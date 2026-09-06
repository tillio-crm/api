# Playbook: Projekty (projects)

Realizacja polecen uzytkownika dotyczacych projektow: zakladanie, przypisywanie
opiekuna, powiazanie z kontrahentem, status, terminy i kolor. Pisane dla
asystenta AI - zaklada wspolne wzorce z
[ai_integration.md](../ai_integration.md) (zwlaszcza `resolveUserId()`,
`findByName()`, `WriteResult` oraz "Zlota zasada: nie zgaduj id").

## Model danych w skrocie

Projekt tworzy sie przez `ProjectInput`. Przy tworzeniu API WYMAGA tylko
`name` (string). Reszta jest opcjonalna: opiekun, kontrahent, status, kolor,
daty i pola niestandardowe.

Projekt jest kontenerem na zadania - odczyt (`Project`) niesie liczniki zadan
(`tasksCount`, `tasksDoneCount`, `tasksUndoneCount`, `tasksOverdueCount`),
ktore ustawia CRM; `ProjectInput` ich nie przyjmuje. Same zadania dodaje sie
przez zasob zadan (patrz playbook zadan), a nie przez `ProjectInput`.

Pelna lista pol: `src/Dto/ProjectInput.php`.

## Pola

### `ProjectInput` (tworzenie i aktualizacja projektu)

| pole | typ | po co |
|---|---|---|
| `name` | string | nazwa projektu. WYMAGANE przy tworzeniu. |
| `description` | string | opis projektu. |
| `contractorId` | int | kontrahent, ktorego dotyczy projekt. Id przez `contractors()->list(['name' => ...])`. |
| `ownerUserId` | int | opiekun (osoba prowadzaca projekt). Id przez `resolveUserId()`. |
| `projectStatusId` | int | status projektu. Id przez `dictionaries()->projectStatuses()` (dopasuj nazwe przez `findByName`). |
| `color` | string | kolor projektu jako HEX (np. "#3399FF") - do oznaczenia wizualnego. |
| `startDate` | string | data rozpoczecia, ISO 8601 z offsetem strefy. |
| `dueDate` | string | termin projektu, ISO 8601 z offsetem strefy. |
| `customField` | array | wartosci pol niestandardowych (klucz => wartosc). Definicje przez `customFields()`. |
| `createdAt` | string | data utworzenia przy imporcie historycznym, ISO 8601. |
| `creatorUserId` | int | autor projektu. TYLKO przy tworzeniu. Id przez `resolveUserId()`. |

### Istotne pola odczytu (`Project`)

| pole | typ | po co |
|---|---|---|
| `id` | int | id projektu - do powiazan i dalszych operacji. |
| `archived` | bool | czy projekt zarchiwizowany (ustawiane po stronie CRM). |
| `tasksCount` | int | liczba wszystkich zadan w projekcie (ustawiane po stronie CRM). |
| `tasksDoneCount` | int | liczba zadan zamknietych. |
| `tasksUndoneCount` | int | liczba zadan otwartych. |
| `tasksOverdueCount` | int | liczba zadan po terminie - sygnal do raportu. |
| `updatedAt` | string | data ostatniej zmiany - do filtrow przyrostowych. |

## Mapowanie intencji uzytkownika na dane API

| Uzytkownik mowi | Potrzebujesz | Skad wziac |
|---|---|---|
| "projekt 'Wdrozenie X'" | `name` | wprost z polecenia |
| "dla kontrahenta Acme" | `contractorId` | `contractors()->list(['name' => 'Acme'])` |
| "opiekun Jan Kowalski" / "prowadzi Jan" | `ownerUserId` | `resolveUserId($client, 'Jan', 'Kowalski')` |
| "status W realizacji" | `projectStatusId` | `findByName($client->dictionaries()->projectStatuses(), 'W realizacji')` |
| "termin do 2026-10-01" | `dueDate` (ISO 8601) | policz date, sformatuj `DATE_ATOM` |
| "start od 2026-09-15" | `startDate` (ISO 8601) | jak wyzej |
| "oznacz na niebiesko" | `color` (HEX) | zamien nazwe koloru na HEX; jesli nie masz pewnosci - pomin |

## Scenariusz flagowy: projekt dla kontrahenta z opiekunem i statusem

Polecenie uzytkownika: *"Zaloz projekt 'Wdrozenie systemu' dla kontrahenta Acme,
opiekun Jan Kowalski, status W realizacji, termin do 2026-10-01."*

Twoj tok postepowania:

1. Wyluskaj nazwe, kontrahenta, opiekuna, status i termin.
2. Rozwiaz kontrahenta na `contractorId` i opiekuna na `ownerUserId`; jesli
   ktorykolwiek jest zerowy albo niejednoznaczny - PRZERWIJ i dopytaj.
