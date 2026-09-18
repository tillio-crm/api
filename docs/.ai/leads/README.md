# Playbook: Leady (leads)

Realizacja poleceń użytkownika dotyczących leadów: rejestrowanie zapytań (także
z formularzy, bez dubli), dane firmy i osoby kontaktowej, adresy e-mail,
przypisanie opiekuna, proces leadowy, notatki pod leadem, import paczek, odczyt
i filtry. Pisane dla asystenta AI - zakłada wspólne wzorce z
[ai_integration.md](../ai_integration.md) (zwłaszcza `resolveUserId()`,
`findByName()`, opis `WriteResult` i "Złotą zasadę: nie zgaduj id").

## Pola

Lead zapisuje się przez `LeadInput` (named arguments, `null` = nie wysyłaj pola).
Źródło prawdy: `src/Dto/LeadInput.php`. Poniżej KAŻDE pole zapisu i po co jest.

| pole | typ | po co (i skąd wziąć wartość) |
|---|---|---|
| `title` | string | tytuł leada - JEDYNE pole wymagane przy tworzeniu; krótki opis zapytania, np. "Zapytanie ze strony - Acme" |
| `note` | string | treść notatki/opisu leada |
| `ownerUserId` | int | opiekun leada; z `resolveUserId($client, imie, nazwisko)` - nie wpisuj id z głowy |
| `priority` | int | priorytet: `0` = standard, `1` = wysoki, `2` = najwyższy (domyślnie 0). Inna liczba to 422 - nie zgaduj skali (API >= 2.15.0) |
| `companyName` | string | nazwa firmy leada (dane wolne, bez kartoteki kontrahenta) |
| `taxId` | string | NIP firmy leada |
| `regon` | string | REGON firmy leada |
| `domain` | string | domena/strona firmy, np. `acme.przyklad.example` |
| `firstName` | string | imię osoby kontaktowej |
| `lastName` | string | nazwisko osoby kontaktowej |
| `position` | string | stanowisko osoby kontaktowej |
| `phone` | string | telefon główny |
| `phoneAlternative` | string | telefon dodatkowy |
| `emails` | list<string> | adresy e-mail leada, PIERWSZY = główny (API >= 2.13.0, najwyżej 20). W `create()` lista do założenia - przy trafieniu w istniejącego leada adresy są DOKŁADANE; w `update()` to KOMPLETNA lista docelowa (`[]` usuwa wszystkie) |
| `street` | string | ulica (wraz z numerem) |
| `street2` | string | druga linia adresu |
| `postCode` | string | kod pocztowy |
| `city` | string | miejscowość |
| `region` | string | województwo/region (w zapisie od API 2.15.0) |
| `district` | string | powiat/dzielnica (w zapisie od API 2.15.0) |
| `country` | string | kraj |
| `leadStatusId` | int | PROCES leadowy (nie konkretny etap); z `dictionaries()->leadProcesses()`, dopasuj po nazwie i weź `->id` procesu. Ustawiane TYLKO przy tworzeniu - status istniejącego leada zmienia `changeStatus()` |
| `categoryId` | int | kategoria leada z `dictionaries()->leadCategories()` (w zapisie od API 2.15.0); zdjęcie kategorii wymaga jawnego nulla, czyli tablicy: `update($id, ['categoryId' => null])` |
| `contractorSourceId` | int | źródło pozyskania leada; z `dictionaries()->contractorSources()`. Od API 2.15.0 edytowalne także w `update()` |
| `leadTagIds` | list<int> | tagi leada z `dictionaries()->leadTags()` (API >= 2.15.0, najwyżej 50). W `create()` tagi są DOKŁADANE do trafionego leada; w `update()` to KOMPLETNA lista docelowa (`[]` zdejmuje wszystkie) |
| `customField` | array<string,mixed> | wartości pól niestandardowych, mapa `klucz => wartosc`; klucze z `customFields()` |
| `createdAt` | string | data utworzenia przy imporcie historycznym (ISO 8601); pomiń dla bieżących leadów |
| `creatorUserId` | int | autor przy imporcie historycznym; z `resolveUserId()`. Ustawiane TYLKO przy tworzeniu |

