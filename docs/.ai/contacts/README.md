# Playbook: Osoby kontaktowe (contacts)

Realizacja poleceń użytkownika dotyczących osób kontaktowych: dodawanie osoby,
przypięcie do kartoteki kontrahenta, dane kontaktowe, wykrywanie duplikatów
(upsert), odczyt i filtry. Pisane dla asystenta AI - zakłada wspólne wzorce z
[ai_integration.md](../ai_integration.md) (zwłaszcza `resolveUserId()`,
`findByName()`, opis `WriteResult`, `IncompleteDuplicateCheckException`
i "Złotą zasadę: nie zgaduj id").

## Pola

Osobę zapisuje się przez `ContactInput` (named arguments, `null` = nie wysyłaj
pola). Opcje zapisu (wykrywanie duplikatów) idą OBOK, w `WriteOptions`. Źródła
prawdy: `src/Dto/ContactInput.php`, `src/Dto/WriteOptions.php`. Poniżej KAŻDE pole.

`ContactInput`:

| pole | typ | po co (i skąd wziąć wartość) |
|---|---|---|
| `firstName` | string | imię - JEDYNE pole wymagane przy tworzeniu |
| `lastName` | string | nazwisko |
| `position` | string | stanowisko w firmie |
| `email` | string | adres e-mail; naturalny klucz duplikatu (patrz `duplicateCheck`) |
| `phone` | string | telefon główny |
| `phoneAlternative` | string | telefon dodatkowy |
| `note` | string | notatka o osobie |
| `contactStatusId` | int | status osoby; z odpowiedniego słownika `dictionaries()`, dopasuj nazwę |
| `ownerUserId` | int | opiekun osoby; z `resolveUserId($client, imie, nazwisko)` |
| `externalId` | string | identyfikator z systemu zewnętrznego (import/integracja) |
| `contractorId` | int | kartoteka kontrahenta, do której osoba należy; z `contractors()->list(['name' => ...])` |
| `customField` | array<string,mixed> | wartości pól niestandardowych, mapa `klucz => wartosc`; klucze z `customFields()` |
| `createdAt` | string | data utworzenia przy imporcie historycznym (ISO 8601) |
| `creatorUserId` | int | autor przy imporcie historycznym; z `resolveUserId()`. Tylko przy tworzeniu |

`WriteOptions` (opcje zapisu, jadą w payloadzie obok pól osoby; `null` = nie
wysyłaj opcji, API użyje domyślnego zachowania). Dla kontaktów istotne są:

| pole | typ | po co |
|---|---|---|
| `duplicateCheck` | list<string> | pola, po których szukać istniejącej osoby (np. `['email']`, `['externalId']`). KAŻDE wskazane pole MUSI mieć wartość w input - SDK pilnuje tego lokalnie i rzuca `IncompleteDuplicateCheckException` |
| `allowDuplicates` | bool | `true` = nie szukaj duplikatu, zawsze twórz nową osobę |
| `requireDuplicateCheck` | bool | `true` = odmów zapisu, gdy żadnego pola domyślnego zestawu nie da się sprawdzić |

Pozostałe pola `WriteOptions` (`taxIdLookup`, `failOnInvalidTaxId`,
`createSystemNote`, `createContractorContacts`) dotyczą kontrahentów, nie osób
- dla kontaktów je pomijaj.

Odczyt (`Contact`, `src/Dto/Contact.php`) różni się od zapisu:

| pole odczytu | typ | znaczenie |
|---|---|---|
| `name` | ?string | pełna nazwa (imię + nazwisko) złożona przez CRM; w zapisie podajesz `firstName`/`lastName` osobno |
| `contractorId` | ?int | GŁÓWNA kartoteka osoby |
| `contractorIds` | list<int> | WSZYSTKIE kartoteki, do których osoba jest przypięta (osoba może należeć do wielu firm) |
| `lastActivityAt`, `updatedAt` | ?string | znaczniki czasu z cyklu życia osoby |

## Model danych w skrócie

Osobę tworzy się przez `create(ContactInput, WriteOptions)`. Przy tworzeniu API
WYMAGA jednego pola:

