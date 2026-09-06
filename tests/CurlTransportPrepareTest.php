<?php

declare(strict_types=1);

namespace TillioCrm\Api\Tests;

use PHPUnit\Framework\TestCase;
use TillioCrm\Api\Config;
use TillioCrm\Api\Exception\TransportException;
use TillioCrm\Api\Transport\CurlTransport;
use TillioCrm\Api\Transport\FileUpload;
use TillioCrm\Api\Transport\TransportRequest;

/**
 * `prepare()` jest czystą funkcją budującą URL, nagłówki i payload - testujemy
 * na niej OBA tryby połączenia bez stawiania serwera.
 */
final class CurlTransportPrepareTest extends TestCase
{
    private function directTransport(): CurlTransport
    {
        return new CurlTransport(new Config([
            'apiKey' => 'Integracja:sekret',
            'tenantDomain' => 'firma.tillio.app',
            'tenantId' => 'firma-abc123',
            'server' => 'https://api.example.test',
        ]));
    }

    public function testDirectModeBuildsUrlAndTenantHeaders(): void
    {
        $prepared = $this->directTransport()->prepare(
            new TransportRequest('GET', 'v2/contractors', ['limit' => '25']),
        );

        self::assertSame('GET', $prepared['method']);
        self::assertSame('https://api.example.test/v2/contractors?limit=25', $prepared['url']);
        self::assertContains('X-Api-Key: Integracja:sekret', $prepared['headers']);
        self::assertContains('X-Tenant-Domain: firma.tillio.app', $prepared['headers']);
        self::assertContains('X-Tenant-Id: firma-abc123', $prepared['headers']);
        self::assertContains('Accept: application/json', $prepared['headers']);
        self::assertNull($prepared['postFields']);
    }

    public function testProxyModeBuildsPrefixedUrlWithBearer(): void
    {
        $transport = new CurlTransport(new Config([
            'accessToken' => 'sekretny-token',
            'server' => 'https://apps.example.test',
        ]));

        $prepared = $transport->prepare(new TransportRequest('GET', 'v2/contractors'));

        // Ścieżka `v2/...` jest częścią ścieżki proxy - adapter platformy po tym
        // prefiksie kieruje na API v2 i dokłada nagłówki tenanta.
        self::assertSame('https://apps.example.test/api/v1/apps/proxy/tillio/v2/contractors', $prepared['url']);
        self::assertContains('Authorization: Bearer sekretny-token', $prepared['headers']);

        // Nagłówków tenanta w trybie proxy NIE MA - dokłada je platforma z credentiala.
        foreach ($prepared['headers'] as $header) {
            self::assertStringStartsNotWith('X-Tenant', $header);
            self::assertStringStartsNotWith('X-Api-Key', $header);
        }
    }

    public function testProxyAppendsConnectorToQuery(): void
    {
        $transport = new CurlTransport(new Config([
            'accessToken' => 'tok',
            'connector' => 'b3ce42',
            'server' => 'https://apps.example.test',
        ]));

        $prepared = $transport->prepare(new TransportRequest('GET', 'v2/contractors', ['limit' => '5']));

        self::assertSame(
            'https://apps.example.test/api/v1/apps/proxy/tillio/v2/contractors?_connector=b3ce42&limit=5',
            $prepared['url'],
        );
    }

    public function testTokenCallableResolvedOnEveryPrepare(): void
    {
        $counter = 0;
        $transport = new CurlTransport(new Config([
            'accessToken' => function () use (&$counter): string {
                $counter++;

                return 'tok-' . $counter;
            },
        ]));

        $first = $transport->prepare(new TransportRequest('GET', 'v2/health'));
        $second = $transport->prepare(new TransportRequest('GET', 'v2/health'));

        self::assertContains('Authorization: Bearer tok-1', $first['headers']);
        self::assertContains('Authorization: Bearer tok-2', $second['headers']);
    }

    public function testJsonBodyGetsContentTypeHeader(): void
    {
        $prepared = $this->directTransport()->prepare(
            new TransportRequest('POST', 'v2/contractors', [], ['name' => 'Acme', 'alias' => 'acme']),
        );

        self::assertContains('Content-Type: application/json', $prepared['headers']);
        self::assertSame('{"name":"Acme","alias":"acme"}', $prepared['postFields']);
    }

    public function testNestedQuerySerializedWithBrackets(): void
    {
        // `customField[klucz]=wartość` - dokładnie tej składni oczekuje v2.
        $prepared = $this->directTransport()->prepare(
            new TransportRequest('GET', 'v2/contractors', ['customField' => ['erp_id' => '42']]),
        );

        self::assertStringContainsString('customField%5Berp_id%5D=42', $prepared['url']);
    }

    public function testMultipartHasNoManualContentType(): void
    {
        // Curl sam ustawia multipart z boundary - ręczny nagłówek by to zepsuł.
        $prepared = $this->directTransport()->prepare(new TransportRequest(
            'POST',
            'v2/tasks/7/attachments',
            [],
            ['directoryId' => 3],
            ['file' => FileUpload::fromString('tresc', 'raport.pdf', 'application/pdf')],
        ));

        self::assertSame(['directoryId' => '3'], $prepared['postFields']);
        foreach ($prepared['headers'] as $header) {
            self::assertStringStartsNotWith('Content-Type', $header);
        }
    }

    public function testMultipartRejectsNestedFields(): void
    {
        $this->expectException(TransportException::class);
        $this->directTransport()->prepare(new TransportRequest(
            'POST',
            'v2/tasks/7/attachments',
            [],
            ['zagniezdzone' => ['a' => 1]],
            ['file' => FileUpload::fromString('x', 'a.txt')],
        ));
    }

    public function testAbsoluteRequestSkipsBaseUrlAndHeaders(): void
    {
        // Podpisany URL storage'u: token w nagłówku wyciekłby do obcego hosta.
        $prepared = $this->directTransport()->prepare(
            new TransportRequest('GET', 'https://storage.example.test/plik?signature=abc', absolute: true),
        );

        self::assertSame('https://storage.example.test/plik?signature=abc', $prepared['url']);
        self::assertSame([], $prepared['headers']);
    }
}
