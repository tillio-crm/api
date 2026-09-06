<?php

declare(strict_types=1);

namespace TillioCrm\Api\Tests;

use PHPUnit\Framework\TestCase;
use TillioCrm\Api\Exception\ServerException;
use TillioCrm\Api\Tests\Support\FakeClock;
use TillioCrm\Api\Tests\Support\MockTransport;
use TillioCrm\Api\TillioClient;

final class SystemEndpointsTest extends TestCase
{
    private MockTransport $transport;

    protected function setUp(): void
    {
        $this->transport = new MockTransport();
    }

    private function client(): TillioClient
    {
        return new TillioClient([
            'apiKey' => 'Test:klucz',
            'tenantDomain' => 'firma.tillio.app',
            'tenantId' => 'firma-abc123',
            'rateLimits' => [],
            'transport' => $this->transport,
            'clock' => new FakeClock(),
        ]);
    }

    public function testHealthAndWhoamiAreFlatResponses(): void
    {
        $this->transport
            ->queueJson(200, '{"status":"ok","version":"2.0.3","php":"8.2.33"}')
            ->queueJson(200, '{"authenticated":true,"tenantDomain":"firma.tillio.app","keyName":"Test"}');

        $client = $this->client();

        self::assertSame('2.0.3', $client->health()['version']);
        self::assertSame('Test', $client->whoami()['keyName']);
        self::assertSame('v2/health', $this->transport->requests[0]->path);
        self::assertSame('v2/whoami', $this->transport->requests[1]->path);
    }

    public function testSelfcheckSuccess(): void
    {
        $this->transport->queueJson(200, '{"data":{"status":"ok","version":"2.0.3"}}');

        self::assertSame('ok', $this->client()->selfcheck()['status']);
    }

    public function testSelfcheck500WithReportIsResultNotFailure(): void
    {
        // Wykryty dryf = HTTP 500, ale odpowiedź NIESIE PEŁNY RAPORT - dokładnie to,
        // po co się tu przyszło. I bez retry: dryf jest deterministyczny.
        $this->transport->queueJson(500, (string) json_encode([
            '_error' => [
                'code' => 500,
                'message' => 'selfcheck.failed',
                'errors' => [['field' => 'core', 'code' => 'selfcheck.missingMethod', 'message' => 'Brak metody X.']],
                'report' => ['status' => 'failed', 'core' => ['missing' => ['X']]],
            ],
        ]));

        $report = $this->client()->selfcheck();

        self::assertSame('failed', $report['status']);
        self::assertCount(1, $this->transport->requests);
    }

    public function testSelfcheckLegacyShape500WithDataEnvelope(): void
    {
        // Instancje aktualizują się w swoim tempie - kształt sprzed API 1.3.0
        // (500 z kopertą sukcesu) też musi oddać raport.
        $this->transport->queueJson(500, '{"data":{"status":"failed","database":{"missing":["kolumna"]}}}');

        self::assertSame('failed', $this->client()->selfcheck()['status']);
    }

    public function testSelfcheck500WithoutReportIsRealFailure(): void
    {
        $this->transport->queueJson(500, '{"_error":{"code":500,"message":"server.error"}}');

        $this->expectException(ServerException::class);
        $this->client()->selfcheck();
    }

    public function testOpenapiReturnsFlatSpec(): void
    {
        $this->transport->queueJson(200, '{"openapi":"3.0.0","info":{"version":"2.0.0"}}');

        $spec = $this->client()->openapi();

        self::assertSame('3.0.0', $spec['openapi']);
        self::assertSame('v2/openapi.json', $this->transport->lastRequest()->path);
    }
}