- `firstName` (string) - imię.

Reszta opcjonalna. W praktyce osoba niemal zawsze idzie z `contractorId`
(przypięcie do firmy) oraz `email`/`phone`.

Metody zasobu: `create(ContactInput, WriteOptions)`, `update(int $id, ContactInput)`,
`get(int $id)`, `list(array $filters)`, `iterate(array $filters)` oraz
`upsert(array $items, WriteOptions)` (paczka).

Inaczej niż leady i szanse, kontakty MAJĄ wykrywanie duplikatów. W `create()`
z `duplicateCheck` trafienie w istniejącą osobę zwraca HTTP 200 z tym rekordem
(`WriteResult->created === false`, `->isDuplicate() === true`). W `upsert()`
status pozycji to `attached` (nie `updated`) - dane zostają DOPIĘTE do istniejącej
osoby, a nie nadpisane.

## Mapowanie intencji użytkownika na dane API

| Użytkownik mówi | Potrzebujesz | Skąd wziąć |
|---|---|---|
| "dodaj Jana Kowalskiego" | `firstName`, `lastName` | wprost z polecenia |
| "u kontrahenta Acme" | `contractorId` | `contractors()->list(['name' => 'Acme', 'limit' => 1])->first()` |
| "mail jan@acme.przyklad.example" | `email` | wprost z polecenia |
| "nie dubluj po mailu" | `WriteOptions(duplicateCheck: ['email'])` | e-mail musi być podany w input |
| "opiekun Anna Nowak" | `ownerUserId` | `resolveUserId($client, 'Anna', 'Nowak')` |
| "status Aktywny" | `contactStatusId` | słownik statusów kontaktów w `dictionaries()`, dopasuj nazwę |
| "zawsze twórz nową" | `WriteOptions(allowDuplicates: true)` | flaga w opcjach |

## Scenariusz flagowy: osoba z kontrolą duplikatu po e-mailu

Polecenie użytkownika: *"Dodaj osobę kontaktową Jan Kowalski, mail
jan.kowalski@acme.przyklad.example, telefon +48 600 100 200, u kontrahenta Acme.
Nie zakładaj duplikatu, jeśli ktoś z tym mailem już jest."*

Twój tok postępowania:

1. Wyłuskaj z polecenia: imię, nazwisko, e-mail, telefon, kontrahenta, intencję
   niedublowania po mailu.
2. Rozwiąż kontrahenta na `contractorId` (odpytując `contractors()`); przy braku
   trafienia PRZERWIJ i dopytaj albo zaproponuj założenie kartoteki.
3. Ustaw `duplicateCheck: ['email']` - i UPEWNIJ SIĘ, że `email` jest w input
   (inaczej SDK rzuci `IncompleteDuplicateCheckException` zanim żądanie wyjdzie).
4. Wywołaj `create()` i rozróżnij w potwierdzeniu: utworzono nową (`created`)
   czy trafiono w istniejącą (`isDuplicate()`, status "attached").

```php
use TillioCrm\Api\Dto\ContactInput;
use TillioCrm\Api\Dto\WriteOptions;

// Krok 1: dane z polecenia (Ty je wyłuskujesz z tekstu użytkownika).
$firstName = 'Jan';
$lastName  = 'Kowalski';
$email     = 'jan.kowalski@acme.przyklad.example';
$phone     = '+48 600 100 200';

// Krok 2: kontrahent - rozwiąż nazwę na id, nie zgaduj.
$contractor = $client->contractors()->list(['name' => 'Acme', 'limit' => 1])->first();
if ($contractor === null) {
    // Bez kartoteki osoba nie ma do czego się przypiąć - dopytaj albo zaloz kontrahenta.
    throw new RuntimeException('Nie znaleziono kontrahenta Acme - dopytaj albo zaloz kartoteke.');
}

// Krok 3 + 4: tworzenie z kontrolą duplikatu po e-mailu.
// duplicateCheck: ['email'] wymaga, żeby email był w input - jest. Gdyby go
// zabrakło, create() rzuciłby IncompleteDuplicateCheckException LOKALNIE,
// zanim żądanie poszłoby do API (patrz ai_integration.md, sekcja Obsługa błędów).
$result = $client->contacts()->create(
    new ContactInput(
        firstName:    $firstName,
        lastName:     $lastName,
        email:        $email,
        phone:        $phone,
        contractorId: $contractor->id,
    ),
    new WriteOptions(duplicateCheck: ['email']),
);

// WriteResult rozróżnia kreację od trafienia w duplikat:
// ->created === true  -> utworzono nową osobę (HTTP 201),
// ->isDuplicate()     -> trafiono w istniejącą (HTTP 200), dane dopięte ("attached").
if ($result->isDuplicate()) {
    echo "Osoba juz istniala (#{$result->id}), dopieto dane - dopasowano po: {$result->matchedBy()}.\n";
} else {
    echo "Utworzono osobe #{$result->id}: {$firstName} {$lastName}.\n";
}
```

