<?php

declare(strict_types=1);

namespace TillioCrm\Api\Tests;

use PHPUnit\Framework\TestCase;
use TillioCrm\Api\Exception\FeatureNotSupportedException;
use TillioCrm\Api\Exception\MaintenanceException;
use TillioCrm\Api\Exception\ProxyBinaryResponseException;
use TillioCrm\Api\Exception\ProxyException;
use TillioCrm\Api\Exception\RateLimitException;
use TillioCrm\Api\Exception\ServerException;
use TillioCrm\Api\Exception\ServiceUnavailableException;
use TillioCrm\Api\Exception\TransportException;
use TillioCrm\Api\Exception\UnexpectedResponseException;
use TillioCrm\Api\Exception\ValidationException;
use TillioCrm\Api\Tests\Support\FakeClock;
use TillioCrm\Api\Tests\Support\MockTransport;
use TillioCrm\Api\TillioClient;
use TillioCrm\Api\Transport\TransportResponse;

/**
 * Polityka żądań klienta: retry/backoff, dekodowanie, wyłączanie ponowień.
 * Transport jest atrapą - testujemy DECYZJE klienta, nie sieć.
 */
final class ClientRequestTest extends TestCase
{
    private MockTransport $transport;
    private FakeClock $clock;

    protected function setUp(): void
    {
        $this->transport = new MockTransport();
        $this->clock = new FakeClock();
    }

    /** @param array<string, mixed> $overrides */
    private function client(array $overrides = []): TillioClient
    {
        return new TillioClient($overrides + [
            'apiKey' => 'Test:klucz',
            'tenantDomain' => 'firma.tillio.app',
            'tenantId' => 'firma-abc123',
            'rateLimits' => [],
            'transport' => $this->transport,
            'clock' => $this->clock,
        ]);
    }

    private static function errorJson(int $code, string $message): string
    {
        return (string) json_encode(['_error' => ['code' => $code, 'message' => $message]]);
    }

    public function testSuccessDecodesEnvelope(): void
    {
        $this->transport->queueJson(200, '{"data":[{"id":1}],"pagination":{"page":1,"limit":25,"total":1,"pages":1}}');

        $response = $this->client()->get('v2/contractors', ['limit' => 25]);

        self::assertSame(200, $response->status);
        self::assertSame([['id' => 1]], $response->data());
        self::assertSame(1, $response->pagination()['total']);
        self::assertSame('v2/contractors', $this->transport->lastRequest()->path);
        self::assertSame(['limit' => '25'], $this->transport->lastRequest()->query);
    }

    public function testStatus201ReachesCaller(): void
    {
        // 201 = utworzono, 200 = duplikat - klient NIE może zgubić tej różnicy.
        $this->transport->queueJson(201, '{"data":{"id":5},"info":{"created":true}}');

        $response = $this->client()->post('v2/contractors', ['name' => 'Acme']);

        self::assertSame(201, $response->status);
        self::assertSame(['created' => true], $response->info());
    }

    public function testEmptyBodyIsNotAnError(): void
    {
        $this->transport->queue(new TransportResponse(204, ''));

        $response = $this->client()->get('v2/health');

        self::assertSame(204, $response->status);
        self::assertSame([], $response->body);
    }

    public function testNonJsonBodyThrowsUnexpectedResponse(): void
    {
        $this->transport->queue(new TransportResponse(200, '<html>reverse proxy</html>'));

        $this->expectException(UnexpectedResponseException::class);
        $this->client()->get('v2/contractors');
    }

    public function test5xxIsRetriedWithBackoff(): void
    {
        $this->transport
            ->queueJson(500, self::errorJson(500, 'server.error'))
            ->queueJson(500, self::errorJson(500, 'server.error'))
            ->queueJson(200, '{"data":[]}');

        $response = $this->client(['retryBaseDelay' => 0.5])->get('v2/contractors');

        self::assertSame(200, $response->status);
        self::assertCount(3, $this->transport->requests);
        // Backoff wykładniczy: 0.5, potem 1.0.
        self::assertEqualsWithDelta([0.5, 1.0], $this->clock->sleeps, 0.001);
    }