Opcje zapisu NIE są polami leada - idą jako drugi argument `create()`/`upsert()`
w `WriteOptions` (API >= 2.13.0):

| opcja | typ | po co |
|---|---|---|
| `duplicateCheck` | list<string> | pola, po których API szuka istniejącego leada, w kolejności priorytetu: `email` (którykolwiek adres z `emails`), `phone` (porównywany z oboma numerami leada), `taxId`, `domain`, `companyName`, `custom:<klucz>` (pole INT/STR/VARCHAR - klucz integracji). Domyślnie `['email', 'phone']` |
| `allowDuplicates` | bool | `true` = nie szukaj, zawsze nowy lead. Tylko w `create()` - w `upsert()` API odrzuca to błędem 422 |
| `requireDuplicateCheck` | bool | `true` = tryb importu: KAŻDE pole listy (także domyślnej) musi mieć wartość, inaczej 422 |

Odczyt (`Lead`, `src/Dto/Lead.php`) niesie pola, których `LeadInput` NIE przyjmuje
- są ustawiane po stronie CRM. Najważniejsze różnice:

| pole odczytu | typ | znaczenie |
|---|---|---|
| `leadStageId` | ?int | konkretny ETAP (status) w ramach procesu leadowego; wynik pracy z leadem, nie parametr zapisu |
| `statusChangeReasonId` | ?int | powód ostatniej zmiany statusu (słownik `dictionaries()->leadStatusChangeReasons()`) |
| `contractorId` | ?int | kartoteka kontrahenta, jeśli lead został z nią powiązany |
| `contactId` | ?int | osoba kontaktowa (kartoteka), jeśli powiązano |
| `salesPipelineId` | ?int | id szansy sprzedaży utworzonej z leada (patrz playbook pipeline-items) |
| `leadTagIds` | list<int> | tagi leada, priorytet malejąco (to samo pole przyjmuje zapis) |
| `closedAt`, `lastActivityAt`, `updatedAt` | ?string | znaczniki czasu z cyklu życia leada |

## Model danych w skrócie

Lead tworzy się przez `LeadInput`. Przy tworzeniu API WYMAGA jednego pola:

- `title` (string) - tytuł leada.

Cała reszta jest opcjonalna. W praktyce lead ma sens dopiero z danymi firmy
i/lub osoby kontaktowej oraz opiekunem, ale API nie wymusza żadnego z tych pól
- wymusza tylko `title`.

Metody zasobu: `create(LeadInput, WriteOptions)`, `update(int $id, LeadInput)`,
`get(int $id)`, `list(array $filters)`, `iterate(array $filters)`,
`upsert(array $items, WriteOptions)` (paczka do 100 leadów),
`changeStatus(int $id, int $leadStatusId, ?int $statusChangeReasonId, ?string $note)`
(zmiana statusu, API >= 2.15.0) oraz `createNote(int $leadId, NoteInput)`
(notatka pod leadem).

**Create-or-attach (API >= 2.13.0).** `create()` NIE zakłada dubla: API najpierw
szuka istniejącego leada (domyślnie po e-mailu i telefonie z żądania). Gdy
znajdzie, zwraca HTTP 200 z TYM leadem i podpina do niego dane z żądania:

- puste pola uzupełnia, wypełnionych NIE nadpisuje (tytuł istniejącego leada zostaje),
- adresy z `emails` dokłada (adres główny bez zmian),
- telefon wpisuje w wolny numer (główny, potem alternatywny; oba zajęte = ostrzeżenie),
- `customField` NADPISUJE (klucze integracji mają być aktualne),
- tagi z `leadTagIds` DOKŁADA (istniejące zostają - API >= 2.15.0),
- `leadStatusId`, `createdAt` i `creatorUserId` pomija z ostrzeżeniem
  w `->warnings` - lead nie powstaje, więc nie ma czego ustawiać.

