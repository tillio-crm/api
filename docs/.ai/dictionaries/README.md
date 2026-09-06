# Playbook: Slowniki (dictionaries)

Slowniki to CENTRALNY zasob calej integracji. Ilekroc inny playbook mowi
"wez id ze slownika" - chodzi wlasnie tutaj. `dictionaries()` zwraca mapy
`id -> nazwa` dla wszystkich pol `*Id` API v2 (statusy, typy, priorytety,
zrodla, dzialy, tagi...) oraz struktury zlozone z zagniezdzonymi etapami
(procesy zgloszen, lejki sprzedazy, procesy leadowe). Pisane dla asystenta AI -
zaklada wspolne wzorce z [ai_integration.md](../ai_integration.md) (zwlaszcza
`findByName()`, `WriteResult` i "Zlota zasade: nie zgaduj id").

Zadanie tego playbooka: nauczyc Cie (1) jak znalezc id po nazwie, (2) ktore
slowniki sa tylko do odczytu, a ktore mozna zapisywac, (3) jak dodawac wpisy
prostych slownikow (`DictionaryEntryInput`) i etapy procesow
(`ProcessStageInput`).

## Pola

Wiekszosc slownikow to PROSTE listy `DictionaryEntry` (odczyt) zapisywane przez
`DictionaryEntryInput`. Jeden typ input obsluguje rozne warianty pol - `null` =
nie wysylaj pola, wiec podajesz tylko to, co dany slownik przyjmuje.

Zapis prostego slownika: `DictionaryEntryInput` (`src/Dto/DictionaryEntryInput.php`).

| pole | typ | po co (ktory slownik) |
|---|---|---|
| `name` | `?string` | nazwa wpisu; wymagana przy wiekszosci slownikow (statusy, zrodla, typy, priorytety, dzialy, tagi) |
| `color` | `?string` | kolor etykiety (statusy, zrodla, typy, tagi); zwykle hex |
| `order` | `?int` | kolejnosc na liscie |
| `active` | `?bool` | czy wpis czynny (statusy, zrodla, typy, terminy platnosci) |
| `days` | `?int` | liczba dni; WYMAGANE dla `service/payment-terms` (0-365), nieuzywane gdzie indziej |
| `isDefault` | `?bool` | czy wpis domyslny (np. domyslny termin platnosci) |

Odczyt prostego slownika: `DictionaryEntry` (`src/Dto/DictionaryEntry.php`).
Pola spoza danego slownika przychodza jako null/false i zostaja w `$raw`.

| pole | typ | po co |
|---|---|---|
| `id` | `int` | to jest szukane id do pola `*Id` w innych playbookach |
| `name` | `?string` | nazwa do dopasowania przez `findByName()` |
| `active` | `?bool` | czy wpis czynny |
| `isFinal` | `?bool` | czy status koncowy (np. zamykajacy zgloszenie/zamowienie) |
| `color` | `?string` | kolor etykiety |
| `days` | `?int` | liczba dni (terminy platnosci) |
| `isDefault` | `?bool` | czy wpis domyslny |
| `isUnique` | `?bool` | czy wartosc musi byc unikalna |
| `order` | `?int` | kolejnosc |
| `raw` | `array` | pelny surowy rekord, gdy potrzebne pole spoza mapowania |

Zapis etapu procesu/lejka: `ProcessStageInput` (`src/Dto/ProcessStageInput.php`).
Wspolny dla etapow zgloszen, lejkow i statusow leadowych - `null` = nie wysylaj.

| pole | typ | po co |
|---|---|---|
| `name` | `?string` | nazwa etapu; WYMAGANA |
| `color` | `?string` | kolor etapu |
| `order` | `?int` | kolejnosc etapu |
| `probability` | `?int` | prawdopodobienstwo (%) - TYLKO etapy lejkow sprzedazy |
| `type` | `?string` | `default`/`qualified`/`disqualified` - TYLKO statusy procesow leadowych |

Odczyt etapu: `ProcessStage` (`src/Dto/ProcessStage.php`) - te same pola co
input plus `id`; `probability` tylko w lejkach, `type` tylko w leadach.

