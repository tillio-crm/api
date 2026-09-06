# Kalendarze

Kalendarze użytkowników i zespołów z wydarzeniami. Wymaga API >= 2.2.0;
trasy wydarzeń (odczyt i zakładanie) wymagają API >= 2.3.0.

```php
use TillioCrm\Api\Dto\CalendarEventInput;

// Lista kalendarzy z dostępami użytkowników (filtry m.in. calendarTypeId,
// ownerUserId, mailAccountId, active)
$page = $client->calendars()->list(['active' => true]);
foreach ($page as $calendar) {
    $calendar->name;
    $calendar->calendarTypeId;     // rodzaj: dictionaries()->calendarTypes()
    $calendar->oauthAuthorized;    // false = kalendarz Tillio albo wygasła zgoda
    if ($calendar->isMainFor(7)) {
        // główny kalendarz użytkownika 7 - tu CRM wpisuje mu wydarzenia;
        // to odpowiedź na pytanie "gdzie wpisać spotkanie tego handlowca"
    }
}

// Pojedynczy kalendarz (trasa nie przyjmuje parametrów)
$calendar = $client->calendars()->get(12);

// Wydarzenia w zakresie dat - daty jako DateTimeInterface albo string ISO 8601;
// bez zakresu API zwraca najbliższe 30 dni. Lista bez stronicowania.
$events = $client->calendars()->events(
    12,
    new \DateTimeImmutable('2026-09-01'),
    new \DateTimeImmutable('2026-09-30'),
);
foreach ($events as $event) {
    $event->id;                    // UWAGA: string (UID usługi kalendarzowej), nie int
    $event->startAt;               // ISO 8601 ze strefą instancji
    $event->attendees;             // uczestnicy z odpowiedziami na zaproszenie
    $event->recurrenceRule;        // null = wydarzenie jednorazowe
}

// Nowe wydarzenie (title, startAt, endAt wymagane). Zakłada się w kontekście
// WŁAŚCICIELA kalendarza - to on figuruje jako organizator zaproszenia.
// Uczestnicy z attendees dostają zaproszenia (sendNotifications: false wyłącza).
$event = $client->calendars()->createEvent(12, new CalendarEventInput(
    title: 'Spotkanie wdrożeniowe',
    startAt: '2026-09-10T10:00:00+02:00',
    endAt: '2026-09-10T11:00:00+02:00',
    attendees: [['name' => 'Jan Kowalski', 'email' => 'jan@przyklad.example']],
    contractorId: 42,
));

// Rodzaje kalendarzy: Tillio, Microsoft, Google (tylko odczyt)
$types = $client->dictionaries()->calendarTypes();
```

## Instalacja bez modułu kalendarza

Instancja bez modułu kalendarza odpowiada na trasy wydarzeń (`events()`,
`createEvent()`) wyjątkiem `ServiceUnavailableException` (503 z kodem
`calendar.serviceUnavailable`). To stan deterministyczny - ponowienie da ten
sam wynik, więc SDK NIE ponawia takiego żądania (w odróżnieniu od gołego 503
bez kodu, traktowanego jak chwilowa awaria). Naprawia się w konfiguracji
instancji, nie w kodzie. Lista samych kalendarzy (`list()`, `get()`) działa
niezależnie od modułu.
