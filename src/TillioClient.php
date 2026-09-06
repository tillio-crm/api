<?php

declare(strict_types=1);

namespace TillioCrm\Api;

use TillioCrm\Api\Exception\ApiException;
use TillioCrm\Api\Exception\MaintenanceException;
use TillioCrm\Api\Exception\ProxyBinaryResponseException;
use TillioCrm\Api\Exception\ProxyException;
use TillioCrm\Api\Exception\RateLimitException;
use TillioCrm\Api\Exception\ServerException;
use TillioCrm\Api\Exception\TransportException;
use TillioCrm\Api\Exception\UnexpectedResponseException;
use TillioCrm\Api\Transport\CurlTransport;
use TillioCrm\Api\Transport\FileUpload;
use TillioCrm\Api\Transport\TransportInterface;
use TillioCrm\Api\Transport\TransportRequest;

/**
 * Oficjalny klient Tillio API v2.
 *
 * Dwa tryby połączenia - wybiera je konfiguracja, dokładnie jeden z kluczy
 * `apiKey`/`accessToken` (szczegóły i walidacja: {@see Config}):
 *
 *     // 1. Tryb bezpośredni - konsument ma własny klucz API tenanta
 *     $client = new TillioClient([
 *         'apiKey'       => 'MojaIntegracja:sekret',         // {nazwa}:{klucz}
 *         'tenantDomain' => 'firma.tillio.app',
 *         'tenantId'     => 'firma-abc123',
 *     ]);
 *
 *     // 2. Tryb proxy - apka marketplace, bez dostępów tenanta
 *     $client = new TillioClient([
 *         'accessToken' => fn () => $auth->accessToken(),    // string|callable
 *         'connector'   => 'b3ce…',                          // gdy >1 połączenie CRM
 *     ]);
 *
 * Zasoby i DTO nie wiedzą, którym trybem działa klient - różnica żyje wyłącznie
 * w warstwie transport/konfiguracja. Wbudowane: limiter okienkowy (żeby w ogóle
 * nie dojść do 429), retry z backoffem (429 i 5xx; NIE przerwa serwisowa i NIE
 * pozostałe 4xx) i mapowanie kontraktu błędu `{field, code, message}` na typowane
 * wyjątki.
 *
 * WYMAGANA WERSJA API: instancja Tillio z API v2 **>= 2.0.4** (kanon nazw pól
 * 2.0.0 + poprawki 2.0.4: `defaultPayValue` w zapisie katalogu usług, boole
 * w flagach zgłoszeń). Wersję instancji sprawdzisz bez uwierzytelnienia:
 * `$client->health()['version']`.
 */
final class TillioClient
{
    private readonly Config $config;
    private readonly TransportInterface $transport;
    private readonly Clock $clock;
    private readonly RateLimiter $rateLimiter;

    /** @var array<class-string, Resources\Resource> zasoby budowane leniwie */
    private array $resources = [];

    /**
     * Obsługiwane klucze konfiguracji (komplet z walidacją opisuje {@see Config}):
     * `apiKey`, `tenantDomain`, `tenantId` (tryb bezpośredni) | `accessToken`,
     * `connector` (tryb proxy) | `server`, `timeout`, `connectTimeout`,
     * `rateLimits`, `maxAttempts`, `retryBaseDelay`, `retryMaxDelay`,
     * `maxRetryAfter`, `transport`, `clock`.
     *
     * @param array<string, mixed> $config tablica konfiguracyjna
     *
     * @throws Exception\ConfigurationException gdy konfiguracja jest sprzeczna albo niekompletna
     */
    public function __construct(array $config)
    {
        $this->config = new Config($config);
        $this->clock = $this->config->clock ?? new SystemClock();
        $this->transport = $this->config->transport ?? new CurlTransport($this->config);
        $this->rateLimiter = new RateLimiter($this->config->rateLimits, $this->clock);
    }