Slowniki zlozone maja wlasne input-DTO: role - `UserRoleInput` (`name` max 32
znaki, `permissions` jako klucze z `userPermissions()`), procesy zgloszen -
`TicketProcessInput`, lejki - `PipelineFunnelInput`, procesy leadowe -
`LeadProcessInput` (wszystkie wymagaja `name`). Etapy tych struktur zapisuje
sie `ProcessStageInput` (tabela wyzej).

## Model danych w skrocie

Trzy poziomy zlozonosci:

1. **Proste slowniki** (`DictionaryEntry`) - plaska lista `{id, name, ...}` bez
   stronicowania. Czesc jest tylko do odczytu, czesc ma `createXxx`/`updateXxx`
   przyjmujace `DictionaryEntryInput`.
2. **Struktury zlozone** (`ticketProcesses`, `pipelineFunnels`, `leadProcesses`)
   - proces/lejek z zagniezdzonymi etapami. Sam proces zapisujesz jego wlasnym
   input-DTO, a etapy osobnymi metodami `ProcessStageInput`.
3. **Role** (`userRoles`) - wpis z lista kluczy uprawnien (`userPermissions`).

Zapisy zwracaja `WriteResult` (`->id`, `->created`, `->warnings`). Odczyty
proste zwracaja listy bez stronicowania - nie ma tu `iterate()` ani filtrow.

Kluczowa konsekwencja: to jest miejsce, gdzie inne playbooki przychodza po id.
Gdy uzytkownik mowi "status W toku", "priorytet wysoki", "zrodlo polecenie" -
tlumaczysz nazwe na id przez odpowiedni slownik i `findByName()`.

## Katalog metod slownikowych

Pogrupowane wg domeny. Kolumna "zapis" mowi, czy slownik ma `createXxx`/
`updateXxx` (`+`), czy jest tylko do odczytu (`ro`).

### Kontrahenci

| metoda | zapis | po co |
|---|---|---|
| `contractorTypes()` | ro | typy kontrahentow (firma/osoba...) |
| `contractorStatuses()` | + | statusy kontrahentow |
| `contractorSources()` | + | zrodla pozyskania |
| `contractorPriorities()` | + | priorytety kontrahentow |
| `contractorIndustries()` | + | branze |
| `contractorLegalForms()` | + | formy prawne |
| `contractorPaymentTypes()` | + | typy platnosci |

### Adresy, notatki, zamowienia, projekty

| metoda | zapis | po co |
|---|---|---|
| `addressTypes()` | + | typy adresow (1 = podstawowy) |
| `noteTypes()` | + | typy notatek |
| `orderStatuses()` | + | statusy zamowien |
| `projectStatuses()` | + | statusy projektow |

### Zgloszenia

| metoda | zapis | po co |
|---|---|---|
| `ticketStatuses()` | + | statusy zgloszen |
| `ticketSources()` | ro | zrodla zgloszen |
| `ticketProcesses()` | + zlozony | procesy obslugi z etapami (`resolutionTimeMinutes` = SLA); etapy przez `createTicketStage()`/`updateTicketStage()` |

### Zadania

| metoda | zapis | po co |
|---|---|---|
| `taskStatuses()` | + | statusy zadan |
| `taskTags()` | + | tagi zadan (bez `#` - CRM doda sam) |

### Uzytkownicy

| metoda | zapis | po co |
|---|---|---|
| `userStatuses()` | ro | statusy kont (NIEAKTYWNE nie licza sie do limitu licencji) |
| `userRoles()` | + (`UserRoleInput`) | role z kluczami uprawnien |
| `userPermissions()` | ro | slownik kluczy uprawnien do rol |
| `userDepartments()` | + | dzialy |

### Uslugi

| metoda | zapis | po co |
|---|---|---|
| `serviceStatuses()` | ro | statusy uslug |
| `serviceBillingPeriods()` | ro | okresy rozliczeniowe |
| `servicePaymentTerms()` | + (`days`) | terminy platnosci (0-365 dni) |
| `serviceInvoiceTypes()` | + | typy faktur uslug |

### Lejki sprzedazy

| metoda | zapis | po co |
|---|---|---|
| `pipelineFunnels()` | + zlozony (`PipelineFunnelInput`) | lejki z etapami (`probability` per etap); etapy przez `createPipelineStage()`/`updatePipelineStage()` |

### Leady

