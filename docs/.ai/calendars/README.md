# Playbook: Kalendarze i wydarzenia (calendars)

Realizacja polecen uzytkownika dotyczacych kalendarzy: znalezienie glownego
kalendarza handlowca, odczyt wydarzen w zakresie dat, zakladanie spotkan
z uczestnikami i powiazaniem z kontrahentem. Pisane dla asystenta AI - zaklada
wspolne wzorce z [ai_integration.md](../ai_integration.md) (zwlaszcza
`resolveUserId()`, `findByName()` i "Zlota zasada: nie zgaduj id").

Wymaga API >= 2.2.0 (same kalendarze), a wydarzenia >= 2.3.0. Instalacja bez
modulu kalendarza odpowiada na trasy wydarzen wyjatkiem
`ServiceUnavailableException` (503, BEZ retry - deterministyczne); lista samych
kalendarzy dziala niezaleznie od modulu.

Kluczowa specyfika: wydarzenie zaklada sie ZAWSZE w kontekscie WLASCICIELA
kalendarza (to on jest organizatorem zaproszenia), wiec pytanie "gdzie wpisac
spotkanie tego handlowca" sprowadza sie do znalezienia jego GLOWNEGO kalendarza.

## Pola

Zapis wydarzenia idzie przez `CalendarEventInput`
(`src/Dto/CalendarEventInput.php`). Named arguments, `null` = nie wysylaj pola.
Wszystkie pola:

### CalendarEventInput

| pole | typ | po co (skad wziac) |
|---|---|---|
| `title` | `string` | Tytul wydarzenia. WYMAGANY. Tekst wprost od uzytkownika. |
| `description` | `string` | Opis/agenda. Tekst wprost. |
| `location` | `string` | Miejsce (adres, sala, link do wideo). Tekst wprost. |
| `startAt` | `string` | Poczatek, ISO 8601 ze strefa (np. `2026-09-10T10:00:00+02:00`). WYMAGANY. Policz i sformatuj `DATE_ATOM`. |
| `endAt` | `string` | Koniec, ISO 8601 ze strefa. WYMAGANY. Musi byc po `startAt`. |
| `allDay` | `bool` | Wydarzenie calodniowe. `true` gdy uzytkownik mowi "caly dzien". |
| `attendees` | `list<array{name?: string, email: string}>` | Uczestnicy - dostaja zaproszenia na podane adresy. `email` wymagany w kazdej pozycji, `name` opcjonalny. |
| `contractorId` | `int` | Powiazany kontrahent. Z `contractors()->list([...])`. |
| `contactId` | `int` | Powiazana osoba kontaktowa (API >= 2.8.0). Z `contacts()->list([...])`. |
| `taskId` | `int` | Powiazane zadanie. Z kontekstu/`tasks()->list([...])`. |
| `ticketId` | `int` | Powiazane zgloszenie. Z kontekstu/`tickets()->list([...])`. |
| `eventTypeId` | `int` | Typ wydarzenia ze slownika CRM. Pominiete = domyslny; gdy nie znasz id - pomin, nie zgaduj. |
| `sendNotifications` | `bool` | Czy rozeslac zaproszenia i powiadomienia. Domyslnie `true` po stronie API; ustaw `false`, gdy wpis ma byc cichy. |

Pola odczytu warte uwagi:

`Calendar` (`src/Dto/Calendar.php`): `id`, `name`, `calendarTypeId` (rodzaj:
Tillio/Microsoft/Google, przez `dictionaries()->calendarTypes()`), `ownerUserId`
(wlasciciel = organizator zakladanych wydarzen), `users` (`list<CalendarUser>`
- kto ma dostep i czyj to kalendarz glowny), `oauthAuthorized`
(false = kalendarz Tillio albo wygasla zgoda), `active`. Metoda pomocnicza
`Calendar::isMainFor(userId)` mowi, czy to GLOWNY kalendarz danej osoby.

`CalendarUser` (`src/Dto/CalendarUser.php`): `userId`, `main` (true = glowny
kalendarz tego uzytkownika - tu CRM wpisuje mu spotkania), `admin`, `accessTo`.

`CalendarEvent` (`src/Dto/CalendarEvent.php`): **`id` jest STRINGIEM** (UID
uslugi kalendarzowej, nie liczba jak w encjach CRM), `title`, `startAt`/`endAt`
(ISO 8601 ze strefa), `organizer` i `attendees` (`CalendarAttendee`
z `status`: ACCEPTED/DECLINED/TENTATIVE/NEEDS-ACTION), `recurrenceRule`
(null = jednorazowe), `contractorId`, `contactId` (powiązana osoba kontaktowa,
API >= 2.8.0), `eventTypeId`, `completed`.

## Model danych

Sciezka jest dwuetapowa: najpierw USTAL KALENDARZ (jego liczbowe `id`), potem
zaloz w nim wydarzenie.

- `list(filters)` (`GET /v2/calendars`) - strona kalendarzy z dostepami
  uzytkownikow. Filtry m.in.: `ownerUserId`, `name`, `calendarTypeId`, `active`.
