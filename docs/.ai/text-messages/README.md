# Playbook: Wiadomosci SMS (text-messages)

Zapis i odczyt wiadomosci SMS: rekordy z bramek SMS, przypiecie do kartoteki
kontrahenta, powiazanie z kontaktem po numerze. Pisane dla asystenta AI -
zaklada wspolne wzorce z [ai_integration.md](../ai_integration.md) (zwlaszcza
`resolveUserId()`, `findByName()`, sekcje "Zlota zasada: nie zgaduj id").
Wymaga API >= 2.10.0.

Zasob: `$client->textMessages()`. Model jest niemal identyczny jak
[polaczenia telefoniczne](../phone-calls/README.md) - rozne sa tylko pola
tresci i inny zestaw statusow.

## Model danych w skrocie

Wiadomosc zapisuje sie przez `TextMessageInput`. Przy tworzeniu (`create()`)
API WYMAGA siedmiu pol:

- `source` (string) - system/bramka zrodlowa, np. `'tillio-calls'`,
- `sourceId` (string) - identyfikator wiadomosci w tym systemie, np. `'call-abc123'`,
- `direction` (string) - `'inbound'` albo `'outbound'`,
- `status` (string) - `'received'`, `'sent'`, `'delivered'` albo `'failed'`,
- `remoteNumber` (string) - numer drugiej strony w formacie miedzynarodowym,
- `body` (string) - tresc wiadomosci, do 1024 znakow,
- `sentAt` (string) - moment wyslania w ISO 8601.

Reszta jest opcjonalna. Pelna lista pol: `src/Dto/TextMessageInput.php`.

Tak jak przy rozmowach:

1. **`create()` i `update()` zwracaja `TextMessage` (odczyt), nie `WriteResult`.**
   Dostajesz pelny rekord z `->id` i `->noteIds`. Nie ma `->created`,
   `->isDuplicate()` ani `->warnings`.
2. **To ten sam rekord, ktory zakladaja integracje bramek SMS.** Z kazda
   wiadomoscia CRM tworzy notatke na kartotece (`->noteIds`). Nie zakladaj jej
   recznie.

Para `source` + `sourceId` jest kluczem idempotencji - patrz Pulapki.

## Pola

Input (`TextMessageInput`) - wszystkie pola konstruktora:

| pole | typ | po co (skad wziac) |
|---|---|---|
| `source` | string | WYMAGANE. Nazwa bramki/systemu, np. `'tillio-calls'`. Stala; razem z `sourceId` tworzy klucz idempotencji |
| `sourceId` | string | WYMAGANE. Id wiadomosci w systemie zrodlowym, np. `'call-abc123'`. Stabilny i unikalny w obrebie `source` |
| `direction` | string | WYMAGANE. `'inbound'` (przychodzaca) albo `'outbound'` (wychodzaca) |
| `status` | string | WYMAGANE. `'received'`, `'sent'`, `'delivered'` albo `'failed'` |
| `remoteNumber` | string | WYMAGANE. Numer drugiej strony w formacie miedzynarodowym, np. `'+48601234567'`. Po nim rozwiazujesz kontakt przez `lookup()->phone()` |
| `ownNumber` | ?string | Numer wlasny (linia/nadawca SMS), format miedzynarodowy. Opcjonalny |
| `body` | string | WYMAGANE. Tresc wiadomosci, do 1024 znakow |
| `sentAt` | string | WYMAGANE. Moment wyslania w ISO 8601 z offsetem strefy (`DATE_ATOM`) |
| `deliveredAt` | ?string | Moment doreczenia w ISO 8601. Uzupelniany zwykle przy statusie `'delivered'` |
| `userId` | ?int | Pracownik powiazany z wiadomoscia. Rozwiaz przez `resolveUserId()` albo `users()->list(['email' => ...])` |
| `contactId` | ?int | Osoba kontaktowa po drugiej stronie. Rozwiaz przez `lookup()->phone($remoteNumber)` (pole `contacts`) albo `contacts()->list([...])` |
| `contractorId` | ?int | Kartoteka kontrahenta do przypiecia. Z `lookup()->phone()` (`contractors` albo `contacts[].contractorId`) albo `contractors()->list([...])` |
| `callsUrl` | ?string | Link do watku/wiadomosci w panelu systemu zrodlowego |
| `creatorUserId` | ?int | Tylko przy tworzeniu. Pracownik rejestrujacy rekord (audyt), gdy inny niz `userId` |

