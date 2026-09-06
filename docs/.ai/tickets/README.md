# Playbook: Zgloszenia (tickets)

Realizacja polecen uzytkownika dotyczacych zgloszen (ticketow): zakladanie,
przypisywanie opiekuna, powiazania z kontrahentem i usluga oraz prowadzenie
watku wiadomosci (odpowiedzi publiczne i notatki wewnetrzne). Pisane dla
asystenta AI - zaklada wspolne wzorce z
[ai_integration.md](../ai_integration.md) (zwlaszcza `resolveUserId()`,
`findByName()`, `WriteResult` oraz "Zlota zasada: nie zgaduj id").

## Model danych w skrocie

Zgloszenie tworzy sie przez `TicketInput`. Przy tworzeniu API WYMAGA tylko
`title` (string). Reszta jest opcjonalna. Wiadomosc w watku tworzy sie przez
`TicketMessageInput` i WYMAGA `text`; jej `visibility` to `public` (widoczna
dla klienta) albo `internal` (notatka wewnetrzna zespolu).

Klluczowa konsekwencja: status, etap i zrodlo zgloszenia (`ticketStatusId`,
`ticketStageId`, `ticketSourceId`) API przyjmuje TYLKO przy tworzeniu. Po
utworzeniu zmienia je proces obslugi zgloszen, a nie goly `update()` (PUT te
pola odrzuca). Etap (`ticketStageId`) to etap w konkretnym procesie - liste
procesow z ich etapami daje `dictionaries()->ticketProcesses()`.

Pelna lista pol input-DTO: `src/Dto/TicketInput.php` i
`src/Dto/TicketMessageInput.php`.

## Pola

### `TicketInput` (tworzenie i aktualizacja zgloszenia)

| pole | typ | po co |
|---|---|---|
| `title` | string | tytul zgloszenia. WYMAGANE przy tworzeniu. |
| `description` | string | tresc/opis zgloszenia. |
| `priority` | int | priorytet. Kontrakt nie definiuje skali - jesli nie znasz mapowania tej instancji, pomin albo dopytaj, nie zgaduj liczby. |
| `ownerUserId` | int | opiekun (osoba prowadzaca zgloszenie). Id przez `resolveUserId()`. |
| `email` | string | adres nadawcy spoza CRM - pozwala powiazac zgloszenie z osoba bez kartoteki. |
| `contractorId` | int | kontrahent, ktorego dotyczy zgloszenie. Id przez `contractors()->list(['name' => ...])`. |
| `contactId` | int | osoba kontaktowa u kontrahenta. Id przez `contacts()` danego kontrahenta. |
| `serviceId` | int | usluga powiazana ze zgloszeniem. Id przez `services()->list([...])` (patrz playbook uslug). |
| `resolutionAt` | string | termin rozwiazania (SLA), ISO 8601 z offsetem strefy. |
| `endedAt` | string | data zamkniecia zgloszenia, ISO 8601. |
| `open` | bool | czy zgloszenie jest otwarte. |
| `ticketStatusId` | int | status zgloszenia. TYLKO przy tworzeniu. Id przez `dictionaries()->ticketStatuses()`. |
| `ticketStageId` | int | etap w procesie obslugi. TYLKO przy tworzeniu. Id ze `stages` procesu z `dictionaries()->ticketProcesses()`. |
| `ticketSourceId` | int | zrodlo zgloszenia (np. e-mail, telefon). TYLKO przy tworzeniu. Id przez `dictionaries()->ticketSources()`. |
| `createClientPanel` | bool | zaloz panel klienta dla tego zgloszenia. TYLKO przy tworzeniu. |
| `customField` | array | wartosci pol niestandardowych (klucz => wartosc). Definicje przez `customFields()`. |
| `createdAt` | string | data utworzenia przy imporcie historycznym, ISO 8601. |
| `creatorUserId` | int | autor zgloszenia. TYLKO przy tworzeniu. Id przez `resolveUserId()`. |

### `TicketMessageInput` (wiadomosc w watku)

| pole | typ | po co |
|---|---|---|
| `text` | string | tresc wiadomosci. WYMAGANE. |
| `visibility` | 'public' \| 'internal' | `public` = widoczna dla klienta, `internal` = notatka wewnetrzna zespolu (wymaga nowszej wersji CRM, patrz Pulapki). |
| `date` | string | data wiadomosci przy imporcie historycznym, ISO 8601. |
| `creatorUserId` | int | autor wiadomosci (domyslnie uzytkownik klucza API). Id przez `resolveUserId()`. |
| `subject` | string | temat wiadomosci (np. dla korespondencji e-mail). |
| `fromName` | string | nazwa nadawcy (dla wiadomosci przychodzacej spoza CRM). |
| `fromEmail` | string | e-mail nadawcy. |
| `contactId` | int | osoba kontaktowa powiazana z wiadomoscia. |
| `replyToMessageId` | int | id wiadomosci, na ktora to jest odpowiedz. |

