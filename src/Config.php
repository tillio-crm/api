<?php

declare(strict_types=1);

namespace TillioCrm\Api;

use TillioCrm\Api\Exception\ConfigurationException;
use TillioCrm\Api\Transport\TransportInterface;

/**
 * Walidacja tablicy konfiguracyjnej i wybór trybu połączenia.
 *
 * Dwa tryby - jedyna różnica w całej bibliotece (uwierzytelnienie + adres docelowy):
 *
 *  - BEZPOŚREDNI (`apiKey`): konsument ma własny klucz API tenanta; żądania lecą
 *    wprost do API v2 z nagłówkami `X-Api-Key` (format `nazwa:klucz`),
 *    `X-Tenant-Domain` i `X-Tenant-Id`.
 *  - PROXY (`accessToken`): apka marketplace bez dostępów tenanta; żądania lecą na
 *    `{server}/api/v1/apps/proxy/tillio/v2/...` z `Authorization: Bearer`, a klucz
 *    CRM dokłada platforma. Token bywa odświeżany w trakcie życia klienta, dlatego
 *    może być callable - rozwiązywane PRZY KAŻDYM żądaniu.
 *
 * Obsługiwane klucze konfiguracji:
 *  - `apiKey` (string `nazwa:klucz`), `tenantDomain`, `tenantId` - tryb bezpośredni,
 *  - `instanceName` (string) - nazwa instancji CRM na serwerze wieloinstancyjnym
 *    (nagłówek `X-Instance-Name`); pomijasz na produkcji z jedną instancją,
 *    tryb bezpośredni,
 *  - `accessToken` (string|callable), `connector` (string) - tryb proxy,
 *  - `server` (string, domyślny zależny od trybu),
 *  - `timeout`, `connectTimeout` (sekundy),
 *  - `caFile` (ścieżka do CA bundle dla środowisk bez `curl.cainfo`),
 *  - `rateLimits` (array okno sekund => maks. żądań; pusta tablica = bez limitera),
 *  - `maxAttempts`, `retryBaseDelay`, `retryMaxDelay`, `maxRetryAfter` - polityka retry,
 *  - `transport` (TransportInterface), `clock` (Clock) - wstrzykiwane w testach.
 *
 * Dokładnie jeden z `apiKey`/`accessToken`; brak lub oba naraz to błąd zgłaszany
 * TUTAJ, w konstruktorze - nie przy pierwszym żądaniu (patrz `ConfigurationException`).
 * Nieznany klucz konfiguracji też jest błędem: literówka w `tenantDomain` nie może
 * po cichu zamienić się w żądania bez nagłówka.
 */
final class Config
{
    /** Domyślny produkcyjny adres API v2 (tryb bezpośredni). */
    public const string DEFAULT_SERVER_DIRECT = 'https://s2.public.api.tillio.app';

    /** Domyślny produkcyjny adres platformy apek (tryb proxy). */
    public const string DEFAULT_SERVER_PROXY = 'https://apps.tillio.app';

    /** Prefiks ścieżki proxy na platformie - za nim jedzie zwykła ścieżka `v2/...`. */
    public const string PROXY_BASE_PATH = 'api/v1/apps/proxy/tillio';

    /**
     * Okna limitera (sekundy => maks. żądań) - wartości sprawdzone na żywym CRM-ie
     * i celowo ciaśniejsze niż deklarowany limit v2 (1000/min per klucz): limit v2
     * jest per KLUCZ, a tym samym kluczem potrafi jechać kilka integracji naraz.
     */
    public const array DEFAULT_RATE_LIMITS = [
        2 => 5,          // 5 żądań / 2 s (2 s zamiast 1 s: zegary serwerów się rozjeżdżają)
        30 => 90,        // 90 / 30 s
        60 => 120,       // 120 / min
        3600 => 3600,    // 3600 / h
        43200 => 14400,  // 14400 / 12 h
    ];

    /** Klucze rozpoznawane przez konstruktor - wszystko inne to literówka. */
    private const array KNOWN_KEYS = [
        'apiKey', 'tenantDomain', 'tenantId', 'instanceName',
        'accessToken', 'connector',
        'server', 'timeout', 'connectTimeout', 'caFile',
        'rateLimits', 'maxAttempts', 'retryBaseDelay', 'retryMaxDelay', 'maxRetryAfter',
        'transport', 'clock',
    ];

