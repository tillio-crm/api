# Playbook: Polaczenia telefoniczne (phone-calls)

Zapis i odczyt rozmow telefonicznych: rekordy z systemu telefonii (VoIP),
przypiecie do kartoteki kontrahenta, powiazanie z kontaktem po numerze. Pisane
dla asystenta AI - zaklada wspolne wzorce z
[ai_integration.md](../ai_integration.md) (zwlaszcza `resolveUserId()`,
`findByName()`, sekcje "Zlota zasada: nie zgaduj id"). Wymaga API >= 2.10.0.

Zasob: `$client->phoneCalls()`.

## Model danych w skrocie

Rozmowe zapisuje sie przez `PhoneCallInput`. Przy tworzeniu (`create()`) API
WYMAGA szesciu pol:

- `source` (string) - system zrodlowy rozmowy, np. `'tillio-calls'`,
- `sourceId` (string) - identyfikator rozmowy w tym systemie, np. `'call-abc123'`,
- `direction` (string) - `'inbound'` albo `'outbound'`,
- `status` (string) - `'answered'`, `'missed'`, `'busy'`, `'voicemail'` albo
  `'failed'` (przy `'answered'` zawsze podawaj `duration` - patrz Pulapki),
- `remoteNumber` (string) - numer drugiej strony w formacie miedzynarodowym,
- `startedAt` (string) - moment rozpoczecia w ISO 8601.

Reszta jest opcjonalna. Pelna lista pol: `src/Dto/PhoneCallInput.php`.

Dwie rzeczy odrozniaja ten zasob od typowych zapisow CRM:

1. **`create()` i `update()` zwracaja `PhoneCall` (odczyt), nie `WriteResult`.**
   Dostajesz od razu pelny rekord z `->id`, `->noteIds` i pozostalymi polami.
   Nie ma tu `->created`, `->isDuplicate()` ani `->warnings`.
2. **To ten sam rekord, ktory zakladaja integracje VoIP** (np. Tillio Calls).
   Z kazda rozmowa CRM automatycznie tworzy notatke na kartotece - jej id
   znajdziesz w `->noteIds`. Nie zakladaj notatki recznie.

Kluczowa konsekwencja: para `source` + `sourceId` jest kluczem idempotencji.
Ten sam identyfikator rozmowy z tego samego systemu nie powinien trafiac dwa
razy jako nowy rekord - patrz Pulapki.

## Pola

Input (`PhoneCallInput`) - wszystkie pola konstruktora:

| pole | typ | po co (skad wziac) |
|---|---|---|
| `source` | string | WYMAGANE. Nazwa systemu telefonii, np. `'tillio-calls'`. Stala dla danej integracji; razem z `sourceId` tworzy klucz idempotencji |
| `sourceId` | string | WYMAGANE. Id rozmowy w systemie zrodlowym, np. `'call-abc123'`. Musi byc stabilny i unikalny w obrebie `source` |
| `direction` | string | WYMAGANE. `'inbound'` (przychodzace) albo `'outbound'` (wychodzace) |
| `status` | string | WYMAGANE. `'answered'`, `'missed'`, `'busy'`, `'voicemail'` albo `'failed'`. Dla `'answered'` zawsze dokladaj `duration` |
| `remoteNumber` | string | WYMAGANE. Numer drugiej strony w formacie miedzynarodowym, np. `'+48601234567'`. Po nim rozwiazujesz kontakt przez `lookup()->phone()` |
| `ownNumber` | ?string | Numer wlasny (linia firmowa), format miedzynarodowy. Opcjonalny, warto podac dla rozpoznania linii |
| `startedAt` | string | WYMAGANE. Poczatek rozmowy w ISO 8601 z offsetem strefy (`DATE_ATOM`), np. `'2026-09-06T10:15:00+02:00'` |
| `duration` | ?int | Czas trwania w sekundach. Przy `status = 'answered'` podawaj zawsze - od API 2.12.0 brak czasu nie jest juz bledem, ale rozmowa wypada z raportu VoIP; dla nieodebranych pomijany albo 0 |
| `userId` | ?int | Pracownik prowadzacy rozmowe. Rozwiaz przez `resolveUserId()` z nazwiska albo `users()->list(['email' => ...])` |
| `contactId` | ?int | Osoba kontaktowa po drugiej stronie. Rozwiaz przez `lookup()->phone($remoteNumber)` (pole `contacts`) albo `contacts()->list([...])` |
| `contractorId` | ?int | Kartoteka kontrahenta, do ktorej przypiac rozmowe. Z `lookup()->phone()` (`contractors` albo `contacts[].contractorId`) albo `contractors()->list([...])` |
| `title` | ?string | Krotki tytul/temat rozmowy. Opcjonalny, dowolny tekst |
| `summary` | ?string | Podsumowanie rozmowy (HTML). Trafia do notatki na kartotece |
| `tldr` | ?string | Jednozdaniowe streszczenie. Opcjonalne |
| `recordingCallId` | ?string | Id nagrania w systemie telefonii, jesli rozmowa byla nagrana |
| `callsUrl` | ?string | Link do rozmowy w panelu systemu telefonii |
| `creatorUserId` | ?int | Tylko przy tworzeniu. Pracownik, ktory rejestruje rekord (audyt), gdy inny niz `userId` |

