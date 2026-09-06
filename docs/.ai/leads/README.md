# Playbook: Leady (leads)

Realizacja poleceń użytkownika dotyczących leadów: rejestrowanie zapytań, dane
firmy i osoby kontaktowej, przypisanie opiekuna, proces leadowy, odczyt i filtry.
Pisane dla asystenta AI - zakłada wspólne wzorce z
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
| `priority` | int | priorytet; kontrakt nie definiuje skali dla tej instancji - jeśli nie znasz mapowania, pomiń albo dopytaj, nie zgaduj liczby |
| `companyName` | string | nazwa firmy leada (dane wolne, bez kartoteki kontrahenta) |
| `taxId` | string | NIP firmy leada |
| `regon` | string | REGON firmy leada |
| `domain` | string | domena/strona firmy, np. `acme.przyklad.example` |
| `firstName` | string | imię osoby kontaktowej |
| `lastName` | string | nazwisko osoby kontaktowej |
| `position` | string | stanowisko osoby kontaktowej |
| `phone` | string | telefon główny |
| `phoneAlternative` | string | telefon dodatkowy |
| `street` | string | ulica (wraz z numerem) |
| `street2` | string | druga linia adresu |
| `postCode` | string | kod pocztowy |
| `city` | string | miejscowość |
| `country` | string | kraj |
| `leadStatusId` | int | PROCES leadowy (nie konkretny etap); z `dictionaries()->leadProcesses()`, dopasuj po nazwie i weź `->id` procesu. Ustawiane TYLKO przy tworzeniu |
| `contractorSourceId` | int | źródło pozyskania leada; z odpowiedniego słownika `dictionaries()`. Ustawiane TYLKO przy tworzeniu |
| `customField` | array<string,mixed> | wartości pól niestandardowych, mapa `klucz => wartosc`; klucze z `customFields()` |
| `createdAt` | string | data utworzenia przy imporcie historycznym (ISO 8601); pomiń dla bieżących leadów |
| `creatorUserId` | int | autor przy imporcie historycznym; z `resolveUserId()`. Ustawiane TYLKO przy tworzeniu |

Odczyt (`Lead`, `src/Dto/Lead.php`) niesie pola, których `LeadInput` NIE przyjmuje
- są ustawiane po stronie CRM. Najważniejsze różnice:

| pole odczytu | typ | znaczenie |
|---|---|---|
| `leadStageId` | ?int | konkretny ETAP (status) w ramach procesu leadowego; wynik pracy z leadem, nie parametr zapisu |
| `statusChangeReasonId` | ?int | powód ostatniej zmiany statusu |
| `categoryId` | ?int | kategoria leada |
| `contractorId` | ?int | kartoteka kontrahenta, jeśli lead został z nią powiązany |
| `contactId` | ?int | osoba kontaktowa (kartoteka), jeśli powiązano |
| `salesPipelineId` | ?int | id szansy sprzedaży utworzonej z leada (patrz playbook pipeline-items) |
| `emails` | list<string> | adresy e-mail leada (odczyt; zapis idzie polami firmy/osoby) |
| `region`, `district` | ?string | region i powiat (uzupełniane przez CRM) |
| `closedAt`, `lastActivityAt`, `updatedAt` | ?string | znaczniki czasu z cyklu życia leada |

## Model danych w skrócie

Lead tworzy się przez `LeadInput`. Przy tworzeniu API WYMAGA jednego pola:

- `title` (string) - tytuł leada.

Cała reszta jest opcjonalna. W praktyce lead ma sens dopiero z danymi firmy
i/lub osoby kontaktowej oraz opiekunem, ale API nie wymusza żadnego z tych pól
- wymusza tylko `title`.

Metody zasobu: `create(LeadInput)`, `update(int $id, LeadInput)`, `get(int $id)`,
`list(array $filters)`, `iterate(array $filters)`. NIE ma metody `upsert` - lead
nie ma wbudowanego wykrywania duplikatów (inaczej niż kontrahenci czy kontakty).
Dwa razy wywołany `create()` da dwa leady.

## Mapowanie intencji użytkownika na dane API

| Użytkownik mówi | Potrzebujesz | Skąd wziąć |
|---|---|---|
| "zapytanie od firmy Acme" | `title`, `companyName` | tekst polecenia; `title` to krótki opis, `companyName` to nazwa firmy |
| "NIP 0000000000" | `taxId` | wprost z polecenia |
| "kontakt: Jan Kowalski, tel. ..." | `firstName`, `lastName`, `phone` | wprost z polecenia (to dane wolne, nie kartoteka) |
| "opiekun Anna Nowak" | `ownerUserId` | `resolveUserId($client, 'Anna', 'Nowak')` |
| "proces 'Sprzedaz nowy klient'" | `leadStatusId` | `findByName($client->dictionaries()->leadProcesses(), 'Sprzedaz nowy klient')` - to id PROCESU |
| "źródło: strona www" | `contractorSourceId` | słownik źródeł w `dictionaries()`; dopasuj nazwę |
| "z adresem w Warszawie" | `city`, `street`, `postCode` | wprost z polecenia |

