# Tillio API v2 SDK - przewodnik dla asystentów AI

Ten katalog jest pisany DLA CIEBIE - asystenta AI, który realizuje polecenie
użytkownika w kodzie PHP przy użyciu paczki `tillio-crm/api`. Użytkownik zwykle
mówi intencją ("dodaj zadanie dla czterech osób z zespołu", "zapisz notatkę
u kontrahenta Acme"), a Twoim zadaniem jest zamienić to na poprawne wywołania
SDK. Ten przewodnik i playbooki w podkatalogach mówią, jak to zrobić bez zgadywania.

## Jak korzystać z tego katalogu

1. **Podłącz klienta** - [connecting/README.md](connecting/README.md) mówi, jak
   zbudować `TillioClient` przez klucz API albo przez proxy OAuth i jak
   potwierdzić, że działa. Bez tego nic nie ruszy.
2. **Poznaj zasady zapytań** - [queries/README.md](queries/README.md) mówi, jak
   czytać dane tanio i bez gubienia rekordów (filtry, paginacja, `iterate`,
   `sort=id`). Obowiązują we wszystkich playbookach.
3. Rozpoznaj DOMENĘ polecenia (zadania, kontrahenci, notatki, ...) i otwórz jej
   playbook (spis niżej). Playbook opisuje krok po kroku, jakich danych API
   wymaga, skąd je wziąć i podaje gotowy, komentowany kod PHP.
4. Zanim cokolwiek zapiszesz, ZBIERZ IDENTYFIKATORY przez odczyt (patrz "Złota
   zasada"). API nie przyjmuje nazwisk ani nazw - przyjmuje liczbowe `id`.
5. Po zapisie zwróć użytkownikowi zwięzłe potwierdzenie z tym, co powstało
   (id rekordu, po czym rozpoznano duplikat itd.).

## Złota zasada: nie zgaduj id, odpytaj końcówkę

To najważniejsza reguła całej integracji. Użytkownik mówi ludzkim językiem
("Jan Kowalski", "status W toku", "kontrahent Acme"), a API operuje na `id`.
NIGDY nie wstawiaj wymyślonego `id`. Zawsze rozwiąż nazwę na `id` przez odczyt:

- osoba (imię i nazwisko) -> `userId`: przez `users()->list([...])`,
- pozycja słownika (nazwa statusu, typu, priorytetu) -> `id`: przez odpowiedni
  słownik w `dictionaries()`,
- kontrahent (nazwa/NIP) -> `contractorId`: przez `contractors()->list([...])`.

Jeśli odczyt zwróci zero trafień albo więcej niż jedno - nie zgaduj. Dopytaj
użytkownika (patrz "Rozwiązywanie osób i słowników" niżej).

## Dwa tryby połączenia (skrót)

Pełna instrukcja podłączenia, weryfikacji i pułapek:
[connecting/README.md](connecting/README.md). Skrót - kod domenowy jest
IDENTYCZNY w obu trybach, różni się tylko konstrukcja klienta.

```php
use TillioCrm\Api\TillioClient;

// Tryb bezpośredni: własny klucz API tenanta (skrypty, integracje wewnętrzne).
$client = new TillioClient([
    'apiKey'       => 'MojaIntegracja:sekret',   // format nazwa:klucz
    'tenantDomain' => 'firma.tillio.app',
    'tenantId'     => 'firma-abc123',
]);

// Tryb proxy: aplikacja marketplace, token OAuth zamiast klucza CRM.
$client = new TillioClient([
    'accessToken' => fn () => $auth->accessToken(),  // callable = auto-refresh
]);
```

Szczegóły i pełna lista kluczy konfiguracji: główny [README](../../README.md).
Na Windowsie w PHP CLI bez skonfigurowanego `curl.cainfo` dodaj
`'caFile' => '<sciezka-do-ca-bundle.crt>'`, inaczej każde żądanie HTTPS padnie
na weryfikacji TLS.

## Wspólne wzorce (używane w każdym playbooku)

### Rozwiązywanie osoby (imię i nazwisko) na userId

Filtry listy użytkowników: `firstName`, `lastName`, `email` (LOGIN),
`userStatusId`, a od API 2.16.0 też `jobTitle`, `contactPhone`, `contactEmail`
i `gender`. Najpewniejszy jest `email` (unikalny); po nazwisku bywa kilka trafień.

```php
/**
 * Zwraca id użytkownika po pełnym imieniu i nazwisku.
 * Rzuca wyjątek przy zeru albo wielu trafieniach - wtedy trzeba dopytać
 * użytkownika, a nie zgadywać. To celowe: cichy zły wybór wykonawcy jest
 * gorszy niż pytanie.
 *
 * @throws RuntimeException gdy osoby nie ma albo jest niejednoznaczna
 */
function resolveUserId(TillioCrm\Api\TillioClient $client, string $firstName, string $lastName): int
{
    $matches = [];
    // Filtrujemy po nazwisku (mniej trafień niż po imieniu), imię dopasowujemy
    // lokalnie bez rozróżniania wielkości liter.
    foreach ($client->users()->list(['lastName' => $lastName, 'limit' => 50]) as $user) {
        if (mb_strtolower((string) $user->firstName) === mb_strtolower($firstName)) {
            $matches[] = $user;
        }
    }

    if (count($matches) === 1) {
        return $matches[0]->id;
    }
    if ($matches === []) {
        throw new RuntimeException("Nie znaleziono uzytkownika: $firstName $lastName. Dopytaj uzytkownika o dane albo e-mail.");
    }
    // Kilka osob o tym samym imieniu i nazwisku - popros uzytkownika o wskazanie
    // po e-mailu; NIE wybieraj pierwszej z brzegu.
    throw new RuntimeException("Wiele osob o nazwisku $lastName - dopytaj o e-mail, zeby wskazac wlasciwa.");
}
```

Wariant pewny, gdy użytkownik podał e-mail:

```php
$page = $client->users()->list(['email' => 'jan.kowalski@przyklad.example', 'limit' => 1]);
$user = $page->first();   // ?SystemUser
```

### Rozwiązywanie pozycji słownika (nazwa) na id

Słowniki (`dictionaries()`) zwracają listy `{id, name, ...}`. Zmapuj nazwę
podaną przez użytkownika na `id`, dopasowując bez rozróżniania wielkości liter.

```php
/** Zwraca id pozycji słownika po nazwie, albo null gdy brak dopasowania. */
function findByName(array $entries, string $name): ?int
{
    foreach ($entries as $entry) {
        if (mb_strtolower((string) $entry->name) === mb_strtolower($name)) {
            return $entry->id;
        }
    }
    return null;
}

// np. status zadania "W toku" -> taskStatusId
$statusId = findByName($client->dictionaries()->taskStatuses(), 'W toku');
```

### "Jakie pola przyjmuje ten zasób" - sprawdź w input-DTO

Każdy zapis ma typowane input-DTO w `src/Dto/*Input.php` z named arguments.
Nazwy argumentów = nazwy pól kontraktu; `null` = nie wysyłaj pola. To jest
źródło prawdy o tym, co można ustawić. Pola wymagane przy tworzeniu są opisane
w PHPDoc DTO i w metodzie `create()` danego zasobu.

### Co zwracają zapisy - `WriteResult`

Większość zapisów (`create()`, `update()`, `addAddress()`, ...) zwraca
`WriteResult`. Najważniejsze pola i metody:

- `->id` (`?int`) - id zapisanego (albo znalezionego) rekordu,
- `->created` (`bool`) - `true` = utworzono (HTTP 201), `false` = trafiono
  w istniejący rekord (HTTP 200, przy `duplicateCheck`; leady od API 2.13.0
  szukają istniejącego rekordu domyślnie),
- `->isDuplicate()` (`bool`) - czy to trafienie w duplikat, nie kreacja,
- `->matchedBy()` (`?string`) - po którym polu dopasowano duplikat,
- `->warnings` (`array`) - ciche korekty normalizacji; czytaj je i pokaż
  użytkownikowi, jeśli są.

Wyjątki od `WriteResult`: `users()->create()` zwraca `CreatedUser` (z hasłem
startowym), `stocks()->update()` zwraca `Stock`, część odczytów zwraca DTO
wprost. Zawsze potwierdź typ zwrotny w sygnaturze metody zasobu.

### Przejścia statusów robi się dedykowaną metodą, nie zapisem pola

Tam, gdzie CRM prowadzi historię, API ma osobną trasę i zwykły `update()` odbija
takie pole błędem 422 `body.fieldNotUpdatable`. Nie kombinuj z PUT - użyj metody:

| Chcesz | Metoda (API >= 2.15.0) | Zamiast |
|---|---|---|
| zmienić status leada (kwalifikacja, dyskwalifikacja, powrót) | `leads()->changeStatus($id, $leadStatusId, $reasonId, $note)` | `leads()->update()` z `leadStatusId` |
| przesunąć szansę na inny etap | `pipelineItems()->changeStage($id, $stageId)` | `pipelineItems()->update()` z `pipelineStageId` |
| zamknąć szansę (wygrana/stracona) albo otworzyć ponownie | `pipelineItems()->changeStatus($id, $statusId, $reasonId, $note)` | `pipelineItems()->update()` z `pipelineStatusId` |

Powód zmiany (`statusChangeReasonId`) bierzesz ze słownika DOPASOWANEGO do
docelowego statusu: `dictionaries()->leadStatusChangeReasons($leadStatusId)` albo
`dictionaries()->pipelineStatusChangeReasons($statusId, $funnelId)`. Sprawdź
`noteRequired` - przy takim powodzie notatka jest obowiązkowa, a bez niej API
odrzuca zapis. Powód i notatka mają sens WYŁĄCZNIE przy statusach kończących;
przy zwykłym przejściu (lead) i przy ponownym otwarciu szansy oba dają 422.

### Własny klucz integracji: pole niestandardowe plus filtr

Zapisy CRM nie są idempotentne - powtórzony `create()` zakłada drugi rekord.
Wykrywanie duplikatu ma tylko część zasobów (kontrahenci, kontakty, produkty,
leady - przez `WriteOptions`), a szansa sprzedaży nie ma go wcale i nie przyjmuje
`externalId` w zapisie (to pole wyłącznie odczytu i filtra). Wzorzec, który
działa wszędzie: trzymaj SWÓJ identyfikator w polu niestandardowym i sprawdź go
filtrem PRZED zapisem.

```php
// Klucz po stronie integracji (np. id zgłoszenia z formularza albo z ERP).
$externalKey = 'ZAP-1042';

// Krok 1: czy taki rekord już jest? customField[<klucz>] filtruje dokładnie.
$existing = $client->pipelineItems()->list([
    'customField' => ['zapier_id' => $externalKey],
    'limit'       => 1,
])->first();

// Krok 2: zapis albo aktualizacja - nigdy "na ślepo" drugi create().
if ($existing === null) {
    $client->pipelineItems()->create(new TillioCrm\Api\Dto\PipelineItemInput(
        name: 'Zapytanie z formularza',
        pipelineStageId: $stageId,
        contractorId: $contractorId,
        customField: ['zapier_id' => $externalKey],
    ));
} else {
    $client->pipelineItems()->update($existing->id, ['amount' => '12000.00']);
}
```

Dla leadów i kontrahentów ten sam klucz podaje się dodatkowo jako
`duplicateCheck: ['custom:zapier_id']` - wtedy sprawdzenie robi API w jednym
żądaniu, a SDK pilnuje lokalnie, że wartość klucza faktycznie jest w payloadzie.

### Normalizacja wejścia: co API poprawia, a co odrzuca

API sprowadza dane do kanonu CRM po swojej stronie - nie normalizuj ich sam
przed wysyłką. Wartość, której nie da się uratować, NIE zapisuje się: pole jest
pomijane, a oryginał wraca w `WriteResult::$warnings` pod nazwą tego pola.
Reszta rekordu powstaje normalnie, więc **cisza w kodzie nie znaczy, że komplet
danych wszedł** - czytaj ostrzeżenia i pokazuj je użytkownikowi.

| Dane | Co robi API | Kiedy odrzuca (ostrzeżenie zamiast zapisu) |
|---|---|---|
| telefon | sprowadza do E.164 przez libphonenumber (9 cyfr = `+48...`, prefiks kraju bez plusa rozpoznawany, stary zapis stacjonarny z zerem wiodącym też) | numer niepoprawny dla swojego kraju: za krótki, za długi, z doklejonym numerem wewnętrznym ("600 100 300 w. 12"), numer testowy spoza planu numeracji. W `lookup()->phone()` taki numer to 422 |
| NIP | bez prefiksu kraju = polski (10 cyfr, separatory zdejmowane, prefiks `PL` pomijany); z prefiksem kraju z listy (cała Europa z GB, CH i NO oraz US, CA, AU, NZ) sprawdzany co do formatu tego kraju i zapisywany z prefiksem; kraj spoza listy - jak podano, bez spacji na końcach | zła długość polskiego NIP-u albo format niezgodny z krajem prefiksu |
| REGON, PESEL | zapisywane bez walidacji formatu | nigdy |
| e-mail, domena, URL | walidowane; e-mail przyjmuje domeny z narodowymi znakami, domena przyjmuje też URL i e-mail (część po `@`) | wartość niepoprawna |
| tekst | wycina tagi HTML i znaki sterujące, ciągi spacji skleja do jednej, łamania linii zostają | nigdy (co najwyżej zostaje krótszy tekst) |
| HTML (treść notatki, szablon notatki, opisy zgłoszeń, zadań, projektów, notatki leada i szansy) | czyści do bezpiecznego podzbioru jak edytor CRM: bez skryptów, iframe, zdarzeń i osadzonych obrazków, linki dostają `target=_blank` | treść pusta PO wycięciu - pole pomijane z ostrzeżeniem |

Konsekwencje, o których warto pamiętać:

- **Numery w danych testowych i przykładach muszą być prawdziwe składniowo.**
  `+48000000000` nie jest poprawnym numerem polskim i nie zapisze się.
- **Do `duplicateCheck` wysyłaj dane tak, jak je masz.** Porównanie jest odporne
  na format (telefon z plusem i bez, z zerem wiodącym, domena z `www.`, NIP
  z `PL`, wielkość liter w e-mailu) i trafia też rekordy zapisane w CRM w starym
  formacie.
- **Ostrzeżenie to nie błąd.** Rekord powstaje, zapis zwraca 200/201 - jedyny
  ślad po pominiętym polu jest w `->warnings`.

### Helpery w playbookach nie są częścią SDK

Funkcje `resolveUserId()` i `findByName()` z tego pliku to WZORCE do wklejenia
do swojego kodu, nie metody paczki. Playbooki się do nich odwołują - pamiętaj,
żeby dołączyć ich definicje albo wpleść logikę bezpośrednio.

### Obsługa błędów

Wszystkie błędy dziedziczą po `TillioApiException`. Reaguj według typu:

- `ValidationException` (400/422) - `->errors` niesie komplet powodów
  `{field, code, message}`. Popraw dane i powtórz; nie ponawiaj bez zmiany.
- `ApiException` ze statusem 413 (`body.tooLarge`, `body.tooComplex`) - żądanie
  za duże: body JSON ponad 2 MB albo ponad 20 000 obiektów i tablic. Podziel dane
  na mniejsze żądania; powtórka tego samego nic nie da. Paczki upsert do 100
  pozycji mieszczą się z zapasem.
- `IncompleteDuplicateCheckException` - SDK zatrzymał zapis LOKALNIE, bo pole
  z `duplicateCheck` nie miało wartości. Uzupełnij albo zdejmij pole.
- `NotFoundException` (404) - złe id (mapowanie nieaktualne).
- `RateLimitException` / `ServerException` / `TransportException` - SDK ponawia
  je sam; jeśli i tak doleciały, odpuść i zgłoś użytkownikowi.
- `ServiceUnavailableException` (503 z kodem) - moduł wyłączony w tej instancji;
  sprawdź `$client->modules()`, nie ponawiaj.
- `FeatureNotSupportedException` (501) - trasa istnieje, ale CRM tej instalacji
  jest za stary na tę funkcję (np. kontakty przy notatce, pola wielowartościowe).
  Stan deterministyczny - nie ponawiaj, zaktualizuj CRM. Odróżnij od 503 (moduł
  wyłączony w planie).

## Spis dokumentów

Fundamenty (przeczytaj zanim zaczniesz):

| Dokument | O czym |
|---|---|
| [connecting/README.md](connecting/README.md) | Podłączenie klienta: klucz API albo proxy OAuth, weryfikacja, pułapki |
| [queries/README.md](queries/README.md) | Wydajne i poprawne zapytania: filtry, paginacja, `iterate`, `sort=id` |
| [custom-fields/README.md](custom-fields/README.md) | Pola niestandardowe: definicje, wartości na rekordzie, typy, pola FILE |
| [files/README.md](files/README.md) | Pliki: DMS, załączniki, pola FILE, upload multipart, pobieranie przez `downloadUrl` |

Playbooki domenowe (każdy ma tabelę wszystkich pól z opisem "po co", scenariusz
z gotowym kodem, warianty i pułapki):

| Domena | Playbook | Typowe polecenia użytkownika |
|---|---|---|
| Zadania | [tasks/README.md](tasks/README.md) | "dodaj zadanie dla X, Y i Z", "przydziel zadanie z terminem" |
| Kontrahenci | [contractors/README.md](contractors/README.md) | "dodaj kontrahenta", "znajdź firmę po NIP", "zaktualizuj adres" |
| Kontakty | [contacts/README.md](contacts/README.md) | "dodaj osobę kontaktową", "podepnij kontakt do firmy" |
| Notatki | [notes/README.md](notes/README.md) | "zapisz notatkę u kontrahenta", "dodaj notatkę z załącznikiem" |
| Leady | [leads/README.md](leads/README.md) | "dodaj leada", "zarejestruj zapytanie z formularza bez dubla", "dopisz notatkę do leada", "zakwalifikuj leada" |
| Szanse sprzedaży | [pipeline-items/README.md](pipeline-items/README.md) | "dodaj szansę w lejku", "przesuń na etap", "oznacz jako wygraną" |
| Zgłoszenia | [tickets/README.md](tickets/README.md) | "utwórz zgłoszenie", "dopisz wiadomość do ticketa" |
| Projekty | [projects/README.md](projects/README.md) | "załóż projekt dla klienta" |
| Usługi | [services/README.md](services/README.md) | "dodaj usługę kontrahentowi z katalogu" |
| Katalog usług | [service-catalog/README.md](service-catalog/README.md) | "dodaj pozycję do katalogu usług" |
| Produkty | [products/README.md](products/README.md) | "dodaj produkt", "znajdź po SKU/EAN" |
| Grupy produktów | [product-groups/README.md](product-groups/README.md) | "utwórz grupę produktów" |
| Magazyny | [warehouses/README.md](warehouses/README.md) | "dodaj magazyn" |
| Stany magazynowe | [stocks/README.md](stocks/README.md) | "zmień stan", "skoryguj ilość o -2" |
| Zamówienia | [orders/README.md](orders/README.md) | "utwórz zamówienie dla kontrahenta" |
| Kalendarze | [calendars/README.md](calendars/README.md) | "wpisz spotkanie handlowcowi", "pokaż wydarzenia" |
| Dokumenty | [generated-documents/README.md](generated-documents/README.md) | "wygeneruj ofertę z szablonu" |
| Użytkownicy | [users/README.md](users/README.md) | "załóż konto pracownikowi" |
| Słowniki | [dictionaries/README.md](dictionaries/README.md) | "znajdź id statusu/typu po nazwie", "dodaj wpis słownika" |
| Poczta | [mail/README.md](mail/README.md) | "wyślij mail z załącznikiem", "użyj szablonu" |
| Połączenia | [phone-calls/README.md](phone-calls/README.md) | "zapisz połączenie telefoniczne", "historia rozmów kontrahenta" |
| SMS | [text-messages/README.md](text-messages/README.md) | "wyślij SMS-a do klienta", "zapisz wiadomość SMS" |
| Lookup po numerze i adresie | [lookup/README.md](lookup/README.md) | "kto dzwoni z tego numeru", "znajdź kontakt po telefonie", "kto pisze z tego adresu" |
| Integracje | [integrations/README.md](integrations/README.md) | "sprawdź dostępne integracje", "dane integracji instancji" |
| Baza wiedzy | [wiki/README.md](wiki/README.md) | "dodaj wpis do wiki" |

Pełne przykłady na każdy zasób (składniowo sprawdzone): katalog
[docs/examples/](../examples/). Kontrakt i lista tras: `$client->openapi()`
oraz `$client->health()['version']` (wersja instancji).