### Istotne pola odczytu (`Ticket`)

| pole | typ | po co |
|---|---|---|
| `id` | int | id zgloszenia - do watku wiadomosci i dalszych operacji. |
| `ticketStageId` | int | biezacy etap w procesie obslugi. |
| `open` / `archived` | bool | czy otwarte / zarchiwizowane (starsze instancje oddawaly "0"/"1" - `Cast::bool` to normalizuje). |
| `estimatedTime` | int | szacowany czas (ustawiany po stronie CRM, `TicketInput` go nie przyjmuje). |
| `uuId` | string | zewnetrzny identyfikator zgloszenia wg kontraktu (pole `uuId`). |
| `clientPanelUrl` | string | link do panelu klienta, jesli zalozony. |
| `lastResponseAt` | string | data ostatniej odpowiedzi. |
| `url` | string | link do zgloszenia w CRM. |

## Mapowanie intencji uzytkownika na dane API

| Uzytkownik mowi | Potrzebujesz | Skad wziac |
|---|---|---|
| "zgloszenie 'Nie dziala logowanie'" | `title` | wprost z polecenia |
| "od kontrahenta Acme" | `contractorId` | `contractors()->list(['name' => 'Acme'])` |
| "opiekun Jan Kowalski" / "przypisz Janowi" | `ownerUserId` | `resolveUserId($client, 'Jan', 'Kowalski')` |
| "status Nowe" | `ticketStatusId` | `findByName($client->dictionaries()->ticketStatuses(), 'Nowe')` (tylko przy tworzeniu) |
| "etap Diagnoza" | `ticketStageId` | ze `stages` procesu z `dictionaries()->ticketProcesses()` (tylko przy tworzeniu) |
| "z e-maila" / "zrodlo telefon" | `ticketSourceId` | `findByName($client->dictionaries()->ticketSources(), 'Telefon')` |
| "dotyczy uslugi X" | `serviceId` | `services()->list([...])` (patrz playbook uslug) |
| "odpisz klientowi: ..." | wiadomosc `public` | `addMessage($ticketId, new TicketMessageInput(text: ..., visibility: 'public'))` |
| "dopisz wewnetrzna notatke: ..." | wiadomosc `internal` | `addMessage($ticketId, new TicketMessageInput(text: ..., visibility: 'internal'))` |

## Scenariusz flagowy: zgloszenie od kontrahenta z notatka wewnetrzna

Polecenie uzytkownika: *"Zaloz zgloszenie 'Nie dziala logowanie' dla
kontrahenta Acme, opiekun Jan Kowalski, i dopisz wewnetrzna notatke, ze klient
zglosil to telefonicznie."*

Twoj tok postepowania:

1. Wyluskaj tytul, kontrahenta, opiekuna oraz tresc notatki.
2. Rozwiaz kontrahenta na `contractorId` i opiekuna na `ownerUserId`; jesli
   ktorykolwiek jest zerowy albo niejednoznaczny - PRZERWIJ i dopytaj.
3. Utworz zgloszenie (`title` wymagane).
4. Dodaj wiadomosc `internal` do watku nowego zgloszenia.
5. Zwroc potwierdzenie z id zgloszenia i id wiadomosci.

```php
use TillioCrm\Api\Dto\TicketInput;
use TillioCrm\Api\Dto\TicketMessageInput;

// Krok 1: dane z polecenia (Ty je wyluskujesz z tekstu uzytkownika).
$title = 'Nie dziala logowanie';
$note  = 'Klient zglosil to telefonicznie.';

// Krok 2: rozwiaz kontrahenta i opiekuna na id - nie zgaduj.
$contractor = $client->contractors()->list(['name' => 'Acme', 'limit' => 1])->first();
if ($contractor === null) {
    throw new RuntimeException('Nie znaleziono kontrahenta Acme - dopytaj albo zaloz kartoteke.');
}

// resolveUserId() z ai_integration.md: rzuca przy zeru/wielu trafieniach.
$ownerUserId = resolveUserId($client, 'Jan', 'Kowalski');

// Krok 3: utworzenie zgloszenia. Wymagane jest tylko title; reszta to powiazania.
// create() zwraca WriteResult: ->id (id zgloszenia), ->created, ->warnings.
$ticket = $client->tickets()->create(new TicketInput(
    title: $title,
    contractorId: $contractor->id,
    ownerUserId: $ownerUserId,
));

// Krok 4: notatka wewnetrzna w watku. visibility 'internal' wymaga nowszej
// wersji CRM - starsza instancja odpowie 422 z podpowiedzia, zeby uzyc 'public'
// (patrz Warianty: bezpieczny fallback).
$message = $client->tickets()->addMessage($ticket->id, new TicketMessageInput(
    text: $note,
    visibility: 'internal',
));

// Krok 5: potwierdzenie dla uzytkownika.
echo "Utworzono zgloszenie #{$ticket->id} dla {$contractor->name}, notatka wewnetrzna #{$message->id}.\n";
```

