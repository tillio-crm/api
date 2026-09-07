# tillio-crm/api

Oficjalne SDK PHP dla **Tillio API v2** - pełnego REST-owego API systemu Tillio CRM.
Pokrywa **100% tras API** (pilnowane testem kontraktowym), każda encja ma typowane
DTO z named arguments, a znane pułapki kontraktu (ciche duplikaty, gubione strony,
jednorazowe hasła) są zneutralizowane w kodzie, zanim żądanie w ogóle wyjdzie.

[![Latest Version](https://img.shields.io/packagist/v/tillio-crm/api.svg)](https://packagist.org/packages/tillio-crm/api)
[![Total Downloads](https://img.shields.io/packagist/dt/tillio-crm/api.svg)](https://packagist.org/packages/tillio-crm/api)
[![PHP Version](https://img.shields.io/badge/php-%5E8.3-blue)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

---

## Spis treści

- [Wymagania](#wymagania)
- [Instalacja](#instalacja)
- [Szybki start](#szybki-start)
  - [Tryb bezpośredni (klucz API)](#tryb-bezpośredni-klucz-api)
  - [Tryb proxy (aplikacje marketplace)](#tryb-proxy-aplikacje-marketplace)
- [Zasoby](#zasoby)
- [Odczyt: strony i pełne przebiegi](#odczyt-strony-i-pełne-przebiegi)
- [Zapis: DTO, duplikaty, upsert](#zapis-dto-duplikaty-upsert)
- [Obsługa błędów](#obsługa-błędów)
- [Retry i limity żądań](#retry-i-limity-żądań)
- [Pliki: upload i pobieranie](#pliki-upload-i-pobieranie)
- [Ograniczenia trybu proxy](#ograniczenia-trybu-proxy)
- [Konfiguracja - wszystkie klucze](#konfiguracja---wszystkie-klucze)
- [Przykłady i integracja przez AI](#przykłady-i-integracja-przez-ai)
- [Testy](#testy)
- [Wersjonowanie](#wersjonowanie)
- [Troubleshooting](#troubleshooting)
- [Wsparcie](#wsparcie)
- [Licencja](#licencja)

---

## Wymagania

- PHP `^8.3`, rozszerzenia `ext-curl` i `ext-json` - **zero innych zależności**
- Composer
- Instancja Tillio z **API v2 w wersji co najmniej 2.0.4** - sprawdzisz ją bez
  uwierzytelnienia: `GET /v2/health` zwraca pole `version` (w SDK:
  `$client->health()['version']`). Starsze wydania 2.0.x zadziałają z drobnymi
  różnicami opisanymi w PHPDoc (`ServiceCatalogItemInput`, flagi zgłoszeń);
  wydań sprzed 2.0.0 SDK nie obsługuje wcale.
- Część funkcji wymaga nowszej instancji - metody mają to zaznaczone w PHPDoc:
  kalendarze od **2.2.0**, wydarzenia kalendarza od **2.3.0**, szablony
  (maili, notatek, zadań), ich kategorie i pełny cykl typów dokumentów od
  **2.4.0**, pliki pól niestandardowych typu FILE od **2.6.0**, komentarze
  zadań i załączniki szablonów maili od **2.7.0**, kontakty przy notatkach,
  zadaniach i wydarzeniach od **2.8.0**, połączenia telefoniczne, SMS i lookup
  po numerze od **2.10.0**, integracja Tillio Calls od **2.11.0**. Wywołanie
  funkcji, której CRM instalacji jeszcze nie ma, kończy się `FeatureNotSupportedException`
  (501); trasy spoza wersji API - błędem 404.

## Instalacja

```bash
composer require tillio-crm/api
```

## Szybki start

### Tryb bezpośredni (klucz API)

Dla skryptów, integracji własnych i narzędzi wewnętrznych - masz klucz API
tenanta (ten sam, co w API v1, format `nazwa:klucz`) i tożsamość instancji:

```php
use TillioCrm\Api\TillioClient;

$client = new TillioClient([
    'apiKey'       => 'MojaIntegracja:sekret',       // format nazwa:klucz (jak w API v1)
    'tenantDomain' => 'firma.tillio.app',            // nagłówek X-Tenant-Domain
    'tenantId'     => 'firma-abc123',                // nagłówek X-Tenant-Id
    // 'server'       => 'https://s2.public.api.tillio.app',  // domyślny SaaS
    // 'instanceName' => 'firma.tillio.app',         // TYLKO serwer wieloinstancyjny (np. dev) - patrz niżej
]);

// najtańszy test konfiguracji:
$who = $client->whoami();
// ['authenticated' => true, 'tenantDomain' => ..., 'tenantId' => ..., 'keyName' => ..., 'userId' => ...]

// i do pracy:
$page = $client->contractors()->list(['limit' => 25]);
foreach ($page as $contractor) {
    echo $contractor->name, ' NIP: ', $contractor->taxId, PHP_EOL;
}
```

### Tryb proxy (aplikacje marketplace)

Aplikacja marketplace **nie ma i nie może mieć** klucza do CRM - woła platformę
własnym tokenem, a platforma dokłada sekret tenanta. Token podajesz jako string
albo **callable wywoływane przy każdym żądaniu** (odświeżanie tokenu działa
wtedy samo):

```php
$client = new TillioClient([
    'accessToken' => fn () => $auth->accessToken(),
    // 'server'    => 'https://apps.tillio.app',       // platforma, domyślny prod
    // 'connector' => '<publicId-polaczenia>',         // tylko gdy instalacja ma >1 połączenie CRM
]);

$page = $client->contractors()->list(['limit' => 25]);   // identycznie jak wyżej
```

Tryb wybiera konfiguracja: **dokładnie jeden** z `apiKey`/`accessToken` -
brak lub oba naraz to `ConfigurationException` już w konstruktorze. Klasy
zasobów są identyczne w obu trybach i nie wiedzą, którym działają.

> **`whoami()` zwraca 401 mimo poprawnego klucza?** Jeśli łączysz się z adresem
> hostującym wiele instancji CRM (typowo środowiska deweloperskie), podaj
> `instanceName` - nazwę instancji (nagłówek `X-Instance-Name`). Bez niego
> żądanie trafia na instancję DOMYŚLNĄ serwera, a klucz innej instancji nie
> pasuje. Produkcyjny adres z jedną instancją per domena tego nie wymaga.

## Zasoby

Każdy zasób to jawna metoda fasady (IDE i analiza statyczna widzą typy):

| Metoda fasady | Zakres | Przykłady |
|---|---|---|
| `contractors()` | kartoteki kontrahentów + adresy + upsert | [docs/examples/contractors.md](docs/examples/contractors.md) |
| `contacts()` | osoby kontaktowe + upsert | [docs/examples/contacts.md](docs/examples/contacts.md) |
| `products()` | produkty | [docs/examples/products.md](docs/examples/products.md) |
| `productGroups()` | grupy produktów (drzewo) | [docs/examples/product-groups.md](docs/examples/product-groups.md) |
| `warehouses()` | magazyny | [docs/examples/warehouses.md](docs/examples/warehouses.md) |
| `stocks()` | stany magazynowe (para magazyn/produkt) | [docs/examples/stocks.md](docs/examples/stocks.md) |
| `orders()` | zamówienia z pozycjami | [docs/examples/orders.md](docs/examples/orders.md) |
| `notes()` | notatki + załączniki + szablony | [docs/examples/notes.md](docs/examples/notes.md) |
| `tickets()` | zgłoszenia + wątki wiadomości | [docs/examples/tickets.md](docs/examples/tickets.md) |
| `leads()` | leady | [docs/examples/leads.md](docs/examples/leads.md) |
| `tasks()` | zadania + komentarze + załączniki + szablony | [docs/examples/tasks.md](docs/examples/tasks.md) |
| `projects()` | projekty | [docs/examples/projects.md](docs/examples/projects.md) |
| `pipelineItems()` | szanse sprzedaży (pozycje lejków) | [docs/examples/pipeline-items.md](docs/examples/pipeline-items.md) |
| `services()` | usługi u kontrahentów | [docs/examples/services.md](docs/examples/services.md) |
| `serviceCatalog()` | katalog usług + grupy | [docs/examples/service-catalog.md](docs/examples/service-catalog.md) |
| `users()` | użytkownicy systemowi (hasło startowe!) | [docs/examples/users.md](docs/examples/users.md) |
| `dictionaries()` | wszystkie słowniki, procesy, lejki | [docs/examples/dictionaries.md](docs/examples/dictionaries.md) |
| `customFields()` | definicje pól niestandardowych + pliki pól FILE | [docs/examples/custom-fields.md](docs/examples/custom-fields.md) |
| `dms()` | repozytorium plików kontrahenta | [docs/examples/dms.md](docs/examples/dms.md) |
| `generatedDocuments()` | generator dokumentów | [docs/examples/generated-documents.md](docs/examples/generated-documents.md) |
| `mail()` | wysyłka maili, szablony, załączniki | [docs/examples/mail.md](docs/examples/mail.md) |
| `wiki()` | baza wiedzy (bazy, kategorie, wpisy) | [docs/examples/wiki.md](docs/examples/wiki.md) |
| `calendars()` | kalendarze + wydarzenia (API >= 2.2.0) | [docs/examples/calendars.md](docs/examples/calendars.md) |
| `phoneCalls()` | połączenia telefoniczne (API >= 2.10.0) | [docs/.ai/phone-calls/](docs/.ai/phone-calls/README.md) |
| `textMessages()` | wiadomości SMS (API >= 2.10.0) | [docs/.ai/text-messages/](docs/.ai/text-messages/README.md) |
| `lookup()` | kto dzwoni: szukanie po numerze (API >= 2.10.0) | [docs/.ai/lookup/](docs/.ai/lookup/README.md) |
| `integrations()` | konfiguracja Tillio Calls (API >= 2.11.0) | [docs/.ai/integrations/](docs/.ai/integrations/README.md) |

Systemowe na fasadzie: `health()`, `whoami()`, `selfcheck()`, `openapi()`,
`modules()` - [docs/examples/systemowe.md](docs/examples/systemowe.md).

Fasada udostępnia też warstwę surową - przydatną w nietypowych scenariuszach
i wtedy, gdy Twoja instancja API ma trasę nowszą niż to wydanie SDK:
`get()`/`post()`/`put()`/`delete()` (dowolna ścieżka `v2/...`, z retry
i limiterem), `postMultipart()` (upload plików, domyślnie bez retry),
`listPage()` i `iterateAll()` (surowe strony i pełne przebiegi) oraz
`download()` (pobranie pliku spod podpisanego `downloadUrl`).

## Odczyt: strony i pełne przebiegi

Listy zwracają `Page<T>` z typowanymi DTO i metadanymi stronicowania:

```php
$page = $client->contractors()->list(['updatedAfter' => '2026-01-01T00:00:00+00:00', 'limit' => 100]);
$page->total;          // łączna liczba rekordów zapytania
$page->hasNextPage();  // bez ręcznego liczenia ceil(total/limit)
$page->first();        // ?Contractor
```

Do pełnych przebiegów służy generator `iterate()` - przechodzi wszystkie strony,
oddając rekordy po jednym:

```php
foreach ($client->contractors()->iterate(['contractorTypeId' => 1]) as $contractor) {
    // ...
}
```

**`iterate()` domyślnie wymusza `sort=id`** - i to nie jest kosmetyka. API
stronicuje przez LIMIT/OFFSET z domyślnym sortowaniem po dacie modyfikacji,
czyli kolumnie, która ZMIENIA SIĘ w trakcie przebiegu: rekord zmodyfikowany
między stronami wypada z niepobranego zakresu (cicha utrata) albo wraca drugi
raz. `id` jest unikalne i niezmienne, więc tylko ono daje porządek, którego nic
nie przestawi. Jawne `sort` w filtrach wygrywa z wymuszeniem; stany magazynowe
(bez sortowalnego id) nie wymuszają niczego same z siebie.

Każde DTO odczytu ma pełny, surowy rekord w `$raw` / `toArray()` - pola dodane
w nowszych wydaniach API nie giną i nie wywracają SDK. Kwoty są **stringami
dziesiętnymi** (`"1999.90"`) - rzutowanie na float gubi grosze, więc SDK tego
nie robi za Ciebie.

## Zapis: DTO, duplikaty, upsert

Input-DTO z named arguments; `null` znaczy "nie wysyłaj pola":

```php
use TillioCrm\Api\Dto\ContractorInput;
use TillioCrm\Api\Dto\AddressInput;
use TillioCrm\Api\Dto\WriteOptions;

$result = $client->contractors()->create(
    new ContractorInput(
        name: 'Przykładowa Firma Sp. z o.o.',
        alias: 'przykladowa-firma',        // wymagany przy tworzeniu!
        contractorTypeId: 1,
        taxId: '0000000000',
        address: [new AddressInput(addressTypeId: 1, city: 'Warszawa', street: 'Przykładowa 1')],
    ),
    new WriteOptions(duplicateCheck: ['taxId']),
);

$result->created;      // true = utworzono (201); false = trafiono w istniejący (200)
$result->id;           // id rekordu
$result->matchedBy();  // po czym znaleziono duplikat (np. 'taxId', 'custom:erp_id')
$result->warnings;     // ciche korekty normalizacji - czytaj i podnoś wyżej
```

Kluczowe zachowania:

- **POST z `duplicateCheck` nie duplikuje**: trafienie w istniejący rekord to
  200 z tym rekordem, bez zmiany danych - rozróżniaj po `created`.
- **Strażnik lokalny**: każde pole wskazane w `duplicateCheck` musi mieć wartość
  w payloadzie. Bez tego API pominęłoby warunek z samym ostrzeżeniem i założyło
  duplikat - SDK rzuca `IncompleteDuplicateCheckException`, zanim żądanie wyjdzie.
- **Upsert** (`contractors()->upsert()`, `contacts()->upsert()`): HTTP jest
  ZAWSZE 200, wynik per item w `UpsertResult` - sprawdzaj `hasFailures()`,
  kod HTTP nie powie Ci nic.
- **Fallback tablicowy**: metody zapisu przyjmują `Input|array`. Tablica to
  jedyna droga wysłania JAWNEGO nulla (czyszczenie pola, np. `externalId`)
  i pól, których DTO jeszcze nie zna.
- **`users()->create()`** zwraca `CreatedUser` z jednorazowym
  `temporaryPassword` i celowo NIE ponawia żądania - szczegóły w
  [docs/examples/users.md](docs/examples/users.md).

## Obsługa błędów

API zawsze zwraca prawdziwy status HTTP i typowany kontrakt błędu
`{field, code, message}` - SDK zamienia go na hierarchię wyjątków
(wspólny przodek `TillioApiException`):

| Wyjątek | Kiedy | Co robić |
|---|---|---|
| `ValidationException` (400/422) | złe query / złe body, `errors` z kompletem powodów | popraw rekord, leć dalej |
| `FieldUnavailableException` | instancja CRM bez kolumny (stan przejściowy) | zaktualizuj CRM, nie dane |
| `ExternalIdAlreadyUsedException` | klucz integracji zajęty przez inną kartotekę | konflikt do rozstrzygnięcia przez człowieka |
| `AuthenticationException` (401) | zły klucz/tenant | przerwij przebieg, popraw konfigurację |
| `AccessDeniedException` (403) | brak uprawnień klucza | nadaj uprawnienia w CRM |
| `TenantBlockedException` (403) | instancja zablokowana | zgłoś operatorowi |
| `NotFoundException` (404) | nie ma rekordu o tym id | mapowanie id nieaktualne |
| `UserLimitReachedException` (409) | brak wolnej licencji na konto | stan biznesowy - nie ponawiaj |
| `RateLimitException` (429) | limit po stronie API | SDK ponawia sam; niesie `retryAfterSeconds` |
| `MaintenanceException` (503) | przerwa serwisowa | wróć przy następnym harmonogramie |
| `ServiceUnavailableException` (503) | funkcja niedostępna w tej instancji (np. brak modułu kalendarza) | włącz moduł w CRM - retry nic nie da |
| `FeatureNotSupportedException` (501) | trasa istnieje, ale CRM tej instalacji jest za stary na tę funkcję | zaktualizuj CRM - retry nic nie da |
| `ServerException` (5xx) | awaria API/CRM | SDK ponawia sam |
| `ProxyAccessDeniedException` | platforma nie zezwala apce na tę operację | popraw manifest apki, nie klucz |
| `TransportException` | żądanie w ogóle nie dojechało | SDK ponawia sam |

```php
use TillioCrm\Api\Exception\ValidationException;

try {
    $client->contractors()->create($input, $options);
} catch (ValidationException $e) {
    foreach ($e->errors as $error) {
        // $error->field, $error->code, $error->message - wszystkie powody naraz
    }
}
```

Kolejność catchów ma znaczenie: `FieldUnavailableException` i
`ExternalIdAlreadyUsedException` dziedziczą po `ValidationException` - łap je
PRZED nią, jeżeli chcesz je obsłużyć osobno.

## Retry i limity żądań

Wbudowane, bez konfiguracji:

- **Limiter okienkowy** trzyma żądania PONIŻEJ limitów API (429 kosztuje pełny
  round-trip - taniej przytrzymać wątek u siebie). Okna domyślne są celowo
  ciaśniejsze niż limit API (1000/min **per klucz** - tym samym kluczem potrafi
  jechać kilka integracji naraz).
- **Retry z backoffem wykładniczym**: ponawiane 429 (z respektowaniem
  `Retry-After`, z sufitem) i 5xx oraz błędy transportu. NIE ponawiane:
  przerwa serwisowa, 4xx poza 429, odmowa binariów przez proxy oraz operacje
  nieidempotentne (`POST /v2/users`, uploady plików, mail z załącznikami).

Wszystko jest konfigurowalne - konsument pracujący w krótkich porcjach ustawia
krótkie sufity (lepiej oddać porcję i wrócić), skrypt wsadowy może czekać dłużej:

```php
$client = new TillioClient([
    // ...tryb...
    'maxAttempts'    => 2,      // łączna liczba prób (1 = bez retry)
    'retryBaseDelay' => 0.25,   // pierwszy backoff [s]; kolejne x2
    'retryMaxDelay'  => 5.0,    // sufit pojedynczego backoffu [s]
    'maxRetryAfter'  => 10.0,   // sufit dla Retry-After z serwera [s]
    'rateLimits'     => [60 => 120],  // własne okna; [] = bez limitera
]);
```

## Pliki: upload i pobieranie

Upload (DMS, załączniki notatek/zadań, załączniki maili) idzie WYŁĄCZNIE przez
`multipart/form-data` - w API nie ma wariantu JSON. SDK ogarnia to typem
`FileUpload`:

```php
use TillioCrm\Api\Transport\FileUpload;

$doc = $client->dms()->uploadDocument(
    $contractorId,
    FileUpload::fromPath('/sciezka/do/umowa.pdf'),      // albo ::fromString($tresc, 'nazwa.pdf')
    directoryId: $dirId,
);
```

Pobieranie: **API nigdy nie zwraca binariów.** Metadane pliku niosą
`downloadUrl` - podpisany link do storage'u ważny **około 1 minuty**, pobierany
bez nagłówków Tillio:

```php
$document = $client->dms()->getDocument($publicId);  // świeży downloadUrl
$bytes = $client->download($document->downloadUrl);
```

Nie buforuj linku; po wygaśnięciu odczytaj metadane ponownie. `downloadUrl null`
znaczy "środowisko bez podpisywania", nie "brak pliku". Dokumenty DMS adresuje
się WYŁĄCZNIE stringowym `publicId` - pole `id` to referencja CRM, która
w ścieżce nie działa.

## Ograniczenia trybu proxy

- Pobieranie plików DZIAŁA (przez proxy jedzie tylko JSON z `downloadUrl`,
  plik idzie prosto ze storage'u). Gdyby jednak jakaś odpowiedź binarna trafiła
  w proxy, dostaniesz jasny `ProxyBinaryResponseException`, nie ogólne 502.
- Platforma ma allowlistę ścieżek per aplikacja - 403 z proxy to
  `ProxyAccessDeniedException` ("popraw manifest apki"), odróżnione od 403
  z API (uprawnienia klucza w CRM).
- Uploady multipart przechodzą przez proxy w górę normalnie.
- Wybór połączenia przy >1 connectorze: klucz konfiguracji `connector` -
  parametr platformy, dokleja go transport, zasoby go nie widzą.

## Konfiguracja - wszystkie klucze

| Klucz | Tryb | Opis (domyślna) |
|---|---|---|
| `apiKey` | bezpośredni | klucz `nazwa:klucz` tenanta |
| `tenantDomain`, `tenantId` | bezpośredni | tożsamość instancji (nagłówki tenanta) |
| `instanceName` | bezpośredni | nazwa instancji CRM na serwerze wieloinstancyjnym (nagłówek `X-Instance-Name`); pomijasz na produkcji z jedną instancją per adres |
| `accessToken` | proxy | string albo `callable(): string`, rozwiązywane per żądanie |
| `connector` | proxy | publiczny id połączenia CRM przy >1 |
| `server` | oba | adres serwera (prod per tryb) |
| `timeout` / `connectTimeout` | oba | sekundy (30 / 10) |
| `caFile` | oba | ścieżka do CA bundle (CURLOPT_CAINFO) - dla środowisk bez `curl.cainfo`, np. PHP CLI na Windows; weryfikacji TLS nie da się wyłączyć |
| `rateLimits` | oba | okna limitera `[sekundy => maks. żądań]`; `[]` = wyłączony |
| `maxAttempts` | oba | łączna liczba prób żądania (4) |
| `retryBaseDelay` / `retryMaxDelay` | oba | backoff wykładniczy [s] (0.5 / 30) |
| `maxRetryAfter` | oba | sufit dla `Retry-After` [s] (60) |
| `transport` / `clock` | oba | wstrzykiwane w testach |

Nieznany klucz konfiguracji = `ConfigurationException` (literówka nie może po
cichu zamienić się w żądania bez nagłówka).

## Przykłady i integracja przez AI

- **Uruchamialny przykład**: katalog [`examples/`](examples/) - skopiuj
  `config.example.php` do `config.php`, wpisz klucz i odpal
  `php examples/quickstart.php` (odczyt listy + jeden zapis).
- **Przykład na każdy zasób**: [`docs/examples/`](docs/examples/) - 23 pliki,
  wszystkie bloki składniowo sprawdzone.

> Integrujesz przez asystenta AI (Claude, Cursor, Copilot)? Wskaż mu katalog
> [`docs/.ai/`](docs/.ai/). Zaczyna się od
> [`ai_integration.md`](docs/.ai/ai_integration.md) (wspólne wzorce: jak
> rozwiązywać osoby i słowniki na `id`, obsługa błędów, dwa tryby auth),
> a playbooki domenowe (`tasks/`, `contractors/`, `notes/`) pokazują krok po
> kroku, jak zamienić polecenie w języku naturalnym na wywołania SDK - tak,
> żeby "dodaj zadanie dla czterech osób z zespołu" zadziałało bez zgadywania id.

## Testy

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse --memory-limit=1G
```

Zestaw zawiera test kontraktowy (`RouteCoverageTest` - każda z 230 tras mapy
wskazuje istniejącą metodę SDK; pełne porównanie 1:1 ze specyfikacją instancji
włączysz, pobierając `GET /v2/openapi.json` i ustawiając `TILLIO_OPENAPI_FILE`)
oraz test przenośności (zero zależności spoza `TillioCrm\Api` w `src/`).

## Wersjonowanie

Semver, start `0.1.0`; przed `1.0.0` zmiany łamiące = minor. Historia:
[CHANGELOG.md](CHANGELOG.md). Wersję instancji API sprawdzisz przez
`$client->health()['version']`, a jej pełny kontrakt przez `$client->openapi()`.

## Troubleshooting

**Każde żądanie HTTPS pada: `SSL certificate problem`**

PHP CLI (typowo na Windows) nie ma skonfigurowanego `curl.cainfo`. Wskaż plik CA
bundle w konfiguracji: `'caFile' => 'C:/Program Files/Git/mingw64/etc/ssl/certs/ca-bundle.crt'`.
Weryfikacji TLS nie da się wyłączyć przez SDK - można tylko podać właściwy zestaw
zaufanych CA.

**`ValidationException` (400) na liście, choć filtr wygląda dobrze**

API v2 odrzuca NIEZNANY parametr zapytania błędem 400 (literówka w filtrze nie
zwróci po cichu całej bazy). Sprawdź nazwę filtra w PHPDoc metody `list()` danego
zasobu - dokumentuje komplet filtrów wg kontraktu.

**`IncompleteDuplicateCheckException` przed wysłaniem żądania**

To strażnik SDK, nie błąd API: `duplicateCheck` wskazuje pole, które nie ma
wartości w payloadzie. API pominęłoby taki warunek z samym ostrzeżeniem i mogłoby
założyć duplikat - uzupełnij wartość pola albo zdejmij je z `duplicateCheck`.

**403 w trybie proxy: `ProxyAccessDeniedException` czy `AccessDeniedException`?**

`ProxyAccessDeniedException` = platforma nie zezwala aplikacji na tę operację
(popraw manifest apki). `AccessDeniedException` = klucz API nie ma uprawnień
w CRM (nadaj je w panelu). To dwa różne miejsca naprawy.

**`ServiceUnavailableException` (503) na kalendarzach albo szablonach**

Moduł jest wyłączony w planie tej instancji - to nie chwilowa awaria i SDK tego
nie ponawia. Sprawdź `$client->modules()`, zanim zaczniesz pisać w dany moduł.

**Pełny przebieg (`iterate()`) gubi albo dubluje rekordy**

Nie nadpisuj wymuszonego `sort=id` własnym `sort` w filtrach przy pełnym
przejściu - sortowanie po zmiennej kolumnie (np. dacie modyfikacji) przestawia
rekordy między stronami. Zostaw domyślne `sort=id`.

## Wsparcie

- Błędy i propozycje: [GitHub Issues](https://github.com/tillio-crm/api/issues)
- Podatności bezpieczeństwa: zgłaszaj prywatnie wg [SECURITY.md](SECURITY.md),
  nie przez publiczne issue

## Licencja

[MIT](LICENSE) © Tillio CRM