`WriteResult` mówi, co się stało: `->created` (`true` = nowy lead, `false` =
podpięto do istniejącego), `->isDuplicate()`, `->matchedBy()` (po czym znaleziono)
i `->id` (id leada - nowego albo istniejącego). Gdy znaleziony lead jest już
skonwertowany na kontrahenta, `$result->duplicate?->raw['contractorId']` wskazuje
tego kontrahenta - to już klient, powiedz o tym użytkownikowi.

Lead bez e-maila i telefonu powstaje normalnie (API tylko ostrzega, że nie miało
po czym szukać). Na instancji starszej niż 2.13.0 wyszukiwania nie ma: każdy
`create()` zakłada nowego leada, a `WriteOptions` odbija się błędem walidacji.

## Mapowanie intencji użytkownika na dane API

| Użytkownik mówi | Potrzebujesz | Skąd wziąć |
|---|---|---|
| "zapytanie od firmy Acme" | `title`, `companyName` | tekst polecenia; `title` to krótki opis, `companyName` to nazwa firmy |
| "NIP 0000000000" | `taxId` | wprost z polecenia |
| "kontakt: Jan Kowalski, tel. ..." | `firstName`, `lastName`, `phone` | wprost z polecenia (to dane wolne, nie kartoteka) |
| "e-mail jan@acme..." | `emails: ['jan@acme...']` | wprost z polecenia; pierwszy adres = główny |
| "opiekun Anna Nowak" | `ownerUserId` | `resolveUserId($client, 'Anna', 'Nowak')` |
| "proces 'Sprzedaz nowy klient'" | `leadStatusId` | `findByName($client->dictionaries()->leadProcesses(), 'Sprzedaz nowy klient')` - to id PROCESU |
| "źródło: strona www" | `contractorSourceId` | słownik źródeł w `dictionaries()`; dopasuj nazwę |
| "z adresem w Warszawie" | `city`, `street`, `postCode` | wprost z polecenia |
| "nawet jeśli już jest, załóż nowy" | `new WriteOptions(allowDuplicates: true)` | tylko na wyraźne życzenie - domyślnie dubla nie zakładaj |
| "dopisz notatkę do leada" | `createNote($leadId, NoteInput)` | typ z `dictionaries()->noteTypes()` |
| "kategoria: kampania wiosenna" | `categoryId` | `findByName($client->dictionaries()->leadCategories(), 'Kampania wiosenna')` |
| "otaguj jako VIP" | `leadTagIds` | `findByName($client->dictionaries()->leadTags(), 'VIP')`; w `update()` podaj KOMPLET tagów |
| "zakwalifikuj leada" / "odrzuć, bo brak budżetu" | `changeStatus($id, $leadStatusId, $reasonId, $note)` | status z `leadProcesses()`, powód z `leadStatusChangeReasons($leadStatusId)` |
| "priorytet wysoki" | `priority: 1` | skala jest stała: 0 standard, 1 wysoki, 2 najwyższy |

## Scenariusz flagowy: lead z zapytania ze strony

Polecenie użytkownika: *"Zarejestruj leada 'Zapytanie ze strony - Acme'. Firma
Acme sp. z o.o., NIP 0000000000, osoba kontaktowa Jan Kowalski, telefon
+48 600 100 200, e-mail jan.kowalski@acme.przyklad.example. Opiekun: Anna Nowak.
Proces leadowy: 'Sprzedaz nowy klient'."*

Twój tok postępowania:

1. Wyłuskaj z polecenia: tytuł, dane firmy, dane osoby, e-mail, opiekuna, nazwę procesu.
2. Rozwiąż opiekuna na `ownerUserId` (odpytując `users()`); przy zerze/wielu
   trafieniach PRZERWIJ i dopytaj, nie zgaduj.
3. Rozwiąż nazwę procesu na `leadStatusId` przez słownik `leadProcesses()`; gdy
   nazwa nie pasuje - dopytaj, nie wstawiaj przypadkowego id.
4. Wyślij leada. API samo sprawdzi, czy lead z tym e-mailem albo telefonem już
   jest - nie szukaj go ręcznie przed zapisem.