    /** Tryb bezpośredni: klucz API w formacie `nazwa:klucz` (null w trybie proxy). */
    public readonly ?string $apiKey;

    /** Tryb bezpośredni: domena tenanta (nagłówek `X-Tenant-Domain`). */
    public readonly ?string $tenantDomain;

    /** Tryb bezpośredni: identyfikator tenanta (nagłówek `X-Tenant-Id`). */
    public readonly ?string $tenantId;

    /**
     * Tryb bezpośredni: nazwa instancji CRM (nagłówek `X-Instance-Name`).
     *
     * Potrzebne tylko na serwerach hostujących WIELE instancji CRM pod jednym
     * adresem API (np. środowiska deweloperskie). Bez tego nagłówka żądanie
     * trafia na instancję domyślną serwera - i uwierzytelnienie kluczem innej
     * instancji kończy się 401. Produkcyjny adres z jedną instancją per domena
     * tego nie wymaga; wtedy zostaw `null`.
     */
    public readonly ?string $instanceName;

    /**
     * Tryb proxy: token apki - string albo callable zwracające string,
     * rozwiązywane przy każdym żądaniu.
     *
     * @var string|callable(): string|null
     */
    public readonly mixed $accessToken;

    /** Tryb proxy: publiczny id połączenia CRM, gdy instalacja ma ich więcej niż jedno. */
    public readonly ?string $connector;

    /** Adres serwera (bez końcowego `/`); domyślny zależy od trybu. */
    public readonly string $server;

    /** Timeout całego żądania w sekundach. */
    public readonly float $timeout;

    /** Timeout nawiązania połączenia w sekundach. */
    public readonly float $connectTimeout;

    /**
     * Ścieżka do pliku CA bundle (CURLOPT_CAINFO). Null = konfiguracja systemowa
     * curl (`curl.cainfo` z php.ini). Przydatne na Windows CLI, gdzie PHP często
     * nie ma skonfigurowanego magazynu certyfikatów i każde żądanie HTTPS pada
     * na weryfikacji TLS. Weryfikacji NIE DA się tym wyłączyć - tylko wskazać
     * właściwy zestaw zaufanych CA.
     *
     * @var non-empty-string|null
     */
    public readonly ?string $caFile;

    /** @var array<int, int> okna limitera: sekundy => maks. żądań (puste = bez limitera) */
    public readonly array $rateLimits;

    /** Łączna liczba prób jednego żądania (1 = bez retry). */
    public readonly int $maxAttempts;

    /** Pierwszy backoff w sekundach; kolejne rosną dwukrotnie. */
    public readonly float $retryBaseDelay;

    /** Sufit pojedynczego backoffu w sekundach. */
    public readonly float $retryMaxDelay;

    /**
     * Sufit dla `Retry-After` z serwera w sekundach - chroni przebieg przed
     * zawieszeniem na kwadrans, gdy v2 każe czekać do resetu szerokiego okna.
     */
    public readonly float $maxRetryAfter;

    /** Transport wstrzykiwany w testach (null = produkcyjny transport curl). */
    public readonly ?TransportInterface $transport;

    /** Zegar wstrzykiwany w testach (null = zegar systemowy). */
    public readonly ?Clock $clock;