Odczyt (`TextMessage`) - pola dodatkowe, ktorych input NIE przyjmuje (ustawia je CRM):

| pole | typ | znaczenie |
|---|---|---|
| `id` | int | Id rekordu wiadomosci w CRM |
| `provider` | ?string | Rozpoznana bramka (ustawia CRM na podstawie integracji) |
| `title` | ?string | Tytul/temat nadany przez CRM |
| `contractorIds` | list&lt;int&gt; | Wszystkie kartoteki powiazane z wiadomoscia (odczyt; input przyjmuje pojedynczy `contractorId`) |
| `noteIds` | list&lt;int&gt; | Id notatek powstalych z wiadomosci na kartotece |

Pelna lista: `src/Dto/TextMessage.php` i `src/Dto/TextMessageInput.php`.

## Mapowanie intencji uzytkownika na dane API

| Uzytkownik mowi | Potrzebujesz | Skad wziac |
|---|---|---|
| "SMS przyszedl z +48601234567" | `direction: 'inbound'`, `status: 'received'`, `remoteNumber` | numer wprost ze zdarzenia, format miedzynarodowy |
| "wyslalem SMS do klienta" | `direction: 'outbound'`, `status: 'sent'` | j.w. |
| "SMS doreczony" | `status: 'delivered'`, `deliveredAt` | ze statusu bramki; ustaw moment doreczenia |
| "SMS nie doszedl" | `status: 'failed'` | ze statusu bramki |
| "tresc: ..." | `body` | tekst wprost; przytnij/odrzuc powyzej 1024 znakow |
| "przypnij do kontrahenta Acme" | `contractorId` | `lookup()->phone($remoteNumber)` albo `contractors()->list(['name' => 'Acme'])` |
| "kto to napisal" | `contactId` | `lookup()->phone($remoteNumber)`, pole `contacts` (patrz playbook [lookup](../lookup/README.md)) |
| "z systemu Tillio Calls, wiadomosc call-abc123" | `source`, `sourceId` | stale identyfikatory ze zdarzenia |

## Scenariusz flagowy: zapis przychodzacego SMS z bramki

Zdarzenie: *"Bramka zglasza SMS call-abc123 od +48601234567 o tresci 'Prosze o
kontakt', przyszla o 09:40. Zapisz w CRM i podepnij do wlasciwej osoby."*

Tok postepowania jest taki sam jak przy rozmowie: klucz idempotencji, sprawdzenie
duplikatu, lookup po numerze, zapis, potwierdzenie z id notatki.

```php
<?php

use TillioCrm\Api\Dto\TextMessageInput;

// Krok 1: dane ze zdarzenia bramki.
$source = 'tillio-calls';
$sourceId = 'call-abc123';
$remoteNumber = '+48601234567';           // format miedzynarodowy
$body = 'Prosze o kontakt';               // do 1024 znakow
$sentAt = (new DateTimeImmutable('2026-09-06 09:40:00'))->format(DATE_ATOM);

// Bezpiecznik dlugosci: API tnie na 1024 znakach.
if (mb_strlen($body) > 1024) {
    $body = mb_substr($body, 0, 1024);
}

// Krok 2: idempotencja - ta sama wiadomosc nie moze wejsc dwa razy.
$existing = $client->textMessages()->list([
    'source' => $source,
    'sourceId' => $sourceId,
    'limit' => 1,
])->first();

if ($existing !== null) {
    echo "Wiadomosc {$sourceId} juz zapisana jako #{$existing->id}.\n";
    return;
}

// Krok 3: kto napisal. Lookup sprowadza numer do kanonu miedzynarodowego.
$lookup = $client->lookup()->phone($remoteNumber);

$contactId = null;
$contractorId = null;

if (count($lookup->contacts) === 1) {
    $contact = $lookup->contacts[0];
    $contactId = $contact->id;
    $contractorId = $contact->contractorId;
}

if ($contractorId === null && count($lookup->contractors) === 1) {
    $contractorId = $lookup->contractors[0]->id;
}

// Krok 4: zloz input i zapisz. Wszystkie 7 pol wymaganych mamy.
$message = $client->textMessages()->create(new TextMessageInput(
    source: $source,
    sourceId: $sourceId,
    direction: 'inbound',            // przychodzaca
    status: 'received',              // odebrana przez CRM
    remoteNumber: $remoteNumber,
    body: $body,
    sentAt: $sentAt,
    contactId: $contactId,           // null gdy nie rozpoznano jednoznacznie
    contractorId: $contractorId,     // null gdy brak kartoteki
));

// Krok 5: potwierdzenie. create() zwrocilo TextMessage (nie WriteResult).
$noteInfo = $message->noteIds === [] ? 'bez notatki' : 'notatka #' . implode(', #', $message->noteIds);
echo "Zapisano SMS #{$message->id} ({$noteInfo}).\n";
```