Co zwrócić użytkownikowi: numer osoby (`$result->id`) oraz JEDNOZNACZNIE, czy
powstała nowa, czy trafiono w istniejącą (i po którym polu - `matchedBy()`).
`WriteResult->warnings` (ciche korekty normalizacji) - jeśli niepuste, pokaż je.

## Warianty

### Upsert paczki osób (import listy)

```php
use TillioCrm\Api\Dto\ContactInput;
use TillioCrm\Api\Dto\WriteOptions;

// upsert() przetwarza paczkę; HTTP zawsze 200, statusy siedzą per pozycja.
// duplicateCheck: ['email'] wymaga e-maila w KAŻDEJ pozycji - SDK sprawdza to
// lokalnie i rzuci IncompleteDuplicateCheckException, jeśli któraś go nie ma.
$result = $client->contacts()->upsert(
    [
        new ContactInput(firstName: 'Jan',  lastName: 'Kowalski', email: 'jan@acme.przyklad.example',  contractorId: 42),
        new ContactInput(firstName: 'Anna', lastName: 'Nowak',    email: 'anna@acme.przyklad.example', contractorId: 42),
    ],
    new WriteOptions(duplicateCheck: ['email']),
);

// ZAWSZE sprawdź hasFailures() - kod HTTP 200 tego nie powie.
echo "Utworzono: {$result->createdCount()}, dopieto: {$result->attachedCount()}, bledy: {$result->failedCount()}.\n";
if ($result->hasFailures()) {
    foreach ($result->failed() as $row) {
        echo "  pozycja #{$row['index']} nie zapisana\n";   // szczegóły w $row['errors']
    }
}
```

### Odczyt i filtrowanie listy

```php
// Osoby jednej kartoteki - filtry wg kontraktu zasobu Contacts.
$page = $client->contacts()->list([
    'contractorId' => 42,
    'limit'        => 50,
]);
foreach ($page as $contact) {
    echo "#{$contact->id} {$contact->name} <{$contact->email}>\n";
}
```

Dostępne filtry (komplet wg kontraktu): `contractorId`, `email`, `name`,
`contactStatusId`, `updatedAfter`/`updatedBefore`,
`createdAfter`/`createdBefore`, `customField[klucz]`, `sort`/`sortDir`,
`page`/`limit`. Do pełnego przebiegu wszystkich stron użyj `iterate()`
(wymusza `sort=id` - patrz [queries](../queries/README.md)).

### Notatka pod osobą kontaktową (API >= 2.10.0)

Notatkę można powiesić wprost pod osobą przez `createNote($contactId, NoteInput)`.
Tak jak notatka kontrahenta WYMAGA `noteTypeId` i `title` (typ ze słownika
`noteTypes()`). Bez `contractorId` notatka trafia pod kontrahenta GŁÓWNEGO tego
kontaktu; z `contractorId` (musi być jednym z kontrahentów kontaktu) wisi na
firmie i osobie naraz. Zwraca `WriteResult` z `noteId` w `->id`.