| metoda | zapis | po co |
|---|---|---|
| `leadProcesses()` | + zlozony (`LeadProcessInput`) | procesy leadowe ze statusami (`type`: default/qualified/disqualified); statusy przez `createLeadStatus()`/`updateLeadStatus()`. UWAGA: trasa to `GET /v2/lead/statuses`, ale zwraca PROCESY |

### Kalendarze i pozostale

| metoda | zapis | po co |
|---|---|---|
| `calendarTypes()` | ro | rodzaje kalendarzy (Tillio/Microsoft/Google); wymaga API >= 2.2.0 |
| `currencies()` | ro | kody walut instancji (`list<string>`, nie `DictionaryEntry`) |

## Mapowanie intencji uzytkownika na dane API

| Uzytkownik mowi | Potrzebujesz | Skad wziac |
|---|---|---|
| "status zgloszenia Nowe" | `ticketStatusId` | `findByName($client->dictionaries()->ticketStatuses(), 'Nowe')` |
| "priorytet wysoki" (kontrahent) | id priorytetu | `findByName($client->dictionaries()->contractorPriorities(), 'Wysoki')` |
| "zrodlo: polecenie" | id zrodla | `findByName($client->dictionaries()->contractorSources(), 'Polecenie')` |
| "dodaj status zamowienia Wyslane" | `WriteResult` | `createOrderStatus(new DictionaryEntryInput(name: 'Wyslane'))` |
| "dodaj tag zadania pilne" | `WriteResult` | `createTaskTag(new DictionaryEntryInput(name: 'pilne'))` (bez `#`) |
| "termin platnosci 14 dni" | id terminu | `findByName(servicePaymentTerms(), '14 dni')`; nowy: `createServicePaymentTerm(new DictionaryEntryInput(days: 14))` |
| "dodaj etap do lejka Sprzedaz" | `WriteResult` | `createPipelineStage($funnelId, new ProcessStageInput(name: 'Negocjacje', probability: 60))` |
| "jakie sa role w systemie" | `list<UserRole>` | `userRoles()` (odczyt, bez zapisu przez `DictionaryEntryInput`) |

## Scenariusz flagowy: rozwiazanie id statusu, a gdy brak - dodanie wpisu

Polecenie uzytkownika: *"Ustaw status zgloszenia na 'Oczekuje na klienta'.
Jesli takiego statusu nie ma, dodaj go."*

To typowy przeplyw: najpierw szukasz id po nazwie, a dopiero gdy slownik go nie
ma, zakladasz wpis. Nigdy nie zgadujesz id ani nie tworzysz duplikatu na slepo.

```php
use TillioCrm\Api\Dto\DictionaryEntryInput;

$wanted = 'Oczekuje na klienta';

// Krok 1: sprobuj znalezc istniejacy status po nazwie.
// findByName() z ai_integration.md zwraca ?int (null gdy brak dopasowania).
$statusId = findByName($client->dictionaries()->ticketStatuses(), $wanted);

// Krok 2: gdy statusu nie ma, zaloz go. createTicketStatus zwraca WriteResult.
if ($statusId === null) {
    $result = $client->dictionaries()->createTicketStatus(new DictionaryEntryInput(
        name: $wanted,
        color: '#f0ad4e',   // opcjonalnie - kolor etykiety
    ));
    // WriteResult->id niesie id nowego wpisu (albo trafionego duplikatu).
    $statusId = $result->id;

    // Ciche korekty normalizacji - pokaz uzytkownikowi, jesli sa.
    foreach ($result->warnings as $warning) {
        echo "Ostrzezenie slownika: " . (is_string($warning) ? $warning : json_encode($warning)) . "\n";
    }
    echo "Dodano status zgloszenia '{$wanted}' (#{$statusId}).\n";
} else {
    echo "Status '{$wanted}' juz istnieje (#{$statusId}).\n";
}

// Krok 3: id gotowe do uzycia w innym playbooku (np. aktualizacja zgloszenia).
// Tu zwracamy je uzytkownikowi albo przekazujemy dalej.
```

Co zwrocic uzytkownikowi: id statusu i informacje, czy zostal znaleziony, czy
dopiero utworzony. Gdy tworzysz wpis, pokaz ewentualne `->warnings`.

## Warianty

### Dodanie etapu do lejka sprzedazy (probability)