3. Rozwiaz status po nazwie ze slownika (moze byc null - wtedy dopytaj).
4. Sformatuj termin do ISO 8601.
5. Utworz projekt (`name` wymagane) i zwroc potwierdzenie z id.

```php
use TillioCrm\Api\Dto\ProjectInput;

// Krok 1: dane z polecenia (Ty je wyluskujesz z tekstu uzytkownika).
$name = 'Wdrozenie systemu';

// Krok 2: rozwiaz kontrahenta i opiekuna na id - nie zgaduj.
$contractor = $client->contractors()->list(['name' => 'Acme', 'limit' => 1])->first();
if ($contractor === null) {
    throw new RuntimeException('Nie znaleziono kontrahenta Acme - dopytaj albo zaloz kartoteke.');
}

// resolveUserId() z ai_integration.md: rzuca przy zeru/wielu trafieniach.
$ownerUserId = resolveUserId($client, 'Jan', 'Kowalski');

// Krok 3: status ze slownika po nazwie. null = brak dopasowania -> dopytaj.
$statusId = findByName($client->dictionaries()->projectStatuses(), 'W realizacji');
if ($statusId === null) {
    throw new RuntimeException('Nie ma statusu "W realizacji" w tej instancji - dopytaj o wlasciwy status.');
}

// Krok 4: termin w ISO 8601 z offsetem strefy (koniec dnia jako zalozenie).
$dueDate = (new DateTimeImmutable('2026-10-01'))->setTime(23, 59)->format(DATE_ATOM);

// Krok 5: utworzenie projektu. create() zwraca WriteResult: ->id, ->created, ->warnings.
$result = $client->projects()->create(new ProjectInput(
    name: $name,
    contractorId: $contractor->id,
    ownerUserId: $ownerUserId,
    projectStatusId: $statusId,
    dueDate: $dueDate,
));

// Potwierdzenie dla uzytkownika.
echo "Utworzono projekt #{$result->id} '{$name}' dla {$contractor->name}, termin {$dueDate}.\n";
```

Co zwrocic uzytkownikowi: numer projektu (`$result->id`), nazwe, kontrahenta,
opiekuna i termin. `WriteResult` niesie tez `->warnings` (ciche korekty
normalizacji) - jesli niepuste, pokaz je uzytkownikowi.

## Warianty

### Projekt bez kontrahenta (wewnetrzny)

```php
// Kontrahent jest opcjonalny - projekt wewnetrzny zakladasz z samą nazwą.
$client->projects()->create(new ProjectInput(
    name: 'Reorganizacja zespolu',
    ownerUserId: $ownerUserId,
));
```

### Status projektu z nazwy

```php
// Status ze slownika; dopasuj nazwe podana przez uzytkownika.
$statusId = findByName($client->dictionaries()->projectStatuses(), 'Zakonczony');
$client->projects()->update($projectId, new ProjectInput(
    projectStatusId: $statusId,   // null jesli nazwa nie pasuje - wtedy dopytaj
));
```

### Postep projektu z licznikow

```php
// Odczyt projektu daje liczniki zadan - przydatne do raportu postepu.
$project = $client->projects()->get($projectId);
echo "Zadania: {$project->tasksDoneCount}/{$project->tasksCount} zrobione, "
    . "{$project->tasksOverdueCount} po terminie.\n";
```

## Pulapki

- **`name` jest wymagane.** Projekt bez nazwy = 422.
- **`projectStatusId` z nazwy bywa null**, gdy uzytkownik poda status spoza
  slownika tej instancji - wtedy dopytaj, nie wstawiaj przypadkowego id.
- **Nie zgaduj `ownerUserId`.** Kilku pracownikow moze miec to samo nazwisko -
  `resolveUserId()` celowo rzuca przy wielu trafieniach. Dopytaj o e-mail.
- **Daty w ISO 8601 z offsetem strefy** (`DATE_ATOM`, np.
  `2026-10-01T23:59:00+02:00`). Inny format to 400.
- **`color` to HEX**, nie nazwa koloru. Jesli nie masz pewnosci co do wartosci
  HEX, pomin pole zamiast zgadywac.
- **Odczyt vs zapis**: liczniki zadan (`tasksCount`, `tasksOverdueCount`, ...)
  i `archived` sa tylko w odczycie - `ProjectInput` ich nie przyjmuje, ustawia
  je CRM. Zadania dodaje sie przez zasob zadan, nie przez `ProjectInput`.
- **`create()` zwraca `WriteResult`** (`->id` = `projectId`, plus `->created`,
  `->warnings`, `isDuplicate()`), nie samo id. Szczegoly: sekcja "Co zwracaja
  zapisy" w [ai_integration.md](../ai_integration.md).