Co zwrocic uzytkownikowi: id wiadomosci (`$message->id`), czy rozpoznano nadawce
oraz id notatki z `->noteIds`. Gdy lookup nie dal jednoznacznego trafienia,
powiedz o tym - rekord istnieje, ale bez twardego powiazania z osoba.

## Warianty

### Wiadomosc wychodzaca z potwierdzeniem doreczenia

```php
$client->textMessages()->create(new TextMessageInput(
    source: 'tillio-calls',
    sourceId: 'call-abc124',
    direction: 'outbound',
    status: 'delivered',
    remoteNumber: '+48601234567',
    body: 'Dziekujemy za kontakt, oddzwonimy jutro.',
    sentAt: (new DateTimeImmutable('now'))->format(DATE_ATOM),
    deliveredAt: (new DateTimeImmutable('now'))->format(DATE_ATOM),
    userId: 7,   // pracownik wysylajacy; w praktyce resolveUserId(...)
));
```

### Aktualizacja statusu po doreczeniu

Gdy bramka dosyla potwierdzenie doreczenia osobnym zdarzeniem:

```php
$client->textMessages()->update($message->id, new TextMessageInput(
    status: 'delivered',
    deliveredAt: (new DateTimeImmutable('now'))->format(DATE_ATOM),
));
```

### Przypiecie do kontrahenta z nazwy

```php
$contractor = $client->contractors()->list(['name' => 'Acme', 'limit' => 1])->first();
if ($contractor === null) {
    throw new RuntimeException('Nie znaleziono kontrahenta Acme - dopytaj albo zaloz kartoteke.');
}
// ... podaj contractorId: $contractor->id w TextMessageInput
```

## Pulapki

- **`create()` zwraca `TextMessage`, nie `WriteResult`.** Idempotencje ogarniasz
  sam, sprawdzajac istniejacy rekord po `source` + `sourceId` przed zapisem.
- **`source` + `sourceId` to klucz idempotencji.** Retry webhooka zaloży drugi
  rekord, jesli nie sprawdzisz wczesniej przez `list([...])`.
- **`body` do 1024 znakow.** Dluzsza tresc to 422 - przytnij albo odrzuc przed
  zapisem, nie licz na cicha korekte.
- **Inny zestaw statusow niz przy rozmowach.** SMS: `received`, `sent`,
  `delivered`, `failed`. Nie uzywaj `answered`/`missed`/`busy`/`voicemail` -
  to statusy rozmow telefonicznych.
- **Numery w formacie miedzynarodowym** (`+48601234567`).
- **`sentAt` (i `deliveredAt`) w ISO 8601 z offsetem strefy** (`DATE_ATOM`).
  Inny format to 400.
- **Notatka zaklada sie sama** (`->noteIds`). Nie twórz jej recznie.
- **Lookup bywa niejednoznaczny.** Przy wielu trafieniach nie wybieraj pierwszego
  z brzegu - zapisz bez `contactId` albo dopytaj. Patrz playbook
  [lookup](../lookup/README.md).