    /**
     * @param array<string, mixed> $config tablica konfiguracyjna - obsługiwane klucze
     *                                     w opisie klasy
     *
     * @throws ConfigurationException gdy konfiguracja jest sprzeczna albo niekompletna
     */
    public function __construct(array $config)
    {
        foreach (array_keys($config) as $key) {
            if (!in_array($key, self::KNOWN_KEYS, true)) {
                throw new ConfigurationException(sprintf(
                    'Nieznany klucz konfiguracji "%s". Obsługiwane: %s.',
                    $key,
                    implode(', ', self::KNOWN_KEYS),
                ));
            }
        }

        $hasApiKey = isset($config['apiKey']);
        $hasAccessToken = isset($config['accessToken']);

        if ($hasApiKey === $hasAccessToken) {
            throw new ConfigurationException(
                $hasApiKey
                    ? 'Podano jednocześnie "apiKey" i "accessToken" - tryb połączenia musi być dokładnie jeden.'
                    : 'Brak "apiKey" (tryb bezpośredni) albo "accessToken" (tryb proxy) - podaj dokładnie jeden.',
            );
        }

        if ($hasApiKey) {
            [$this->apiKey, $this->tenantDomain, $this->tenantId] = $this->validateDirect($config);
            $this->accessToken = null;
            $this->connector = null;

            $instanceName = $config['instanceName'] ?? null;
            if ($instanceName !== null && (!is_string($instanceName) || trim($instanceName) === '')) {
                throw new ConfigurationException('Klucz konfiguracji "instanceName" musi być niepustym stringiem (nazwa instancji CRM).');
            }
            $this->instanceName = $instanceName;
        } else {
            [$this->accessToken, $this->connector] = $this->validateProxy($config);
            $this->apiKey = null;
            $this->tenantDomain = null;
            $this->tenantId = null;

            if (isset($config['instanceName'])) {
                throw new ConfigurationException(
                    'Klucz "instanceName" działa tylko w trybie bezpośrednim (apiKey) - w trybie proxy instancję wybiera platforma.',
                );
            }
            $this->instanceName = null;
        }

        $serverRaw = $config['server'] ?? ($hasApiKey ? self::DEFAULT_SERVER_DIRECT : self::DEFAULT_SERVER_PROXY);
        if (!is_string($serverRaw) || rtrim(trim($serverRaw), '/') === '') {
            throw new ConfigurationException('Klucz konfiguracji "server" musi być niepustym adresem URL.');
        }
        $this->server = rtrim(trim($serverRaw), '/');

        $this->timeout = self::positiveFloat($config, 'timeout', 30.0);
        $this->connectTimeout = self::positiveFloat($config, 'connectTimeout', 10.0);

        $caFile = $config['caFile'] ?? null;
        if ($caFile !== null && (!is_string($caFile) || $caFile === '' || !is_file($caFile))) {
            throw new ConfigurationException('Klucz konfiguracji "caFile" musi wskazywać istniejący plik CA bundle.');
        }
        $this->caFile = $caFile;
        $this->rateLimits = self::rateLimits($config['rateLimits'] ?? self::DEFAULT_RATE_LIMITS);

        $maxAttempts = $config['maxAttempts'] ?? 4;
        if (!is_int($maxAttempts) || $maxAttempts < 1) {
            throw new ConfigurationException('Klucz konfiguracji "maxAttempts" musi być liczbą całkowitą >= 1.');
        }
        $this->maxAttempts = $maxAttempts;

        $this->retryBaseDelay = self::positiveFloat($config, 'retryBaseDelay', 0.5);
        $this->retryMaxDelay = self::positiveFloat($config, 'retryMaxDelay', 30.0);
        $this->maxRetryAfter = self::positiveFloat($config, 'maxRetryAfter', 60.0);

        $transport = $config['transport'] ?? null;
        if ($transport !== null && !$transport instanceof TransportInterface) {
            throw new ConfigurationException('Klucz konfiguracji "transport" musi implementować TransportInterface.');
        }
        $this->transport = $transport;

        $clock = $config['clock'] ?? null;
        if ($clock !== null && !$clock instanceof Clock) {
            throw new ConfigurationException('Klucz konfiguracji "clock" musi implementować Clock.');
        }
        $this->clock = $clock;
    }

    /** Czy klient działa w trybie proxy platformy. */
    public function isProxy(): bool
    {
        return $this->accessToken !== null;
    }

    /**
     * Bazowy adres, do którego transport dokleja ścieżki `v2/...`.
     * W trybie proxy zawiera już prefiks proxy platformy.
     */
    public function baseUrl(): string
    {
        return $this->isProxy()
            ? $this->server . '/' . self::PROXY_BASE_PATH
            : $this->server;
    }

    /**
     * Nagłówki uwierzytelniające - budowane PRZY KAŻDYM żądaniu, bo token trybu
     * proxy bywa odświeżany w trakcie życia klienta (callable).
     *
     * @return array<string, string> nazwa nagłówka => wartość
     *
     * @throws ConfigurationException gdy provider tokenu zwróci pustkę
     */
    public function headers(): array
    {
        if (!$this->isProxy()) {
            $headers = [
                'X-Tenant-Domain' => (string) $this->tenantDomain,
                'X-Tenant-Id' => (string) $this->tenantId,
                'X-Api-Key' => (string) $this->apiKey,
            ];
            if ($this->instanceName !== null) {
                // Serwery z wieloma instancjami CRM pod jednym adresem API wybierają
                // instancję po tym nagłówku; bez niego trafiłoby na domyślną.
                $headers['X-Instance-Name'] = $this->instanceName;
            }

            return $headers;
        }

        $token = is_callable($this->accessToken) ? ($this->accessToken)() : $this->accessToken;
        if (!is_string($token) || $token === '') {
            throw new ConfigurationException('Provider "accessToken" zwrócił pusty token.');
        }

        return ['Authorization' => 'Bearer ' . $token];
    }