```php
use TillioCrm\Api\Dto\ProcessStageInput;

// Najpierw znajdz lejek po nazwie wsrod struktur zlozonych.
$funnelId = null;
foreach ($client->dictionaries()->pipelineFunnels() as $funnel) {
    if (mb_strtolower((string) $funnel->name) === mb_strtolower('Sprzedaz B2B')) {
        $funnelId = $funnel->id;
        break;
    }
}
if ($funnelId === null) {
    throw new RuntimeException('Nie znaleziono lejka "Sprzedaz B2B" - dopytaj albo zaloz lejek.');
}

// probability jest sensowne TYLKO w etapach lejkow.
$client->dictionaries()->createPipelineStage($funnelId, new ProcessStageInput(
    name: 'Negocjacje',
    probability: 60,
    order: 3,
));
```

### Dodanie statusu do procesu leadowego (type)

```php
use TillioCrm\Api\Dto\ProcessStageInput;

// leadProcesses() zwraca PROCESY (mimo trasy /lead/statuses).
$processId = 12;   // id procesu leadowego z leadProcesses()

// type jest sensowne TYLKO w statusach procesow leadowych.
$client->dictionaries()->createLeadStatus($processId, new ProcessStageInput(
    name: 'Zakwalifikowany',
    type: 'qualified',
));
```

### Dodanie terminu platnosci (days zamiast name)

```php
use TillioCrm\Api\Dto\DictionaryEntryInput;

// Termin platnosci opisuje sie liczba DNI, nie nazwa. isDefault ustawia domyslny.
$client->dictionaries()->createServicePaymentTerm(new DictionaryEntryInput(
    days: 14,
    isDefault: false,
));
```

### Przeglad calego slownika (bez zapisu)

```php
// Proste slowniki nie maja paginacji ani filtrow - zwracaja pelna liste.
foreach ($client->dictionaries()->contractorStatuses() as $entry) {
    $final = $entry->isFinal === true ? ' [koncowy]' : '';
    echo "{$entry->id}: {$entry->name}{$final}\n";
}

// Waluty to lista stringow, nie DictionaryEntry.
foreach ($client->dictionaries()->currencies() as $code) {
    echo "waluta: {$code}\n";
}
```

## Pulapki

- **Nie kazdy slownik da sie zapisywac.** Tylko-do-odczytu sa m.in.
  `contractorTypes`, `ticketSources`, `userStatuses`, `userPermissions`,
  `serviceStatuses`, `serviceBillingPeriods`, `calendarTypes`, `currencies`.
  Sprawdz kolumne "zapis" w katalogu wyzej, zanim zaproponujesz dodanie wpisu.
- **`findByName()` zwraca null przy braku dopasowania.** Nie interpretuj null
  jako id. Albo zaloz wpis (gdy slownik zapisywalny), albo dopytaj uzytkownika -
  nigdy nie wstawiaj liczby z glowy do pola `*Id`.
- **`ProcessStageInput`: `probability` tylko w lejkach, `type` tylko w leadach.**
  Podanie `probability` do etapu zgloszenia albo `type` do etapu lejka nie ma
  sensu - CRM je zignoruje albo odrzuci.
- **`leadProcesses()` zwraca PROCESY, nie statusy**, mimo trasy
  `GET /v2/lead/statuses`. Nazwa metody idzie za typem wyniku (`LeadProcess`).
- **Struktury zlozone maja wlasne input-DTO.** Sam proces/lejek/role zapisujesz
  odpowiednio `TicketProcessInput`/`PipelineFunnelInput`/`LeadProcessInput`/
  `UserRoleInput`, a NIE `DictionaryEntryInput`. Etapy zawsze `ProcessStageInput`.
- **Terminy platnosci opisuje `days`, nie `name`.** `createServicePaymentTerm`
  wymaga `days` (0-365).
- **Tagi zadan bez `#`.** `createTaskTag(new DictionaryEntryInput(name: 'pilne'))`
  - CRM sam doda `#`.
- **Slowniki nie maja paginacji.** Zwracaja pelne listy - nie szukaj tu
  `iterate()` ani filtrow jak w zasobach CRM.
- **`calendarTypes()` wymaga API >= 2.2.0.** Na starszej instancji trasa nie
  istnieje - sprawdz `health()['version']` albo obsluz `ServiceUnavailableException`.