    public function testExhaustedAttemptsThrowLastError(): void
    {
        $this->transport
            ->queueJson(500, self::errorJson(500, 'server.error'))
            ->queueJson(500, self::errorJson(500, 'server.error'));

        $this->expectException(ServerException::class);
        $this->client(['maxAttempts' => 2])->get('v2/contractors');
    }

    public function test429HonorsRetryAfterWithCap(): void
    {
        $this->transport
            ->queue(new TransportResponse(429, self::errorJson(429, 'rateLimit.exceeded'), ['retry-after' => '600']))
            ->queueJson(200, '{"data":[]}');

        $this->client(['maxRetryAfter' => 10.0])->get('v2/contractors');

        // Serwer kazał czekać 600 s - sufit przycina do 10 s, żeby jeden nagłówek
        // nie zawiesił przebiegu.
        self::assertEqualsWithDelta([10.0], $this->clock->sleeps, 0.001);
    }

    public function test429WithoutHeaderGetsBackoff(): void
    {
        $this->transport
            ->queueJson(429, self::errorJson(429, 'rateLimit.exceeded'))
            ->queueJson(200, '{"data":[]}');

        $this->client(['retryBaseDelay' => 0.25])->get('v2/contractors');

        self::assertEqualsWithDelta([0.25], $this->clock->sleeps, 0.001);
    }

    public function testMaintenanceIsNotRetried(): void
    {
        // Przerwa serwisowa trwa minuty - backoff tylko zamroziłby przebieg.
        $this->transport->queueJson(503, self::errorJson(503, 'system.maintenance'));

        try {
            $this->client()->get('v2/contractors');
            self::fail('Oczekiwano MaintenanceException.');
        } catch (MaintenanceException) {
            self::assertCount(1, $this->transport->requests);
            self::assertSame([], $this->clock->sleeps);
        }
    }

    public function testFeatureUnavailable503IsNotRetried(): void
    {
        // 503 z kodem symbolicznym (instalacja bez modułu kalendarza) jest
        // deterministyczne - retry dałby ten sam wynik i zmarnował próby.
        $this->transport->queueJson(503, self::errorJson(503, 'calendar.serviceUnavailable'));

        try {
            $this->client()->get('v2/calendars/7/events');
            self::fail('Oczekiwano ServiceUnavailableException.');
        } catch (ServiceUnavailableException $e) {
            self::assertSame('calendar.serviceUnavailable', $e->errorCode);
            self::assertCount(1, $this->transport->requests);
            self::assertSame([], $this->clock->sleeps);
        }
    }

    public function testPlain503WithoutCodeIsRetriedToMaxAttempts(): void
    {
        // Kontrast: gołe 503 bez kodu symbolicznego to chwilowa awaria -
        // podlega retry aż do wyczerpania prób.
        $this->transport
            ->queue(new TransportResponse(503, '<html>Service Unavailable</html>'))
            ->queue(new TransportResponse(503, '<html>Service Unavailable</html>'));

        try {
            $this->client(['maxAttempts' => 2])->get('v2/calendars/7/events');
            self::fail('Oczekiwano ServerException.');
        } catch (ServerException) {
            self::assertCount(2, $this->transport->requests);
        }
    }

    public function testFeatureNotSupported501IsNotRetried(): void
    {
        // 501 feature.notSupportedByCrmVersion jest deterministyczne (za stary CRM
        // instalacji) - retry dałby ten sam wynik i zmarnował próby. Kontrast z 500.
        $this->transport->queueJson(501, self::errorJson(501, 'feature.notSupportedByCrmVersion'));

        try {
            $this->client()->get('v2/notes/9/contacts');
            self::fail('Oczekiwano FeatureNotSupportedException.');
        } catch (FeatureNotSupportedException $e) {
            self::assertSame('feature.notSupportedByCrmVersion', $e->errorCode);
            self::assertCount(1, $this->transport->requests);
            self::assertSame([], $this->clock->sleeps);
        }
    }