    /**
     * Parametry query dokładane przez transport do KAŻDEGO żądania API.
     * Dziś tylko `_connector` w trybie proxy - to parametr PLATFORMY (wybór
     * połączenia CRM przy >1 connectorze), nie API, dlatego dokleja go transport,
     * a nie zasoby.
     *
     * @return array<string, string>
     */
    public function extraQuery(): array
    {
        return $this->isProxy() && $this->connector !== null
            ? ['_connector' => $this->connector]
            : [];
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array{string, string, string} apiKey, tenantDomain, tenantId
     */
    private function validateDirect(array $config): array
    {
        $apiKey = $config['apiKey'];
        if (!is_string($apiKey) || !str_contains($apiKey, ':')
            || str_starts_with($apiKey, ':') || str_ends_with($apiKey, ':')
        ) {
            throw new ConfigurationException(
                'Klucz "apiKey" musi mieć format "nazwa:klucz" (te same klucze API co v1).',
            );
        }

        foreach (['tenantDomain', 'tenantId'] as $required) {
            if (!isset($config[$required]) || !is_string($config[$required]) || trim($config[$required]) === '') {
                throw new ConfigurationException(sprintf(
                    'Tryb bezpośredni wymaga niepustego klucza "%s" (nagłówki tenanta).',
                    $required,
                ));
            }
        }

        if (isset($config['connector'])) {
            throw new ConfigurationException(
                'Klucz "connector" działa tylko w trybie proxy (accessToken) - w trybie bezpośrednim nie ma połączeń do wybierania.',
            );
        }

        /** @var string $tenantDomain */
        $tenantDomain = $config['tenantDomain'];
        /** @var string $tenantId */
        $tenantId = $config['tenantId'];

        return [$apiKey, $tenantDomain, $tenantId];
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array{string|callable, string|null} accessToken (callable zwraca string,
     *                                             co i tak weryfikuje `headers()`), connector
     */
    private function validateProxy(array $config): array
    {
        $token = $config['accessToken'];
        if (!is_callable($token) && (!is_string($token) || trim($token) === '')) {
            throw new ConfigurationException(
                'Klucz "accessToken" musi być niepustym stringiem albo callable zwracającym token.',
            );
        }

        foreach (['tenantDomain', 'tenantId'] as $forbidden) {
            if (isset($config[$forbidden])) {
                throw new ConfigurationException(sprintf(
                    'Klucz "%s" działa tylko w trybie bezpośrednim (apiKey) - w trybie proxy tożsamość tenanta dokłada platforma.',
                    $forbidden,
                ));
            }
        }

        $connector = $config['connector'] ?? null;
        if ($connector !== null && (!is_string($connector) || trim($connector) === '')) {
            throw new ConfigurationException('Klucz "connector" musi być niepustym stringiem (publiczny id połączenia).');
        }

        return [$token, $connector];
    }

    /**
     * @return array<int, int>
     */
    private static function rateLimits(mixed $raw): array
    {
        if (!is_array($raw)) {
            throw new ConfigurationException('Klucz konfiguracji "rateLimits" musi być tablicą okno => limit.');
        }

        $limits = [];
        foreach ($raw as $window => $limit) {
            if (!is_int($window) || $window <= 0 || !is_int($limit)) {
                throw new ConfigurationException(
                    'Okna limitera podaje się jako [sekundy(int > 0) => maks. żądań(int)], np. [60 => 120].',
                );
            }
            $limits[$window] = $limit;
        }

        return $limits;
    }

    /** @param array<string, mixed> $config */
    private static function positiveFloat(array $config, string $key, float $default): float
    {
        $value = $config[$key] ?? $default;
        if (!is_int($value) && !is_float($value) || (float) $value <= 0.0) {
            throw new ConfigurationException(sprintf('Klucz konfiguracji "%s" musi być liczbą dodatnią (sekundy).', $key));
        }

        return (float) $value;
    }
}