    /** Kontrahenci: kartoteki, adresy, upsert z wyszukiwaniem duplikatów. */
    public function contractors(): Resources\Contractors
    {
        return $this->resource(Resources\Contractors::class);
    }

    /** Osoby kontaktowe. */
    public function contacts(): Resources\Contacts
    {
        return $this->resource(Resources\Contacts::class);
    }

    /** Produkty (katalog). */
    public function products(): Resources\Products
    {
        return $this->resource(Resources\Products::class);
    }

    /** Grupy produktów. */
    public function productGroups(): Resources\ProductGroups
    {
        return $this->resource(Resources\ProductGroups::class);
    }

    /** Magazyny. */
    public function warehouses(): Resources\Warehouses
    {
        return $this->resource(Resources\Warehouses::class);
    }

    /** Stany magazynowe (para magazyn/produkt, bez własnego id). */
    public function stocks(): Resources\Stocks
    {
        return $this->resource(Resources\Stocks::class);
    }

    /** Zamówienia. */
    public function orders(): Resources\Orders
    {
        return $this->resource(Resources\Orders::class);
    }

    /** Notatki i ich załączniki. */
    public function notes(): Resources\Notes
    {
        return $this->resource(Resources\Notes::class);
    }

    /** Zgłoszenia i wątki wiadomości. */
    public function tickets(): Resources\Tickets
    {
        return $this->resource(Resources\Tickets::class);
    }

    /** Leady. */
    public function leads(): Resources\Leads
    {
        return $this->resource(Resources\Leads::class);
    }

    /** Zadania, komentarze, załączniki. */
    public function tasks(): Resources\Tasks
    {
        return $this->resource(Resources\Tasks::class);
    }

    /** Projekty. */
    public function projects(): Resources\Projects
    {
        return $this->resource(Resources\Projects::class);
    }

    /** Szanse sprzedaży (pozycje lejków). */
    public function pipelineItems(): Resources\PipelineItems
    {
        return $this->resource(Resources\PipelineItems::class);
    }

    /** Usługi u kontrahentów. */
    public function services(): Resources\Services
    {
        return $this->resource(Resources\Services::class);
    }

    /** Katalog usług i jego grupy. */
    public function serviceCatalog(): Resources\ServiceCatalog
    {
        return $this->resource(Resources\ServiceCatalog::class);
    }

    /** Użytkownicy systemowi (POST bez retry - jednorazowe hasło startowe). */
    public function users(): Resources\Users
    {
        return $this->resource(Resources\Users::class);
    }

    /** Słowniki systemowe (z kolorami; procesy/lejki z zagnieżdżonymi etapami). */
    public function dictionaries(): Resources\Dictionaries
    {
        return $this->resource(Resources\Dictionaries::class);
    }

    /** Definicje pól niestandardowych + provisioning. */
    public function customFields(): Resources\CustomFields
    {
        return $this->resource(Resources\CustomFields::class);
    }

    /** Repozytorium plików kontrahenta (DMS) - adresowanie po publicId. */
    public function dms(): Resources\Dms
    {
        return $this->resource(Resources\Dms::class);
    }

    /** Generator dokumentów (oferty/umowy z szablonów, publikacja online). */
    public function generatedDocuments(): Resources\GeneratedDocuments
    {
        return $this->resource(Resources\GeneratedDocuments::class);
    }

    /** Wysyłka maili: konta, szablony, załączniki. */
    public function mail(): Resources\Mail
    {
        return $this->resource(Resources\Mail::class);
    }

    /** Baza wiedzy wiki (bazy, kategorie, wpisy). */
    public function wiki(): Resources\Wiki
    {
        return $this->resource(Resources\Wiki::class);
    }

    /** Kalendarze i wydarzenia (wymaga API >= 2.2.0; wydarzenia >= 2.3.0). */
    public function calendars(): Resources\Calendars
    {
        return $this->resource(Resources\Calendars::class);
    }