Odczyt (`PhoneCall`) - pola dodatkowe, ktorych input NIE przyjmuje (ustawia je CRM):

| pole | typ | znaczenie |
|---|---|---|
| `id` | int | Id rekordu rozmowy w CRM |
| `provider` | ?string | Rozpoznany dostawca telefonii (ustawia CRM na podstawie integracji) |
| `contractorIds` | list&lt;int&gt; | Wszystkie kartoteki powiazane z rozmowa (odczyt; input przyjmuje pojedynczy `contractorId`) |
| `noteIds` | list&lt;int&gt; | Id notatek powstalych z rozmowy na kartotece. Tu sprawdzasz, ze notatka sie zalozyla |

Pelna lista: `src/Dto/PhoneCall.php` i `src/Dto/PhoneCallInput.php`.

## Mapowanie intencji uzytkownika na dane API

| Uzytkownik mowi | Potrzebujesz | Skad wziac |
|---|---|---|
| "rozmowa przychodzaca z +48601234567" | `direction: 'inbound'`, `remoteNumber` | numer wprost z polecenia/zdarzenia, w formacie miedzynarodowym |
| "oddzwonilem do klienta" | `direction: 'outbound'` | j.w. |
| "odebrana, trwala 3 minuty" | `status: 'answered'`, `duration: 180` | przelicz czas na sekundy; przy `answered` zawsze dokladaj `duration` |
| "nieodebrane" / "poczta glosowa" | `status: 'missed'` / `'voicemail'` | z opisu zdarzenia; bez `duration` |
| "przypnij do kontrahenta Acme" | `contractorId` | `lookup()->phone($remoteNumber)` albo `contractors()->list(['name' => 'Acme'])` |
| "kto dzwonil" | `contactId` | `lookup()->phone($remoteNumber)`, pole `contacts` (patrz playbook [lookup](../lookup/README.md)) |
| "rozmowe prowadzil Jan Kowalski" | `userId` | `resolveUserId($client, 'Jan', 'Kowalski')` |
| "z systemu Tillio Calls, rozmowa call-abc123" | `source`, `sourceId` | stale identyfikatory z systemu zrodlowego |
| "o 10:15 dzisiaj" | `startedAt` | sformatuj do ISO 8601 (`DATE_ATOM`) |

## Scenariusz flagowy: zapis rozmowy z systemu telefonii

Polecenie/zdarzenie: *"System telefonii zglasza rozmowe przychodzaca call-abc123
z numeru +48601234567, odebrana, trwala 2 minuty, o 10:15. Zapisz ja w CRM,
rozpoznaj kto dzwonil i przypnij do wlasciwej kartoteki."*

Tok postepowania:

1. Zbierz z zdarzenia klucz idempotencji (`source` + `sourceId`), kierunek,
   status, numer, czas i moment startu.
