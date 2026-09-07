# Podłączenie do API: klucz albo proxy

Zanim wykonasz jakiekolwiek polecenie domenowe (zadania, kontrahenci, ...),
musisz zbudować klienta `TillioClient`. Są dwa tryby połączenia. Kod domenowy
jest w obu IDENTYCZNY - różni się tylko konstrukcja klienta. Ten dokument mówi,
który tryb wybrać, jakich danych potrzebujesz i jak potwierdzić, że działa.

## Który tryb

| Sytuacja | Tryb | Klucz konfiguracji |
|---|---|---|
| Skrypt, integracja wewnętrzna, narzędzie z własnym kluczem API tenanta | bezpośredni | `apiKey` |
| Aplikacja marketplace (nie ma i nie może mieć klucza CRM), działa w imieniu użytkownika przez OAuth | proxy | `accessToken` |

Reguła twarda: podajesz DOKŁADNIE JEDEN z `apiKey` / `accessToken`. Brak obu
albo oba naraz to `ConfigurationException` już w konstruktorze (nie przy
pierwszym żądaniu). Jeśli nie wiesz, który tryb ma użytkownik - zapytaj, czy ma
własny klucz API (bezpośredni), czy buduje aplikację marketplace (proxy).

## Tryb bezpośredni (klucz API)

Potrzebujesz trzech rzeczy, wszystkie z panelu Tillio:

- `apiKey` w formacie `nazwa:klucz` - te same klucze co w API v1
  (nazwa integracji, dwukropek, sekret),
- `tenantDomain` - domena instancji (np. `firma.tillio.app`),
- `tenantId` - identyfikator instancji (nagłówek tenanta).

```php
use TillioCrm\Api\TillioClient;

$client = new TillioClient([
    'apiKey'       => 'MojaIntegracja:sekret',   // format nazwa:klucz
    'tenantDomain' => 'firma.tillio.app',
    'tenantId'     => 'firma-abc123',
    // 'server'    => 'https://s2.public.api.tillio.app',  // domyslny SaaS - pomin
]);
```

Format `apiKey` jest walidowany: musi zawierać dwukropek i nie może się nim
zaczynać ani kończyć. Zły format = `ConfigurationException`.

## Tryb proxy (aplikacja marketplace)

Aplikacja marketplace woła platformę WŁASNYM tokenem OAuth, a klucz CRM dokłada
platforma. Nie podajesz `tenantDomain` ani `tenantId` - tożsamość tenanta wynika
z tokenu.

```php
$client = new TillioClient([
    // Token bywa odswiezany w trakcie zycia klienta, dlatego callable -
    // rozwiazywany PRZY KAZDYM zadaniu. To wlasciwy sposob (auto-refresh).
    'accessToken' => fn () => $auth->accessToken(),
    // 'server'    => 'https://apps.tillio.app',   // domyslny prod - pomin
    // 'connector' => '<publicId-polaczenia>',     // TYLKO gdy instalacja ma >1 polaczenie CRM
]);
```

Skąd token: aplikacja loguje użytkownika przez OAuth i trzyma access token.
Najprościej użyć paczki `tillio-crm/oauth-client` - jej metoda `accessToken()`
zwraca ważny token (odświeżając go, gdy wygasa), więc `fn () => $oauth->accessToken()`
załatwia całość. Token musi mieć scope `tillio_client` (to on otwiera proxy do
API v2) i pochodzić z instalacji aktywnej w danym workspace.

`connector` podajesz tylko wtedy, gdy instalacja ma więcej niż jedno połączenie
CRM - wtedy platforma nie wie, do którego kierować. Przy jednym połączeniu pomiń.

## Serwer wieloinstancyjny: `instanceName`

Jeśli adres API hostuje WIELE instancji CRM pod jednym URL (typowo środowiska
deweloperskie), podaj `instanceName` - nazwę instancji (nagłówek `X-Instance-Name`).
Bez niego żądanie trafia na instancję DOMYŚLNĄ serwera, a uwierzytelnienie
kluczem innej instancji kończy się 401 (mimo poprawnego klucza).

```php
$client = new TillioClient([
    'apiKey'       => 'MojaIntegracja:sekret',
    'tenantDomain' => 'firma.tillio.app',
    'tenantId'     => 'firma-abc123',
    'instanceName' => 'firma.tillio.app',   // nazwa instancji na serwerze wieloinstancyjnym
]);
```

Produkcyjny adres z jedną instancją per domena tego NIE wymaga - pomiń klucz.
Objaw braku: `whoami()` zwraca `AuthenticationException` (401) mimo poprawnego
klucza, bo trafiłeś na złą instancję.

## Windows / PHP CLI: `caFile`

Jeśli żądania HTTPS padają na `SSL certificate problem`, PHP nie ma
skonfigurowanego `curl.cainfo` (typowe na Windows w CLI). Wskaż plik CA bundle:

```php
$client = new TillioClient([
    // ...tryb...
    'caFile' => 'C:/Program Files/Git/mingw64/etc/ssl/certs/ca-bundle.crt',
]);
```

Weryfikacji TLS NIE da się przez SDK wyłączyć - `caFile` tylko wskazuje właściwy
zestaw zaufanych CA.

## Potwierdź, że połączenie działa

Zanim ruszysz z zapisami, sprawdź konfigurację najtańszym żądaniem:

```php
// whoami() - kim jestesmy wg API (dziala w obu trybach).
$who = $client->whoami();
// ['authenticated' => true, 'tenantDomain' => ..., 'tenantId' => ..., 'keyName' => ..., 'userId' => ...]

// health() - wersja instancji, BEZ uwierzytelnienia (trasa publiczna).
$version = $client->health()['version'];   // np. "2.4.0"
```

`health()` jest publiczne i działa nawet w przerwie serwisowej - użyj go, żeby
sprawdzić, czy instancja ma wersję API wymaganą przez funkcje, których chcesz
użyć (np. kalendarze wymagają >= 2.2.0, szablony >= 2.4.0). Jeśli `whoami()`
rzuci `AuthenticationException` (401) - klucz albo token jest zły; przerwij
i popraw konfigurację, nie ponawiaj.

## Pułapki

- **Dokładnie jeden tryb.** `apiKey` i `accessToken` naraz (albo żaden) =
  `ConfigurationException` w konstruktorze.
- **`tenantDomain`/`tenantId` tylko w trybie bezpośrednim.** W proxy ich podanie
  to błąd - tożsamość tenanta dokłada platforma.
- **`connector` tylko w proxy.** W trybie bezpośrednim nie ma połączeń do wyboru.
- **Token jako callable, nie string.** String zadziała, ale nie odświeży się sam;
  w realnej aplikacji zawsze callable.
- **Nieznany klucz konfiguracji = `ConfigurationException`.** Literówka w nazwie
  klucza (np. `tenantDomian`) nie przejdzie po cichu.