    /** Połączenia telefoniczne w tabeli połączeń CRM (wymaga API >= 2.10.0). */
    public function phoneCalls(): Resources\PhoneCalls
    {
        return $this->resource(Resources\PhoneCalls::class);
    }

    /** Wiadomości SMS w tabeli wiadomości CRM (wymaga API >= 2.10.0). */
    public function textMessages(): Resources\TextMessages
    {
        return $this->resource(Resources\TextMessages::class);
    }

    /** Wyszukanie po numerze telefonu: kto dzwoni (wymaga API >= 2.10.0). */
    public function lookup(): Resources\Lookup
    {
        return $this->resource(Resources\Lookup::class);
    }

    /** Konfiguracja integracji zewnętrznych (Tillio Calls; wymaga API >= 2.11.0). */
    public function integrations(): Resources\Integrations
    {
        return $this->resource(Resources\Integrations::class);
    }

    /**
     * `GET /v2/modules` - moduły instancji CRM z flagą `active` i datą dostępu.
     * Po tym sprawdzisz, czy tenant ma włączony moduł (np. zgłoszenia), zanim
     * zaczniesz w niego pisać.
     *
     * @return list<Dto\Module>
     */
    public function modules(): array
    {
        $modules = [];
        foreach ($this->get('v2/modules')->data() as $row) {
            if (is_array($row)) {
                /** @var array<string, mixed> $row */
                $modules[] = Dto\Module::fromArray($row);
            }
        }

        return $modules;
    }

    /**
     * `GET /v2/health` - jedyna trasa PUBLICZNA (bez klucza) i jedyna działająca
     * w trakcie przerwy serwisowej. Odpowiedź jest PŁASKA, bez koperty `data`:
     * `{status, version, php, checks}`. Degradacja to HTTP 503 - czyli wyjątek,
     * nie `status: degraded` w wyniku. Pole `version` to jedyny sposób wykrycia
     * możliwości instancji (semver + changelog + openapi).
     *
     * @return array<string, mixed>
     */
    public function health(): array
    {
        return $this->get('v2/health')->body;
    }

    /**
     * `GET /v2/whoami` - najtańszy test konfiguracji (czy nagłówki i klucz są OK).
     * Odpowiedź PŁASKA: `{authenticated, tenantDomain, tenantId, keyName, userId}`.
     *
     * @return array<string, mixed>
     */
    public function whoami(): array
    {
        return $this->get('v2/whoami')->body;
    }

    /**
     * `GET /v2/selfcheck` - wykrywanie dryfu między API v2 a schematem CRM.
     *
     * WYKRYTY DRYF TO HTTP 500, ALE ODPOWIEDŹ NIE JEST AWARIĄ: niesie pełny raport,
     * czyli dokładnie to, po co się tu przyszło. Raport jedzie w kopercie błędu
     * (`_error.report`); obsługujemy też starszy kształt (`data` przy 500), bo
     * instancje aktualizują się w swoim tempie. Wyjątek leci dalej tylko wtedy,
     * gdy w body nie ma raportu - czyli przy 500 z prawdziwej awarii.
     *
     * Retry jest tu WYŁĄCZONE: 500 z raportem jest deterministyczne - generyczna
     * polityka ponowień zmarnowałaby wszystkie próby, zanim odda raport.
     *
     * @return array<string, mixed> raport `{status, version, durationMs, core, database}`
     */
    public function selfcheck(): array
    {
        try {
            /** @var array<string, mixed> $data */
            $data = $this->request('GET', 'v2/selfcheck', retry: false)->data();

            return $data;
        } catch (ServerException $e) {
            $error = is_array($e->body['_error'] ?? null) ? $e->body['_error'] : [];
            // Kolejność: najpierw kontrakt bieżący (report w kopercie błędu), potem
            // kształt sprzed API 1.3.0 (500 z kopertą sukcesu).
            $report = $error['report'] ?? ($e->body['data'] ?? null);
            if (!is_array($report)) {
                throw $e;
            }

            /** @var array<string, mixed> $report */
            return $report;
        }
    }