2. Sprawdz, czy rozmowa juz nie jest zapisana (ten sam `source` + `sourceId`) -
   nie duplikuj.
3. Rozwiaz numer na kontakt/kontrahenta przez `lookup()->phone()`.
4. Zloz `PhoneCallInput` i zapisz. `create()` zwraca `PhoneCall`.
5. Zwroc potwierdzenie z id rozmowy i id notatki, ktora sie zalozyla.

```php
<?php

use TillioCrm\Api\Dto\PhoneCallInput;

// Krok 1: dane ze zdarzenia telefonii (Ty je wyluskujesz z payloadu).
$source = 'tillio-calls';                 // stala nazwa systemu zrodlowego
$sourceId = 'call-abc123';                // id rozmowy w tym systemie
$remoteNumber = '+48601234567';           // format miedzynarodowy
$startedAt = (new DateTimeImmutable('2026-09-06 10:15:00'))->format(DATE_ATOM);
$durationSeconds = 120;                   // 2 minuty; przy answered zawsze podawaj

// Krok 2: idempotencja. Ta sama rozmowa nie moze wejsc dwa razy.
// Filtrujemy po parze source + sourceId; jesli cos jest - konczymy.
$existing = $client->phoneCalls()->list([
    'source' => $source,
    'sourceId' => $sourceId,
    'limit' => 1,
])->first();

if ($existing !== null) {
    // Rekord juz istnieje - nie tworzymy drugiego. Ewentualnie update($existing->id, ...).
    echo "Rozmowa {$sourceId} juz zapisana jako #{$existing->id}.\n";
    return;
}

// Krok 3: kto dzwonil. Lookup sprowadza numer do kanonu miedzynarodowego
// po stronie API i zwraca kontakty oraz kontrahentow z tym numerem.
$lookup = $client->lookup()->phone($remoteNumber);

$contactId = null;
$contractorId = null;

// Jednoznaczny kontakt: dokladnie jeden. Przy zerze albo wielu - nie zgaduj,
// zostaw puste (rozmowa i tak sie zapisze, tylko bez twardego powiazania).
if (count($lookup->contacts) === 1) {
    $contact = $lookup->contacts[0];
    $contactId = $contact->id;
    // Kontakt zwykle niesie swojego kontrahenta - uzyj go, jesli jest.
    $contractorId = $contact->contractorId;
}

// Gdy kontaktu nie ma, a numer pasuje do kartoteki kontrahenta wprost:
if ($contractorId === null && count($lookup->contractors) === 1) {
    $contractorId = $lookup->contractors[0]->id;
}

// Krok 4: zloz input i zapisz. Wszystkie 6 pol wymaganych mamy.
$call = $client->phoneCalls()->create(new PhoneCallInput(
    source: $source,
    sourceId: $sourceId,
    direction: 'inbound',            // przychodzaca
    status: 'answered',              // odebrana -> dokladaj duration, inaczej wypada z raportu
    remoteNumber: $remoteNumber,
    startedAt: $startedAt,
    duration: $durationSeconds,
    contactId: $contactId,           // null gdy nie rozpoznano jednoznacznie
    contractorId: $contractorId,     // null gdy brak kartoteki
));

// Krok 5: potwierdzenie. create() zwrocilo PhoneCall (nie WriteResult).
// Notatka na kartotece zaklada sie automatycznie - jej id jest w noteIds.
$noteInfo = $call->noteIds === [] ? 'bez notatki' : 'notatka #' . implode(', #', $call->noteIds);
echo "Zapisano rozmowe #{$call->id} ({$noteInfo}).\n";
```

Co zwrocic uzytkownikowi: id rozmowy (`$call->id`), czy rozpoznano dzwoniacego
(`$call->contactId` / `$call->contractorIds`) oraz id notatki z `->noteIds`.
Gdy lookup nie dal jednoznacznego trafienia, powiedz o tym wprost - rekord
istnieje, ale bez twardego powiazania z osoba.

## Warianty

### Rozmowa nieodebrana