5. Zwróć potwierdzenie, rozróżniając "utworzono" od "lead już był".

```php
use TillioCrm\Api\Dto\LeadInput;

// Krok 1: dane z polecenia (Ty je wyłuskujesz z tekstu użytkownika).
$title       = 'Zapytanie ze strony - Acme';
$companyName = 'Acme sp. z o.o.';
$taxId       = '0000000000';
$firstName   = 'Jan';
$lastName    = 'Kowalski';
$phone       = '+48 600 100 200';
$email       = 'jan.kowalski@acme.przyklad.example';

// Krok 2: opiekun leada - rozwiąż nazwisko na id, nie zgaduj.
// resolveUserId() z ai_integration.md: rzuca przy zeru/wielu trafieniach.
$ownerUserId = resolveUserId($client, 'Anna', 'Nowak');

// Krok 3: proces leadowy z nazwy. leadProcesses() zwraca listę procesów
// {id, name, statuses[...]}; findByName() dopasuje po nazwie i zwróci id PROCESU
// (to jest leadStatusId), a nie id pojedynczego etapu.
$leadStatusId = findByName($client->dictionaries()->leadProcesses(), 'Sprzedaz nowy klient');
if ($leadStatusId === null) {
    // Nazwa procesu spoza słownika tej instancji - dopytaj, nie wstawiaj losowego id.
    throw new RuntimeException("Nie znaleziono procesu leadowego o tej nazwie - dopytaj uzytkownika, ktory proces wybrac.");
}

// Krok 4: zapis. Wymagany jest tylko title, resztę dokładamy z polecenia.
// API >= 2.13.0 szuka istniejącego leada po e-mailu i telefonie (domyślnie).
$result = $client->leads()->create(new LeadInput(
    title:        $title,
    companyName:  $companyName,
    taxId:        $taxId,
    firstName:    $firstName,
    lastName:     $lastName,
    phone:        $phone,
    emails:       [$email],
    ownerUserId:  $ownerUserId,
    leadStatusId: $leadStatusId,
));

// Krok 5: potwierdzenie - created mówi, czy lead jest nowy.
if ($result->created) {
    echo "Utworzono leada #{$result->id}: {$title}.\n";
} else {
    // Lead z tym e-mailem/telefonem już był - API podpięło do niego dane.
    echo "Lead juz istnial (#{$result->id}, dopasowany po: {$result->matchedBy()}) - uzupelniono dane.\n";
    $contractorId = $result->duplicate?->raw['contractorId'] ?? null;
    if ($contractorId !== null) {
        echo "Uwaga: ten lead jest juz klientem - kontrahent #{$contractorId}.\n";
    }
}
```

Co zwrócić użytkownikowi: numer leada (`$result->id`), nazwę firmy i opiekuna
oraz to, czy lead jest nowy. Przy trafieniu w istniejącego leada proces
(`leadStatusId`) NIE został ustawiony - `->warnings` mówi o tym wprost, pokaż to
użytkownikowi razem z pozostałymi ostrzeżeniami.

## Warianty

### Formularz albo integracja: własny klucz zamiast e-maila

Lead nie ma `externalId` - klucz integracji (np. id zgłoszenia z formularza)
trzyma się w polu niestandardowym leada typu INT/STR/VARCHAR.

```php
use TillioCrm\Api\Dto\LeadInput;
use TillioCrm\Api\Dto\WriteOptions;

// Najpierw szukamy po kluczu integracji, potem po e-mailu. SDK wymaga, żeby
// KAŻDE pole z duplicateCheck miało wartość w żądaniu - inaczej rzuci
// IncompleteDuplicateCheckException, zanim żądanie wyjdzie.
$result = $client->leads()->create(
    new LeadInput(
        title: 'Formularz kontaktowy - Acme',
        emails: ['jan.kowalski@acme.przyklad.example'],
        customField: ['zapier_id' => 'ZAP-1042'],
    ),
    new WriteOptions(duplicateCheck: ['custom:zapier_id', 'email']),
);
```

### Import paczki leadów