    /**
     * `GET /v2/openapi.json` - pełna specyfikacja OpenAPI instancji, trasa
     * publiczna, odpowiedź płaska. To jedyny sposób sprawdzenia kontraktu
     * KONKRETNEJ instancji zamiast zakładania, że jest jak w dokumentacji.
     *
     * Uwaga: duża odpowiedź (setki kilobajtów) - narzędzie do diagnostyki
     * i startu integracji, nie do pracy bieżącej.
     *
     * @return array<string, mixed>
     */
    public function openapi(): array
    {
        return $this->get('v2/openapi.json')->body;
    }

    /**
     * Żądanie GET.
     *
     * @param array<string, mixed> $query filtry/paginacja (normalizowane przez {@see QueryBuilder})
     */
    public function get(string $path, array $query = []): ApiResponse
    {
        return $this->request('GET', $path, $query);
    }

    /**
     * Żądanie POST z ciałem JSON.
     *
     * @param array<string, mixed>|list<mixed> $body
     * @param array<string, mixed>             $query
     */
    public function post(string $path, array $body, array $query = []): ApiResponse
    {
        return $this->request('POST', $path, $query, $body);
    }

    /**
     * Żądanie PUT z ciałem JSON.
     *
     * @param array<string, mixed>|list<mixed> $body
     * @param array<string, mixed>             $query
     */
    public function put(string $path, array $body, array $query = []): ApiResponse
    {
        return $this->request('PUT', $path, $query, $body);
    }

    /**
     * Żądanie DELETE.
     *
     * @param array<string, mixed> $query
     */
    public function delete(string $path, array $query = []): ApiResponse
    {
        return $this->request('DELETE', $path, $query);
    }

    /**
     * Żądanie POST `multipart/form-data` - upload plików (DMS, załączniki notatek
     * i zadań). API nie ma wariantu JSON dla plików; pola formularza jadą obok
     * plików jako zwykłe pola multipart.
     *
     * Retry jest domyślnie WYŁĄCZONE: upload nie jest idempotentny - powtórka po
     * timeoutcie, który w rzeczywistości doszedł, zostawiłaby w CRM drugi plik
     * (API nie ma DELETE, śmieć zostaje na zawsze).
     *
     * @param array<string, scalar>     $fields pola formularza (nazwa => wartość)
     * @param array<string, FileUpload> $files  pliki (nazwa pola, typowo `file` => plik)
     * @param array<string, mixed>      $query
     */
    public function postMultipart(string $path, array $fields, array $files, array $query = [], bool $retry = false): ApiResponse
    {
        return $this->request('POST', $path, $query, $fields, $files, $retry);
    }

    /**
     * Pobiera plik spod PEŁNEGO adresu - podpisanego URL-a storage'u (`downloadUrl`
     * z metadanych dokumentu/załącznika). Bez nagłówków Tillio (podpis jest w URL-u,
     * token nie może wyciec do obcego hosta), bez limitera (to nie jest budżet API)
     * i bez retry.
     *
     * UWAGA: podpisany URL żyje ~1 minutę - pobieraj od razu po odczycie metadanych,
     * a po wygaśnięciu odpytaj o metadane ponownie. Nie buforuj linku.
     *
     * @return string surowe bajty pliku
     *
     * @throws TransportException          gdy nie udało się dowieźć żądania
     * @throws UnexpectedResponseException gdy storage odpowiedział statusem błędu
     */
    public function download(string $url): string
    {
        $response = $this->transport->send(new TransportRequest('GET', $url, absolute: true));

        if ($response->status >= 400) {
            throw new UnexpectedResponseException(
                sprintf('Pobieranie pliku nie powiodło się [HTTP %d] - podpisany URL mógł wygasnąć (TTL ~1 min).', $response->status),
                $response->status,
                $response->body,
            );
        }

        return $response->body;
    }