    public function test4xxOtherThan429IsNotRetried(): void
    {
        $this->transport->queueJson(422, self::errorJson(422, 'validation.error'));

        try {
            $this->client()->get('v2/contractors');
            self::fail('Oczekiwano ValidationException.');
        } catch (ValidationException) {
            self::assertCount(1, $this->transport->requests);
        }
    }

    public function testTransportErrorIsRetried(): void
    {
        $this->transport
            ->queueThrowable(new TransportException('timeout'))
            ->queueJson(200, '{"data":[]}');

        $response = $this->client()->get('v2/contractors');

        self::assertSame(200, $response->status);
        self::assertCount(2, $this->transport->requests);
    }

    public function testRetryFalseDisablesTransportRetriesToo(): void
    {
        // `POST /v2/users` i uploady: powtórka po timeoutcie, który w rzeczywistości
        // doszedł, jest groźniejsza niż porażka.
        $this->transport->queueThrowable(new TransportException('timeout'));

        try {
            $this->client()->request('POST', 'v2/users', body: ['login' => 'jan'], retry: false);
            self::fail('Oczekiwano TransportException.');
        } catch (TransportException) {
            self::assertCount(1, $this->transport->requests);
        }
    }

    public function testProxy5xxIsRetriedButBinaryRefusalIsNot(): void
    {
        $proxyTransport = new MockTransport();
        $proxyTransport
            ->queueJson(502, self::errorJson(502, 'Zewnętrzne API nieosiągalne: timeout'))
            ->queueJson(200, '{"data":[]}');

        $proxyClient = new TillioClient([
            'accessToken' => 'tok',
            'rateLimits' => [],
            'transport' => $proxyTransport,
            'clock' => $this->clock,
        ]);

        self::assertSame(200, $proxyClient->get('v2/contractors')->status);
        self::assertCount(2, $proxyTransport->requests);

        // Odmowa binariów jest deterministyczna - retry zmarnowałby próby.
        $proxyTransport->queueJson(502, self::errorJson(502, "Zewnętrzne API zwróciło nieobsługiwany typ treści 'application/pdf' (HTTP 200)."));
        try {
            $proxyClient->get('v2/dms/documents/abc');
            self::fail('Oczekiwano ProxyBinaryResponseException.');
        } catch (ProxyBinaryResponseException) {
            self::assertCount(3, $proxyTransport->requests);
        }
    }

    public function testProxy4xxIsNotRetried(): void
    {
        $proxyTransport = new MockTransport();
        $proxyTransport->queueJson(403, self::errorJson(403, "Operacja GET v2/users nie jest dozwolona przez adapter usługi 'tillio'."));

        $proxyClient = new TillioClient([
            'accessToken' => 'tok',
            'rateLimits' => [],
            'transport' => $proxyTransport,
            'clock' => $this->clock,
        ]);

        try {
            $proxyClient->get('v2/users');
            self::fail('Oczekiwano ProxyException.');
        } catch (ProxyException $e) {
            self::assertSame(403, $e->status);
            self::assertCount(1, $proxyTransport->requests);
        }
    }

    public function testLimiterAppliesToEveryAttempt(): void
    {
        // Okno 1 żądanie / 10 s: druga próba (retry po 500) musi poczekać na okno,
        // bo powtórka to pełnoprawne żądanie.
        $this->transport
            ->queueJson(500, self::errorJson(500, 'server.error'))
            ->queueJson(200, '{"data":[]}');

        $this->client(['rateLimits' => [10 => 1], 'retryBaseDelay' => 0.5])->get('v2/contractors');

        // sleep backoffu (0.5) + sleep limitera (~9.5 do zwolnienia okna).
        self::assertCount(2, $this->clock->sleeps);
        self::assertEqualsWithDelta(9.5, $this->clock->sleeps[1], 0.01);
    }