```php
use TillioCrm\Api\Dto\LeadInput;

// Do 100 leadów w jednym żądaniu; większą listę dziel sam (UpsertResult ma
// withIndexOffset() i merge() do scalania wyników paczek).
$batch = $client->leads()->upsert([
    new LeadInput(title: 'Acme - targi', phone: '+48 600 100 200'),
    new LeadInput(title: 'Beta - targi', emails: ['biuro@beta.przyklad.example']),
]);

// HTTP jest zawsze 200 - wynik siedzi per pozycja. Sprawdzaj hasFailures().
echo "Nowe: {$batch->createdCount()}, podpiete do istniejacych: {$batch->attachedCount()}\n";
foreach ($batch->failed() as $row) {
    echo "Pozycja {$row['index']} odrzucona: " . json_encode($row['errors']) . "\n";
}
```

### Notatka pod leadem (API >= 2.13.0)

```php
use TillioCrm\Api\Dto\NoteInput;

$noteTypeId = findByName($client->dictionaries()->noteTypes(), 'Rozmowa telefoniczna');
if ($noteTypeId === null) {
    throw new RuntimeException('Nie znaleziono typu notatki - dopytaj, ktory uzyc.');
}

// Wymagane noteTypeId i title, jak przy notatce kontrahenta.
$result = $client->leads()->createNote($leadId, new NoteInput(
    noteTypeId: $noteTypeId,
    title: 'Rozmowa kwalifikacyjna',
    body: '<p>Prosi o wycene na 10 stanowisk.</p>',
));
echo "Zapisano notatke #{$result->id} pod leadem #{$leadId}.\n";
```

Notatka należy do leada (`contractorId` zostaje `null`, także gdy lead ma już
kontrahenta) - przy konwersji leada CRM sam przepina jego notatki. `contactIds`,
`serviceId` i `pipelineItemId` nie są tu obsługiwane (422). Odczyt:
`notes()->list(['leadId' => $leadId])`.

### Zmiana statusu leada: kwalifikacja i dyskwalifikacja (API >= 2.15.0)

Statusu NIE zmienia się przez `update()` - PUT odbija `leadStatusId` błędem 422
`body.fieldNotUpdatable`. W CRM to proces z historią, więc ma własną metodę:
CRM dopisuje wpis do historii, ustawia `closedAt` i grupę statusów.

```php
// Krok 1: status docelowy z procesu leadowego. Powód i notatkę przyjmują
// WYŁĄCZNIE statusy kończące - type `qualified` albo `disqualified`.
$target = null;
foreach ($client->dictionaries()->leadProcesses() as $process) {
    foreach ($process->statuses as $status) {
        if (mb_strtolower((string) $status->name) === mb_strtolower('Zdyskwalifikowany')) {
            $target = $status;
            break 2;
        }
    }
}
if ($target === null) {
    throw new RuntimeException('Nie znaleziono statusu - dopytaj uzytkownika, ktory wybrac.');
}

// Krok 2: powód zmiany ze słownika TEGO statusu. noteRequired mówi, czy
// notatka jest obowiązkowa - bez niej API odrzuci zapis błędem 422.
$reason = null;
foreach ($client->dictionaries()->leadStatusChangeReasons($target->id) as $candidate) {
    if ($candidate->active === true && mb_strtolower((string) $candidate->name) === mb_strtolower('Brak budzetu')) {
        $reason = $candidate;
        break;
    }
}

$note = $reason?->noteRequired === true ? 'Klient odlozyl decyzje na przyszly rok.' : null;

// Krok 3: zmiana. Przy statusie NIEKOŃCZĄCYM (type `default`) powód i notatka
// to 422 - wtedy wywołaj changeStatus($id, $target->id) bez nich.
$result = $client->leads()->changeStatus(42, $target->id, $reason?->id, $note);

// data to lead PO zmianie - dokładnie jak z leads()->get().
$lead = TillioCrm\Api\Dto\Lead::fromArray($result->data);
echo "Lead #{$lead->id} ma status {$lead->leadStatusId} (zamkniety: {$lead->closedAt}).\n";
```