- `get(id)` (`GET /v2/calendars/{id}`) - pojedynczy kalendarz.
- `events(calendarId, dateFrom, dateTo)` (`GET /v2/calendars/{id}/events`) -
  wydarzenia w zakresie dat; bez zakresu API zwraca najblizsze 30 dni. Lista bez
  stronicowania. `dateFrom`/`dateTo` przyjmuja `DateTimeInterface` albo string
  ISO 8601.
- `createEvent(calendarId, CalendarEventInput)` (`POST /v2/calendars/{id}/events`)
  - nowe wydarzenie; wymaga `title`, `startAt`, `endAt`. Zwraca `CalendarEvent`
  (nie `WriteResult`!) z `id` bedacym STRINGIEM.

Konsekwencje dla Ciebie:

- Uzytkownik poda nazwisko handlowca, nie `calendarId`. Rozwiaz osobe na
  `userId` (`resolveUserId()`), potem znajdz JEJ glowny kalendarz przez
  `list(['ownerUserId' => $userId])` + `Calendar::isMainFor($userId)`.
- Wydarzenie zaklada sie w kontekscie wlasciciela kalendarza - wpisujac spotkanie
  "u handlowca", wybierasz JEGO kalendarz, bo to on ma byc organizatorem.
- `startAt`/`endAt` musza byc w ISO 8601 ze strefa (`DATE_ATOM`). Inny format = 400.
- `id` wydarzenia to STRING - nie rzutuj na int, nie porownuj liczbowo.

## Mapowanie intencji

| Uzytkownik mowi | Potrzebujesz | Skad wziac |
|---|---|---|
| "u handlowca Jana Kowalskiego" | `calendarId` jego glownego kalendarza | `resolveUserId()` -> `list(['ownerUserId' => $id])` -> `isMainFor($id)` |
| "spotkanie z Acme" | `contractorId` | `contractors()->list(['name' => 'Acme'])` |
| "z osoba Jan Kowalski" | `contactId` (API >= 2.8.0) | `contacts()->list([...])`, dopasuj osobę |
| "jutro 10-11" | `startAt`, `endAt` (ISO 8601) | policz daty, `DATE_ATOM`; `endAt` po `startAt` |
| "zapros jan@... i anna@..." | `attendees: [['email' => ...], ...]` | adresy wprost; `name` opcjonalnie |
| "caly dzien" | `allDay: true` | z tekstu |
| "bez wysylania zaproszen" | `sendNotifications: false` | z tekstu |
| "typ: Telefon" | `eventTypeId` | slownik typow wydarzen CRM; brak dopasowania = pomin/dopytaj |
| "co ma w kalendarzu w tym tygodniu" | `events(calendarId, from, to)` | zakres dat tygodnia |

## Scenariusz flagowy: spotkanie w kalendarzu handlowca z klientem

Polecenie uzytkownika: *"Wpisz Janowi Kowalskiemu spotkanie wdrozeniowe
z Acme jutro na 10-11 i zapros osobe kontaktowa jan@acme.example."*

Tok postepowania:

1. Rozwiaz handlowca Jana Kowalskiego na `userId`.
2. Znajdz JEGO glowny kalendarz (`ownerUserId` + `isMainFor`) - to jego `id`.
3. Rozwiaz kontrahenta Acme na `contractorId`.
4. Policz `startAt`/`endAt` (jutro 10:00-11:00) w ISO 8601.
5. Zaloz wydarzenie; obsluz brak modulu kalendarza.

```php
use TillioCrm\Api\Dto\CalendarEventInput;
use TillioCrm\Api\Exception\ServiceUnavailableException;

// Krok 1: handlowiec po imieniu i nazwisku -> userId (resolveUserId rzuca przy
// zeru/wielu trafieniach; wtedy dopytaj o e-mail, nie zgaduj).
$ownerId = resolveUserId($client, 'Jan', 'Kowalski');

// Krok 2: znajdz GLOWNY kalendarz tego uzytkownika. Filtrujemy po wlascicielu,
// potem wybieramy ten z flaga main (isMainFor). Nie bierz pierwszego z brzegu.
$mainCalendar = null;
foreach ($client->calendars()->list(['ownerUserId' => $ownerId, 'limit' => 50]) as $calendar) {
    if ($calendar->isMainFor($ownerId)) {
        $mainCalendar = $calendar;
        break;
    }
}
if ($mainCalendar === null) {
    // Brak glownego kalendarza - nie wpisuj do przypadkowego, oddaj userowi.
    throw new RuntimeException('Nie znaleziono glownego kalendarza Jana Kowalskiego - dopytaj, gdzie wpisac spotkanie.');
}

// Krok 3: kontrahent po nazwie -> contractorId.
$contractor = $client->contractors()->list(['name' => 'Acme', 'limit' => 1])->first();
if ($contractor === null) {
    throw new RuntimeException('Nie znaleziono kontrahenta Acme - dopytaj albo zaloz kartoteke.');
}

// Krok 4: daty. "Jutro 10-11" liczymy w strefie lokalnej i formatujemy z offsetem
// (DATE_ATOM). endAt musi byc po startAt.
$tomorrow = new DateTimeImmutable('tomorrow');
$startAt = $tomorrow->setTime(10, 0)->format(DATE_ATOM);
$endAt = $tomorrow->setTime(11, 0)->format(DATE_ATOM);

// Krok 5: zaloz wydarzenie w kalendarzu handlowca (on jest organizatorem).
// createEvent zwraca CalendarEvent (nie WriteResult!), a jego id to STRING.
try {
    $event = $client->calendars()->createEvent($mainCalendar->id, new CalendarEventInput(
        title: 'Spotkanie wdrozeniowe',
        startAt: $startAt,
        endAt: $endAt,
        attendees: [['name' => 'Jan z Acme', 'email' => 'jan@acme.example']],
        contractorId: $contractor->id,
    ));
} catch (ServiceUnavailableException $e) {
    // Modul kalendarza wylaczony w tej instancji - NIE ponawiaj (503 jest
    // deterministyczne). Zglos to uzytkownikowi.
    throw new RuntimeException('Modul kalendarza jest wylaczony w tej instancji - sprawdz modules(), nie ponawiaj.', 0, $e);
}

echo "Zalozono wydarzenie {$event->id} w kalendarzu #{$mainCalendar->id} ({$startAt} - {$endAt}).\n";
```