    /**
     * Jedna strona listy jako {@see Page} (rekordy + metadane stronicowania).
     *
     * @param array<string, mixed> $query
     *
     * @return Page<array<string, mixed>>
     */
    public function listPage(string $path, array $query = []): Page
    {
        return Page::fromResponse($this->get($path, $query));
    }

    /**
     * Przechodzi wszystkie strony wyniku, oddając rekordy po jednym (generator -
     * pełny przebieg to dziesiątki tysięcy rekordów, materializacja w pamięci
     * jest proszeniem się o OOM).
     *
     * DOMYŚLNIE WYMUSZA `sort=id`. v2 stronicuje przez LIMIT/OFFSET z domyślnym
     * sortowaniem po dacie modyfikacji - kolumnie, która ZMIENIA SIĘ w trakcie
     * przebiegu: rekord zmodyfikowany między stronami przeskakuje w porządku
     * i wypada z niepobranego zakresu (cicha utrata) albo wraca drugi raz.
     * `id` jest unikalne i niezmienne, więc tylko ono daje kolejność, której nic
     * w trakcie nie przestawi. Jawne `sort` w `$query` wygrywa z wymuszeniem;
     * `$stableSort = null` wyłącza je całkiem (konieczne dla stanów magazynowych,
     * które nie mają sortowalnego id).
     *
     * @param array<string, mixed> $query
     * @param int                  $pageSize   rozmiar strony (domyślnie maksymalny - każde żądanie
     *                                         kosztuje round-trip i slot w limiterze)
     * @param string|null          $stableSort pole sortowania wymuszane przy pełnym przejściu;
     *                                         null = nie narzucaj
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function iterateAll(string $path, array $query = [], int $pageSize = 1000, ?string $stableSort = 'id'): \Generator
    {
        if ($stableSort !== null && !isset($query['sort'])) {
            $query['sort'] = $stableSort;
        }

        $page = 1;
        do {
            // Kolejność scalania NIE jest kosmetyczna: `page`/`limit` z tej pętli
            // muszą NADPISAĆ ewentualne wartości z filtrów wołającego. Przy odwrotnej
            // kolejności podane z zewnątrz `page` zamroziłoby numer strony - czyli
            // nieskończona pętla żądań.
            $result = $this->listPage($path, ['page' => $page, 'limit' => $pageSize] + $query);
            foreach ($result->rows as $row) {
                yield $row;
            }
            $page++;
        } while ($result->hasNextPage());
    }

    /**
     * Jedno przejście przez limiter, transport, mapowanie błędów i retry.
     *
     * `$retry = false` wyłącza WSZYSTKIE ponowienia - także transportowe. Używane
     * tam, gdzie powtórka jest groźniejsza niż porażka: `POST /v2/users` (hasło
     * startowe jednorazowe - powtórka po timeoutcie, który doszedł, to "login
     * zajęty" i hasło przepada), uploady multipart, selfcheck (500 z raportem
     * jest deterministyczne).
     *
     * @param array<string, mixed>                  $query
     * @param array<string, mixed>|list<mixed>|null $body
     * @param array<string, FileUpload>             $files pliki multipart (pusta tablica = JSON)
     *
     * @throws ApiException                gdy API odpowiedziało statusem błędu
     * @throws TransportException          gdy nie udało się dowieźć żądania
     * @throws UnexpectedResponseException gdy body 2xx nie jest JSON-em w kontrakcie v2
     */
    public function request(
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
        array $files = [],
        bool $retry = true,
    ): ApiResponse {
        $request = new TransportRequest($method, $path, QueryBuilder::build($query), $body, $files);
        $maxAttempts = $retry ? $this->config->maxAttempts : 1;

        for ($attempt = 1; ; $attempt++) {
            // Limiter PRZED każdą próbą, także przy powtórce - powtórka to
            // pełnoprawne żądanie i musi się mieścić w tych samych oknach.
            $this->rateLimiter->await();

            try {
                $response = $this->transport->send($request);
            } catch (TransportException $e) {
                // Nie dowieźliśmy żądania - nie wiemy nawet, czy serwer je widział.
                // Powtarzamy, bo typowa przyczyna (zerwane połączenie, chwilowy
                // timeout) mija sama; przy zapisach ryzykiem jest duplikat, ale v2
                // broni się `duplicateCheck`. Operacje bez tej obrony (users,
                // uploady) jadą z `$retry = false`.
                if ($attempt >= $maxAttempts) {
                    throw $e;
                }
                $this->clock->sleep($this->backoff($attempt));
                continue;
            }

            if ($response->status < 400) {
                return $this->decode($response->status, $response->body, $response->headers);
            }

            $exception = ErrorMapper::map($response, $this->config->isProxy());
            if ($attempt >= $maxAttempts || !$this->shouldRetry($exception)) {
                throw $exception;
            }

            $this->clock->sleep($this->retryDelay($exception, $attempt));
        }
    }

