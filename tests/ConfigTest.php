<?php

declare(strict_types=1);

namespace TillioCrm\Api\Tests;

use PHPUnit\Framework\TestCase;
use TillioCrm\Api\Config;
use TillioCrm\Api\Exception\ConfigurationException;

/**
 * Walidacja konfiguracji: tryb musi być dokładnie jeden, błędy wychodzą
 * W KONSTRUKTORZE (nie przy pierwszym żądaniu), a literówka w kluczu nie może
 * przejść po cichu.
 */
final class ConfigTest extends TestCase
{
    /** @var array{apiKey: string, tenantDomain: string, tenantId: string} */
    private const array DIRECT = [
        'apiKey' => 'Integracja:sekret',
        'tenantDomain' => 'firma.tillio.app',
        'tenantId' => 'firma-abc123',
    ];

    public function testMissingBothModesFailsInConstructor(): void
    {
        $this->expectException(ConfigurationException::class);
        new Config([]);
    }

    public function testBothModesAtOnceFailsInConstructor(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('dokładnie jeden');
        new Config(self::DIRECT + ['accessToken' => 'tok']);
    }

    public function testUnknownConfigKeyFails(): void
    {
        // Literówka (`tennantId`) nie może po cichu zamienić się w żądania bez nagłówka.
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('tennantId');
        new Config(self::DIRECT + ['tennantId' => 'x']);
    }

    public function testDirectModeRequiresTenantFields(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('tenantDomain');
        new Config(['apiKey' => 'Integracja:sekret', 'tenantId' => 'firma-abc123']);
    }

    public function testApiKeyMustHaveNameColonKeyFormat(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('nazwa:klucz');
        new Config(['apiKey' => 'bez-dwukropka'] + ['tenantDomain' => 'a', 'tenantId' => 'b']);
    }

    public function testConnectorAllowedOnlyInProxyMode(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('connector');
        new Config(self::DIRECT + ['connector' => 'b3ce']);
    }

    public function testTenantDomainAllowedOnlyInDirectMode(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('tenantDomain');
        new Config(['accessToken' => 'tok', 'tenantDomain' => 'firma.tillio.app']);
    }

    public function testBlankAccessTokenFails(): void
    {
        $this->expectException(ConfigurationException::class);
        new Config(['accessToken' => '   ']);
    }

    public function testInstanceNameAddsHeaderInDirectMode(): void
    {
        $config = new Config(self::DIRECT + ['instanceName' => 'instancja-b.tillio.app']);
        self::assertSame('instancja-b.tillio.app', $config->instanceName);
        self::assertSame('instancja-b.tillio.app', $config->headers()['X-Instance-Name'] ?? null);
    }

    public function testInstanceNameAbsentByDefault(): void
    {
        $config = new Config(self::DIRECT);
        self::assertNull($config->instanceName);
        self::assertArrayNotHasKey('X-Instance-Name', $config->headers());
    }

    public function testInstanceNameForbiddenInProxyMode(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('instanceName');
        new Config(['accessToken' => 'tok', 'instanceName' => 'x']);
    }

    public function testEmptyInstanceNameFails(): void
    {
        $this->expectException(ConfigurationException::class);
        new Config(self::DIRECT + ['instanceName' => '  ']);
    }

    public function testDefaultServersDependOnMode(): void
    {
        $direct = new Config(self::DIRECT);
        self::assertSame(Config::DEFAULT_SERVER_DIRECT, $direct->server);
        self::assertFalse($direct->isProxy());

        $proxy = new Config(['accessToken' => 'tok']);
        self::assertSame(Config::DEFAULT_SERVER_PROXY, $proxy->server);
        self::assertTrue($proxy->isProxy());
    }

    public function testProxyBaseUrlContainsPlatformPrefix(): void
    {
        $proxy = new Config(['accessToken' => 'tok', 'server' => 'https://apps.example.test/']);

        self::assertSame('https://apps.example.test/api/v1/apps/proxy/tillio', $proxy->baseUrl());
    }

    public function testDirectBaseUrlHasNoPrefix(): void
    {
        $direct = new Config(self::DIRECT + ['server' => 'https://api.example.test/']);

        self::assertSame('https://api.example.test', $direct->baseUrl());
    }

    public function testDirectModeHeaders(): void
    {
        $config = new Config(self::DIRECT);

        self::assertSame([
            'X-Tenant-Domain' => 'firma.tillio.app',
            'X-Tenant-Id' => 'firma-abc123',
            'X-Api-Key' => 'Integracja:sekret',
        ], $config->headers());
    }

    public function testTokenCallableResolvedOnEveryCall(): void
    {
        // Token bywa odświeżany w trakcie życia klienta - nagłówek musi nieść
        // AKTUALNĄ wartość, nie tę z momentu budowy klienta.
        $counter = 0;
        $config = new Config(['accessToken' => function () use (&$counter): string {
            $counter++;

            return 'token-' . $counter;
        }]);

        self::assertSame(['Authorization' => 'Bearer token-1'], $config->headers());
        self::assertSame(['Authorization' => 'Bearer token-2'], $config->headers());
        self::assertSame(2, $counter);
    }

    public function testBlankTokenFromCallableFailsOnRequest(): void
    {
        $config = new Config(['accessToken' => static fn (): string => '']);

        $this->expectException(ConfigurationException::class);
        $config->headers();
    }

    public function testExtraQueryCarriesConnectorOnlyInProxyMode(): void
    {
        $proxy = new Config(['accessToken' => 'tok', 'connector' => 'b3ce42']);
        self::assertSame(['_connector' => 'b3ce42'], $proxy->extraQuery());

        $proxyBez = new Config(['accessToken' => 'tok']);
        self::assertSame([], $proxyBez->extraQuery());

        $direct = new Config(self::DIRECT);
        self::assertSame([], $direct->extraQuery());
    }

    public function testNegativeTimeoutFails(): void
    {
        $this->expectException(ConfigurationException::class);
        new Config(self::DIRECT + ['timeout' => -1]);
    }

    public function testCaFileMustExist(): void
    {
        // Literówka w ścieżce CA bundle nie może po cichu zostawić curla bez
        // certyfikatów - żądania i tak by padły, tylko dużo mniej czytelnie.
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('caFile');
        new Config(self::DIRECT + ['caFile' => '/nie/ma/takiego/pliku.crt']);
    }

    public function testCaFileAcceptsExistingFile(): void
    {
        $config = new Config(self::DIRECT + ['caFile' => __FILE__]);

        self::assertSame(__FILE__, $config->caFile);
        self::assertNull((new Config(self::DIRECT))->caFile);
    }

    public function testZeroMaxAttemptsFails(): void
    {
        $this->expectException(ConfigurationException::class);
        new Config(self::DIRECT + ['maxAttempts' => 0]);
    }
}