## Scenariusz flagowy: lead z zapytania ze strony

Polecenie użytkownika: *"Zarejestruj leada 'Zapytanie ze strony - Acme'. Firma
Acme sp. z o.o., NIP 0000000000, osoba kontaktowa Jan Kowalski, telefon
+48 600 100 200. Opiekun: Anna Nowak. Proces leadowy: 'Sprzedaz nowy klient'."*

Twój tok postępowania:

1. Wyłuskaj z polecenia: tytuł, dane firmy, dane osoby, opiekuna, nazwę procesu.
2. Rozwiąż opiekuna na `ownerUserId` (odpytując `users()`); przy zerze/wielu
   trafieniach PRZERWIJ i dopytaj, nie zgaduj.
3. Rozwiąż nazwę procesu na `leadStatusId` przez słownik `leadProcesses()`; gdy
   nazwa nie pasuje - dopytaj, nie wstawiaj przypadkowego id.
4. Utwórz leada i zwróć potwierdzenie z id.

```php
use TillioCrm\Api\Dto\LeadInput;

// Krok 1: dane z polecenia (Ty je wyłuskujesz z tekstu użytkownika).
$title       = 'Zapytanie ze strony - Acme';
$companyName = 'Acme sp. z o.o.';
$taxId       = '0000000000';
$firstName   = 'Jan';
$lastName    = 'Kowalski';
$phone       = '+48 600 100 200';

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

// Krok 4: utworzenie leada. Wymagany jest tylko title, resztę dokładamy z polecenia.
// create() zwraca WriteResult: ->id (id leada), ->created, ->warnings.
$result = $client->leads()->create(new LeadInput(
    title:        $title,
    companyName:  $companyName,
    taxId:        $taxId,
    firstName:    $firstName,
    lastName:     $lastName,
    phone:        $phone,
    ownerUserId:  $ownerUserId,
    leadStatusId: $leadStatusId,
));

// Potwierdzenie dla użytkownika:
echo "Utworzono leada #{$result->id}: {$title}.\n";
```

Co zwrócić użytkownikowi: numer leada (`$result->id`), nazwę firmy i opiekuna.
`WriteResult` niesie też `->warnings` (ciche korekty normalizacji) - jeśli
niepuste, pokaż je użytkownikowi.

## Warianty

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
`ownerUserId`, `title`, `taxId`, `updatedAfter`/`updatedBefore`,
`createdAfter`/`createdBefore`, `customField[klucz]`, `sort`/`sortDir`,
`page`/`limit`. Do pełnego przebiegu wszystkich stron użyj `iterate()`
(wymusza `sort=id`, nie gubi rekordów - patrz [queries](../queries/README.md)).

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
- **`leadStatusId` to PROCES, nie etap.** W input ustawiasz proces leadowy
  (id z `leadProcesses()`). Konkretny ETAP (`leadStageId`) jest polem ODCZYTU -
  wynikiem pracy z leadem w CRM, nie parametrem zapisu. `LeadInput` w ogóle nie
  ma pola `leadStageId`.
- **`leadStatusId`/`contractorSourceId`/`creatorUserId` tylko przy tworzeniu.**
  Kontrakt oznacza je jako ustawiane wyłącznie w `create()`; nie licz, że
  `update()` je zmieni.
- **Brak upsert - brak wykrywania duplikatów.** Dwa `create()` z tymi samymi
  danymi dadzą dwa leady. Jeśli chcesz uniknąć duplikatu, najpierw sprawdź
  `list(['taxId' => ...])` albo `list(['title' => ...])`.
- **Nie zgaduj `ownerUserId`.** Kilka osób może mieć to samo nazwisko -
  `resolveUserId()` celowo rzuca przy wielu trafieniach. Dopytaj o e-mail.
- **`priority` bez zdefiniowanej skali.** Kontrakt nie mówi, co znaczy dana
  liczba w tej instancji - nie wpisuj wartości "na oko", pomiń albo dopytaj.
- **Odczyt vs zapis.** `Lead` (odczyt) ma pola nieobecne w `LeadInput`
  (`leadStageId`, `contractorId`, `contactId`, `salesPipelineId`, `region`,
  `district`, `emails`, znaczniki czasu) - ustawia je CRM.
- **`create()` zwraca `WriteResult`** (`->id`, `->created`, `->warnings`), nie
  samo id. Szczegóły: sekcja "Co zwracają zapisy" w
  [ai_integration.md](../ai_integration.md).