### Kategoria i tagi leada (API >= 2.15.0)

```php
use TillioCrm\Api\Dto\LeadInput;

// Tagi w create() są DOKŁADANE do trafionego leada, w update() ZASTĘPUJĄ listę.
// Chcesz dopisać tag do istniejącego leada - przekaż komplet, tak jak przy emails.
$lead = $client->leads()->get(42);
$client->leads()->update(42, new LeadInput(
    leadTagIds: [...$lead->leadTagIds, $vipTagId],
    categoryId: $categoryId,
));

// Pusta lista zdejmuje wszystkie tagi.
$client->leads()->update(42, new LeadInput(leadTagIds: []));

// Zdjęcie kategorii wymaga jawnego nulla - LeadInput pomija null-e, więc tablica.
$client->leads()->update(42, ['categoryId' => null]);
```

### Adresy e-mail: dopisanie a wymiana

```php
use TillioCrm\Api\Dto\LeadInput;

// update() WYMIENIA listę na podaną - żeby dopisać adres, przekaż komplet,
// inaczej dotychczasowe adresy znikną. Pierwszy element = adres główny.
$lead = $client->leads()->get(42);
$client->leads()->update(42, new LeadInput(
    emails: [...$lead->emails, 'nowy@acme.przyklad.example'],
));

// Pusta lista usuwa wszystkie adresy.
$client->leads()->update(42, new LeadInput(emails: []));
```

### Odczyt i filtrowanie listy

```php
// Leady jednego opiekuna, zmienione po dacie - filtry wg kontraktu zasobu Leads.
$page = $client->leads()->list([
    'ownerUserId'  => 7,
    'updatedAfter' => '2026-08-01T00:00:00+02:00',
    'limit'        => 50,
]);
foreach ($page as $lead) {
    echo "#{$lead->id} {$lead->title} (etap: {$lead->leadStageId})\n";
}
```

Dostępne filtry (komplet wg kontraktu): `leadStatusId`, `leadStageId`,
`ownerUserId`, `title`, `taxId`, `id`, `statusChangeReasonId`, `categoryId`,
`leadTagId` (jeden tag na żądanie), `contractorSourceId`, `priority`,
`creatorUserId`, `contractorId`, `contactId`,
`salesPipelineId`, `companyName`, `regon`, `domain`, `firstName`, `lastName`,
`position`, `phone`, `phoneAlternative`, `street`, `postCode`, `city`, `region`,
`district`, `country`, `updatedAfter`/`updatedBefore`,
`createdAfter`/`createdBefore`,
`customField[klucz]`, `sort`/`sortDir`, `page`/`limit` (`leadTagId` i `district`
wymagają API >= 2.15.0). Do pełnego przebiegu
wszystkich stron użyj `iterate()` (wymusza `sort=id`, nie gubi rekordów - patrz
[queries](../queries/README.md)).

### Zmiana opiekuna albo danych istniejącego leada

```php
// update() wysyła tylko pola podane w input; reszta zostaje bez zmian.
$client->leads()->update(42, new LeadInput(
    ownerUserId: resolveUserId($client, 'Piotr', 'Wisniewski'),
));
```

### Podejrzenie procesów i ich etapów

```php
// Zanim ustawisz leadStatusId, możesz pokazać użytkownikowi dostępne procesy
// i ich etapy (statusy), żeby wybrał świadomie.
foreach ($client->dictionaries()->leadProcesses() as $process) {
    echo "Proces #{$process->id}: {$process->name}\n";
    foreach ($process->statuses as $stage) {
        echo "  - etap #{$stage->id}: {$stage->name} ({$stage->type})\n";
    }
}
```

## Pułapki

- **`title` jest jedynym polem wymaganym.** Brak `title` = 422. Wszystko inne
  opcjonalne, ale lead bez danych firmy/osoby jest bezużyteczny - dołóż to, co
  podał użytkownik.
- **`create()` nie zawsze tworzy (API >= 2.13.0).** Lead z tym samym e-mailem
  albo telefonem już istnieje → HTTP 200, `->created === false`, dane podpięte do
  istniejącego. Nie mów "utworzono", gdy `created` jest `false`, i nie szukaj
  leada ręcznie przed zapisem - API robi to samo, w jednym żądaniu.