Co zwrocic uzytkownikowi: numer zgloszenia (`$ticket->id`), kontrahenta i opiekuna
oraz to, ze notatka jest wewnetrzna. `WriteResult` niesie tez `->warnings` (ciche
korekty normalizacji) - jesli niepuste, pokaz je uzytkownikowi.

## Warianty

### Odczyt watku wiadomosci

```php
// Lista wiadomosci zgloszenia (bez stronicowania).
$messages = $client->tickets()->messages($ticketId);   // list<TicketMessage>
foreach ($messages as $m) {
    // $m->visibility to 'public' albo 'internal' - odfiltruj, co pokazujesz.
    echo "[{$m->visibility}] {$m->text}\n";
}
```

### Odpowiedz publiczna dla klienta

```php
$client->tickets()->addMessage($ticketId, new TicketMessageInput(
    text: 'Dziekujemy za zgloszenie, juz to sprawdzamy.',
    visibility: 'public',
));
```

### Bezpieczny fallback internal -> public

Jesli instancja nie obsluguje komentarzy wewnetrznych, `internal` zwroci 422.
Gdy notatka i tak ma trafic do watku, mozna sprobowac `public` po odmowie -
ale tylko jesli jestes pewien, ze tresc moze zobaczyc klient:

```php
use TillioCrm\Api\Exception\ValidationException;

try {
    $client->tickets()->addMessage($ticketId, new TicketMessageInput(
        text: $note,
        visibility: 'internal',
    ));
} catch (ValidationException $e) {
    // Instancja bez komentarzy wewnetrznych. NIE przelaczaj cicho na public,
    // jesli tresc jest wrazliwa - dopytaj uzytkownika, zanim upubliczisz.
    throw new RuntimeException('Ta instancja nie obsluguje notatek wewnetrznych - potwierdz, czy tresc moze byc publiczna.');
}
```

### Etap zgloszenia z procesu obslugi

Etap (`ticketStageId`) nalezy do konkretnego procesu - najpierw znajdz proces,
potem jego etap. Ustawiasz go TYLKO przy tworzeniu:

```php
// Znajdz proces po nazwie i jego etap po nazwie.
$stageId = null;
foreach ($client->dictionaries()->ticketProcesses() as $process) {
    if (mb_strtolower((string) $process->name) === mb_strtolower('Obsluga standardowa')) {
        $stageId = findByName($process->stages, 'Diagnoza');   // stages to list<ProcessStage>
        break;
    }
}
if ($stageId === null) {
    throw new RuntimeException('Nie znaleziono procesu/etapu - dopytaj uzytkownika.');
}

$client->tickets()->create(new TicketInput(
    title: 'Awaria serwera',
    ticketStageId: $stageId,   // tylko przy tworzeniu; pozniej etap zmienia proces
));
```

## Pulapki

- **`title` jest wymagane.** Zgloszenie bez tytulu = 422.
- **Status/etap/zrodlo tylko przy tworzeniu.** `ticketStatusId`, `ticketStageId`,
  `ticketSourceId`, `createClientPanel`, `creatorUserId` PUT odrzuca. Po
  utworzeniu status i etap zmienia proces obslugi zgloszen, nie `update()`.
- **`internal` wymaga nowszej wersji CRM.** Starsza instancja odrzuci wiadomosc
  wewnetrzna z 422 i podpowiedzia, zeby uzyc `public`. Nie przelaczaj cicho na
  `public`, jesli tresc jest wrazliwa - dopytaj (patrz Warianty).
- **`ticketStageId` to etap w procesie, nie globalny slownik.** Bierz go ze
  `stages` procesu z `dictionaries()->ticketProcesses()`, nie zgaduj liczby.
- **Nie zgaduj `ownerUserId`.** Kilku pracownikow moze miec to samo nazwisko -
  `resolveUserId()` celowo rzuca przy wielu trafieniach. Dopytaj o e-mail.
- **`priority` bez zdefiniowanej skali.** Kontrakt nie mowi, co znaczy dana
  liczba - jesli nie znasz mapowania tej instancji, pomin pole albo dopytaj.
- **`open`/`archived` w odczycie bywaly stringami** ("0"/"1") na instancjach
  2.0.0-2.0.3; `Cast::bool` oba warianty normalizuje do bool.
- **`messages()` nie stronicuje** - zwraca caly watek naraz.
- **Odczyt vs zapis**: `Ticket` (odczyt) ma pola, ktorych `TicketInput` nie
  przyjmuje (np. `estimatedTime`, `lastResponseUserId`, `relatedTicketId`) -
  sa ustawiane po stronie CRM.
- **`create()` i `addMessage()` zwracaja `WriteResult`** (`->id` = odpowiednio
  `ticketId`/`messageId`, plus `->created`, `->warnings`, `isDuplicate()`), nie
  samo id. Szczegoly: sekcja "Co zwracaja zapisy" w
  [ai_integration.md](../ai_integration.md).