```php
use TillioCrm\Api\Dto\NoteInput;

// Notatka wprost pod osoba kontaktowa (API >= 2.10.0). Wymaga noteTypeId i title
// tak jak notatka kontrahenta. noteTypeId ze slownika noteTypes().
$noteTypeId = findByName($client->dictionaries()->noteTypes(), 'Rozmowa telefoniczna');
if ($noteTypeId === null) {
    throw new RuntimeException('Nie znaleziono typu notatki - dopytaj, ktory uzyc.');
}

// Bez contractorId notatka wisi pod kontrahentem GLOWNYM tego kontaktu.
$result = $client->contacts()->createNote($contactId, new NoteInput(
    noteTypeId: $noteTypeId,
    title: 'Rozmowa telefoniczna',
    body: 'Klient prosi o oferte.',
));

// Z contractorId (jeden z kontrahentow kontaktu) notatka wisi na FIRMIE i OSOBIE.
$result = $client->contacts()->createNote($contactId, new NoteInput(
    noteTypeId: $noteTypeId,
    title: 'Rozmowa telefoniczna',
    contractorId: $contractor->id,   // musi byc kontrahentem tego kontaktu
));

echo "Zapisano notatke #{$result->id} pod kontaktem #{$contactId}.\n";
```

### Aktualizacja istniejącej osoby

```php
// update() wysyła tylko podane pola; NIE wykrywa duplikatów (to zwykły PUT).
$client->contacts()->update(7, new ContactInput(
    phone:       '+48 600 200 300',
    ownerUserId: resolveUserId($client, 'Piotr', 'Wisniewski'),
));
```

## Pułapki

- **`firstName` jest jedynym polem wymaganym.** Brak `firstName` = 422. Reszta
  opcjonalna, ale osoba bez `contractorId` nie jest przypięta do żadnej firmy.
- **`duplicateCheck` wymaga wartości w polach, po których sprawdza.** Podałeś
  `duplicateCheck: ['email']`, ale nie ustawiłeś `email`? SDK rzuci
  `IncompleteDuplicateCheckException` LOKALNIE, zanim żądanie wyjdzie. Uzupełnij
  pole albo zdejmij je z `duplicateCheck`.
- **Trafienie w duplikat to "attached", nie "updated".** W `create()`
  `->isDuplicate()` znaczy: znaleziono istniejącą osobę i DOPIĘTO do niej dane,
  nie nadpisano jej. W `upsert()` ten status nazywa się `attached`. Nie mów
  użytkownikowi "zaktualizowano" - powiedz "dopięto do istniejącej osoby".
- **`upsert()` zawsze zwraca HTTP 200 - sprawdzaj `hasFailures()`.** Pozycje mogą
  polec pojedynczo (`failed`), a kod HTTP tego nie zdradzi. Zawsze zajrzyj do
  `failed()`/`hasFailures()`.
- **`WriteOptions` idą jako drugi argument `create()`/`upsert()`, nie w
  `ContactInput`.** To osobny obiekt obok pól osoby.
- **Osoba może należeć do wielu kartotek.** Odczyt ma `contractorId` (główna)
  i `contractorIds` (wszystkie). Nie zakładaj, że osoba jest w jednej firmie.
- **Nie zgaduj `contractorId` ani `ownerUserId`.** Rozwiąż nazwę na id przez
  odczyt; przy zerze/wielu trafieniach dopytaj.
- **Odczyt vs zapis.** `Contact` (odczyt) ma `name` (złożone przez CRM),
  `contractorIds`, `lastActivityAt` - w zapisie podajesz `firstName`/`lastName`
  osobno i pojedynczy `contractorId`.
- **`create()`/`update()` zwracają `WriteResult`, `upsert()` zwraca
  `UpsertResult`.** Szczegóły: sekcja "Co zwracają zapisy" w
  [ai_integration.md](../ai_integration.md).
- **Notatka pod osobą wymaga API >= 2.10.0.** `createNote()` na starszej instancji
  zwróci `FeatureNotSupportedException` (501) - nie ponawiaj, zaktualizuj CRM.
  Wymaga `noteTypeId` i `title`; `contractorId` musi być jednym z kontrahentów
  tego kontaktu (obcy = błąd). Domyślne wieszanie pod kontrahentem głównym bez
  `contractorId` od API 2.11.0.