- **Przy podpięciu proces NIE wchodzi.** `leadStatusId`, `createdAt`
  i `creatorUserId` działają tylko przy tworzeniu - przy trafieniu w istniejącego
  leada lądują w `->warnings`. `contractorSourceId` (od API 2.15.0 edytowalny też
  w `update()`) podlega regule "uzupełnia puste", a istniejący lead źródło ma
  zawsze, więc i tak zostaje bez zmian.
- **Skonwertowany lead to już klient.** `$result->duplicate?->raw['contractorId']`
  niepuste = lead został kontrahentem. Nie zakładaj nowego leada na siłę
  (`allowDuplicates`) - zapytaj użytkownika, co zrobić.
- **`emails` działa różnie w `create()` i `update()`.** Przy podpięciu adresy są
  DOKŁADANE, a `update()` WYMIENIA całą listę - pominięte adresy znikają, `[]`
  czyści wszystkie.
- **Klucz integracji to pole niestandardowe.** Lead nie ma `externalId`;
  `duplicateCheck: ['custom:<klucz>']` działa tylko dla pól INT/STR/VARCHAR.
- **Pole z `duplicateCheck` musi mieć wartość.** SDK zatrzymuje zapis lokalnie
  (`IncompleteDuplicateCheckException`), gdy jawnie wskazane pole jest puste -
  dla `email` liczy się lista `emails` (pusta = brak wartości).
- **`leadStatusId` to PROCES, nie etap.** W input ustawiasz proces leadowy
  (id z `leadProcesses()`). Konkretny ETAP (`leadStageId`) jest polem ODCZYTU -
  wynikiem pracy z leadem w CRM, nie parametrem zapisu. `LeadInput` w ogóle nie
  ma pola `leadStageId`.
- **Nie zgaduj `ownerUserId`.** Kilka osób może mieć to samo nazwisko -
  `resolveUserId()` celowo rzuca przy wielu trafieniach. Dopytaj o e-mail.
- **`priority` to enum `0|1|2`** (0 standard, 1 wysoki, 2 najwyższy; API >= 2.15.0).
  Inna liczba to 422 `body.invalidValue` przed zapisem. Ta sama skala obowiązuje
  w zgłoszeniach i zadaniach.
- **Statusu nie zmienia `update()`.** `leadStatusId` w PUT to 422
  `body.fieldNotUpdatable` - od zmiany jest `changeStatus()`. Powód i notatkę
  przyjmują wyłącznie statusy kończące; przy statusie `default` oba dają 422.
- **Tagi: `create()` dokłada, `update()` wymienia.** Jak przy `emails` - żeby
  dopisać tag istniejącemu leadowi, przekaż KOMPLET, inaczej pozostałe znikną.
- **Zdjęcie kategorii przez tablicę.** `new LeadInput(categoryId: null)` nie
  wyśle pola (null = "nie wysyłaj"); zdejmuje ją dopiero
  `update($id, ['categoryId' => null])`.
- **Numer telefonu musi być prawdziwy.** Od API 2.15.0 numer niepoprawny wg
  libphonenumber (za krótki, za długi, z doklejonym numerem wewnętrznym) NIE
  zapisuje się - wraca w `->warnings.phone`, a lead powstaje bez telefonu.
  Dotyczy też wyszukiwania duplikatu po `phone`.
- **Odczyt vs zapis.** `Lead` (odczyt) ma pola nieobecne w `LeadInput`
  (`leadStageId`, `statusChangeReasonId`, `contractorId`, `contactId`,
  `salesPipelineId`, znaczniki czasu) - ustawia je CRM.
- **`create()` zwraca `WriteResult`** (`->id`, `->created`, `->warnings`,
  `isDuplicate()`), nie samo id; `upsert()` zwraca `UpsertResult`. Szczegóły:
  sekcja "Co zwracają zapisy" w [ai_integration.md](../ai_integration.md).