Status inny niz `'answered'` nie wymaga `duration`. Nie ustawiaj czasu na sile.

```php
$client->phoneCalls()->create(new PhoneCallInput(
    source: 'tillio-calls',
    sourceId: 'call-abc124',
    direction: 'inbound',
    status: 'missed',                // nieodebrana - bez duration
    remoteNumber: '+48601234567',
    startedAt: (new DateTimeImmutable('now'))->format(DATE_ATOM),
));
```

### Uzupelnienie rozmowy po fakcie (nagranie, podsumowanie)

Gdy system telefonii dosyla dane po zakonczeniu rozmowy (transkrypcja, link do
nagrania), zaktualizuj istniejacy rekord. `update()` tez zwraca `PhoneCall`.

```php
$client->phoneCalls()->update($call->id, new PhoneCallInput(
    summary: '<p>Klient prosi o oferte na wdrozenie.</p>',
    tldr: 'Prosba o oferte',
    recordingCallId: 'rec-xyz789',
    callsUrl: 'https://calls.example/rec-xyz789',
));
```

### Przypiecie do konkretnego kontrahenta z nazwy

Gdy uzytkownik wskazuje firme z nazwy zamiast polegac na lookup po numerze:

```php
$contractor = $client->contractors()->list(['name' => 'Acme', 'limit' => 1])->first();
if ($contractor === null) {
    throw new RuntimeException('Nie znaleziono kontrahenta Acme - dopytaj albo zaloz kartoteke.');
}
// ... podaj contractorId: $contractor->id w PhoneCallInput
```

### Prowadzacy rozmowe z nazwiska

```php
// resolveUserId() z ai_integration.md: rzuca przy zeru/wielu trafieniach.
$userId = resolveUserId($client, 'Jan', 'Kowalski');
// ... podaj userId: $userId w PhoneCallInput
```

## Pulapki

- **`create()` zwraca `PhoneCall`, nie `WriteResult`.** Nie szukaj `->created`
  ani `->isDuplicate()`. Idempotencje ogarniasz sam, sprawdzajac istniejacy
  rekord po `source` + `sourceId` przed zapisem.
- **`source` + `sourceId` to klucz idempotencji.** Ta sama rozmowa dostarczona
  dwa razy (retry webhooka) zaloży dwa rekordy, jesli nie sprawdzisz wczesniej.
  Zawsze najpierw `list(['source' => ..., 'sourceId' => ...])`.
- **Przy `status: 'answered'` zawsze podawaj `duration`.** Do API 2.11 odebrana
  rozmowa bez czasu trwania konczyla sie bledem 422. Od 2.12.0 zapisuje sie,
  ale z ostrzezeniem - i wypada z raportu VoIP, bo ten liczy odebrane po czasie
  rozmowy. Cichy brak w zestawieniach jest gorszy od jawnego bledu, wiec traktuj
  `duration` jak wymagane. Dla nieodebranych (`missed`, `busy`, `voicemail`,
  `failed`) nie ustawiaj go wcale.
- **Numery w formacie miedzynarodowym** (`+48601234567`). Numer lokalny bez
  prefiksu kraju psuje lookup i rozpoznanie linii.
- **`startedAt` w ISO 8601 z offsetem strefy** (`DATE_ATOM`, np.
  `2026-09-06T10:15:00+02:00`). Inny format to 400.
- **Notatka zaklada sie sama.** Nie twórz recznie notatki na kartotece dla
  rozmowy - CRM robi to automatycznie, id jest w `->noteIds`.
- **Lookup bywa niejednoznaczny.** Numer moze pasowac do wielu kontaktow (ten
  sam numer u kilku osob). Przy wielu trafieniach nie wybieraj pierwszego z
  brzegu - zapisz rozmowe bez `contactId` albo dopytaj. Patrz playbook
  [lookup](../lookup/README.md).
- **`contractorIds` (odczyt) vs `contractorId` (input).** Input przyjmuje jeden
  `contractorId`; odczyt zwraca liste `contractorIds` (rozmowa moze dotknac
  wielu kartotek przez wspoldzielony kontakt).