    public function testDownloadReturnsRawBytesWithoutDecoding(): void
    {
        $this->transport->queue(new TransportResponse(200, "\x25PDF-1.7 binarna tresc"));

        $content = $this->client()->download('https://storage.example.test/plik?sig=abc');

        self::assertSame("\x25PDF-1.7 binarna tresc", $content);
        $request = $this->transport->lastRequest();
        self::assertTrue($request->absolute);
        self::assertSame('https://storage.example.test/plik?sig=abc', $request->path);
    }

    public function testDownloadAfterLinkExpiryThrowsReadableException(): void
    {
        $this->transport->queue(new TransportResponse(403, 'SignatureDoesNotMatch'));

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('wygasn');
        $this->client()->download('https://storage.example.test/plik?sig=stary');
    }

    /**
     * `download()` bierze pełny adres z zewnątrz - jedyne takie miejsce w SDK.
     * Gdyby aplikacja konsumenta przepuściła tu adres od swojego użytkownika,
     * cURL bez filtru obsłużyłby też `file://` (odczyt plików procesu) i schematy
     * strzelające w usługi wewnętrzne. Odrzucamy PRZED wysyłką.
     */
    public function testDownloadRejectsSchemesOtherThanHttp(): void
    {
        $blocked = [
            'file:///C:/Windows/win.ini',
            'file://localhost/etc/passwd',
            'ftp://storage.example.test/plik.pdf',
            'dict://127.0.0.1:11211/stat',
            'gopher://127.0.0.1:6379/_INFO',
            'FILE:///etc/passwd',
        ];

        foreach ($blocked as $url) {
            try {
                $this->client()->download($url);
                self::fail(sprintf('Adres "%s" powinien zostać odrzucony przed wysyłką.', $url));
            } catch (TransportException) {
                // oczekiwane
            }
        }

        // Nic z tego nie może opuścić procesu - filtr stoi przed transportem.
        self::assertSame([], $this->transport->requests);
    }

    public function testDownloadRejectsMalformedUrlsAndCredentials(): void
    {
        $blocked = [
            '',
            'storage.example.test/plik',            // bez schematu
            '//storage.example.test/plik',          // bez schematu
            'https:///plik',                        // bez hosta
            'https://uzytkownik:haslo@storage.example.test/plik',
            "https://storage.example.test/plik\r\nX-Wstrzykniety: 1",
        ];

        foreach ($blocked as $url) {
            try {
                $this->client()->download($url);
                self::fail(sprintf('Adres "%s" powinien zostać odrzucony przed wysyłką.', $url));
            } catch (TransportException) {
                // oczekiwane
            }
        }

        self::assertSame([], $this->transport->requests);
    }

    /** Komunikat odmowy nie może wnieść podpisu pobrania do logu wyjątków. */
    public function testDownloadRejectionKeepsSignatureOutOfTheMessage(): void
    {
        try {
            $this->client()->download('ftp://storage.example.test/plik?signature=TAJNY_PODPIS');
            self::fail('Adres ftp:// powinien zostać odrzucony.');
        } catch (TransportException $e) {
            self::assertStringNotContainsString('TAJNY_PODPIS', $e->getMessage());
            self::assertStringContainsString('ftp', $e->getMessage());
        }
    }

    /** Storage on-premise bywa wystawiony po zwykłym HTTP - tego nie blokujemy. */
    public function testDownloadAllowsPlainHttpForOnPremiseStorage(): void
    {
        $this->transport->queue(new TransportResponse(200, 'tresc'));

        self::assertSame('tresc', $this->client()->download('http://storage.firma.local/plik?sig=abc'));
    }
}