Co zwrocic uzytkownikowi: identyfikator wydarzenia (`$event->id` - STRING),
kalendarz (czyj), termin i liste zaproszonych. Gdy krok 2 nie znalazl glownego
kalendarza albo krok 3 kontrahenta - zamiast tworzyc, dopytaj.

## Warianty

### Odczyt wydarzen w zakresie dat

```php
// "Co Jan ma w tym tygodniu?" - zakres dat na wejsciu (DateTimeInterface albo
// string ISO 8601). Bez zakresu API zwraca najblizsze 30 dni.
$from = new DateTimeImmutable('monday this week');
$to = new DateTimeImmutable('sunday this week 23:59:59');
$events = $client->calendars()->events($mainCalendar->id, $from, $to); // list<CalendarEvent>
foreach ($events as $event) {
    // id STRING, daty ISO 8601 ze strefa.
    echo "{$event->startAt} {$event->title}\n";
}
```

### Wydarzenie calodniowe bez zaproszen

```php
use TillioCrm\Api\Dto\CalendarEventInput;

$client->calendars()->createEvent($calendarId, new CalendarEventInput(
    title: 'Urlop',
    startAt: '2026-09-14T00:00:00+02:00',
    endAt: '2026-09-14T23:59:59+02:00',
    allDay: true,
    sendNotifications: false,   // cichy wpis, bez rozsylania zaproszen
));
```

### Rodzaj kalendarza (Tillio / Microsoft / Google)

```php
// calendarTypeId mapuje slownik calendarTypes(); przydatne przy filtrowaniu
// listy albo prezentacji (np. tylko kalendarze Tillio).
$types = $client->dictionaries()->calendarTypes(); // list<DictionaryEntry>
$tilioTypeId = findByName($types, 'Tillio');
```

## Pulapki

- **`title`, `startAt`, `endAt` sa wymagane.** Brak ktoregokolwiek = 422.
- **Daty w ISO 8601 ze strefa** (`DATE_ATOM`, np. `2026-09-10T10:00:00+02:00`).
  `endAt` musi byc po `startAt`. Inny format = 400.
- **`id` wydarzenia to STRING**, nie int - to UID uslugi kalendarzowej. Nie
  rzutuj na int, nie porownuj liczbowo z id encji CRM.
- **`createEvent()` zwraca `CalendarEvent`, nie `WriteResult`.** Nie szukaj tu
  `->id` jako int ani `->created` - masz pelne DTO wydarzenia.
- **Wydarzenie zaklada sie u WLASCICIELA kalendarza.** Wpisujac spotkanie "u
  handlowca", wybierz jego glowny kalendarz (`isMainFor`), bo to on ma byc
  organizatorem - nie wpisuj do pierwszego znalezionego.
- **Brak modulu = `ServiceUnavailableException` (503), BEZ retry.** SDK nie
  ponawia deterministycznego 503 - zlap wyjatek, sprawdz `$client->modules()`
  i zglos uzytkownikowi zamiast probowac ponownie. Lista samych kalendarzy
  (`list`/`get`) dziala niezaleznie od modulu wydarzen.
- **`eventTypeId` ze slownika CRM** - gdy uzytkownik poda nazwe typu spoza
  slownika, pomin pole albo dopytaj, nie zgaduj id.
- **Wersje**: kalendarze od API 2.2.0, wydarzenia od 2.3.0 (jednolite strefy
  w datach od 2.3.1). Na starszej instancji trasy wydarzen nie beda dzialac.
- **`contactId` wymaga API >= 2.8.0.** Powiazanie osoby kontaktowej (zapis
  w `CalendarEventInput` i odczyt w `CalendarEvent`) na starszej instancji
  odpadnie z `FeatureNotSupportedException` (501) - nie ponawiaj, zaktualizuj CRM.