    /**
     * Ile żądań klient wysłał w ostatnich `$window` sekundach - do logu przebiegu.
     */
    public function requestsInWindow(int $window): int
    {
        return $this->rateLimiter->sentInWindow($window);
    }

    /**
     * Retry TYLKO tam, gdzie powtórka ma szansę pomóc:
     *  - 429 - limit resetuje się z czasem,
     *  - 5xx - chwilowa awaria v2/CRM (także 5xx z proxy: "nieosiągalne"),
     * NIGDY:
     *  - przerwa serwisowa (503 `system.maintenance`) - trwa minuty, nie milisekundy,
     *  - odmowa binariów przez proxy - deterministyczna,
     *  - 4xx poza 429 - ten sam payload da ten sam wynik.
     */
    private function shouldRetry(ApiException $exception): bool
    {
        if ($exception instanceof MaintenanceException || $exception instanceof ProxyBinaryResponseException) {
            return false;
        }

        if ($exception instanceof ProxyException) {
            return $exception->status >= 500;
        }

        return $exception instanceof RateLimitException || $exception instanceof ServerException;
    }

    private function retryDelay(ApiException $exception, int $attempt): float
    {
        if ($exception instanceof RateLimitException && $exception->retryAfterSeconds !== null) {
            // Serwer wie lepiej, kiedy zwolni okno - ale z sufitem, żeby jeden
            // nagłówek nie zawiesił przebiegu na kwadrans.
            return min($exception->retryAfterSeconds, $this->config->maxRetryAfter);
        }

        return $this->backoff($attempt);
    }

    /** Backoff wykładniczy: base, 2*base, 4*base… przycięty sufitem z konfiguracji. */
    private function backoff(int $attempt): float
    {
        $delay = $this->config->retryBaseDelay * (2 ** ($attempt - 1));

        return min($delay, $this->config->retryMaxDelay);
    }

    /**
     * @template T of Resources\Resource
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function resource(string $class): Resources\Resource
    {
        /** @var T */
        return $this->resources[$class] ??= new $class($this);
    }

    /** @param array<string, string> $headers */
    private function decode(int $status, string $body, array $headers): ApiResponse
    {
        if (trim($body) === '') {
            // 204 i puste 200 są legalne (v2 ich dziś nie zwraca, ale proxy potrafi
            // przepuścić pustkę) - traktujemy jak odpowiedź bez treści, nie jak błąd.
            return new ApiResponse($status, [], $headers);
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new UnexpectedResponseException(
                sprintf('Odpowiedź Tillio API v2 nie jest JSON-em [HTTP %d]: %s', $status, substr(trim($body), 0, 200)),
                $status,
                $body,
            );
        }

        /** @var array<string, mixed> $decoded */
        return new ApiResponse($status, $decoded, $headers);
    }
}
