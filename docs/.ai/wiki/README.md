# Playbook: Wiki (baza wiedzy)

Realizacja polecen uzytkownika dotyczacych bazy wiedzy: bazy -> kategorie ->
wpisy (artykuly i procedury). Tworzenie, edycja, publikacja i usuwanie wpisow,
wyszukiwanie pelnotekstowe. Pisane dla asystenta AI - zaklada wspolne wzorce z
[ai_integration.md](../ai_integration.md) (zwlaszcza `resolveUserId()`,
`WriteResult` i "Zlota zasade: nie zgaduj id").

Wiki jest jedna z DWOCH encji z operacja DELETE w calym API v2 (druga to adresy
kontrahenta) - `deleteEntry()` usuwa wpis NIEODWRACALNIE. Traktuj to ostroznie
(patrz Pulapki).

## Model danych w skrocie

Trzy poziomy hierarchii:

1. **Baza** (`WikiBase`) - kontener wiedzy o typie `article` albo `procedure`.
   Tworzysz `WikiBaseInput` (wymagane `name`).
2. **Kategoria** (`WikiCategory`) - grupa wpisow w bazie. Tworzysz przez
   `createCategory($baseId, ['name' => ...])` (zwykla tablica, wymagane `name`).
3. **Wpis** (`WikiEntry`) - artykul albo procedura. Tworzysz `WikiEntryInput`
   (wymagane `categoryId` i `title`).

Zapisy zwracaja `WriteResult` (id w `->id`, zrodlo zalezne od encji: `baseId`,
`categoryId`, `entryId`). Wpisy listuje sie stronami (`entries()` -> `Page`,
`iterateEntries()` -> generator).

Konsekwencja dla Ciebie: zeby dodac wpis, musisz najpierw miec `categoryId` -
a wiec baze i kategorie. Gdy uzytkownik mowi "dodaj artykul do bazy Onboarding",
najpierw rozwiaz baze po nazwie, potem kategorie, dopiero potem twórz wpis.

## Pola

Zapis bazy: `WikiBaseInput` (`src/Dto/WikiBaseInput.php`). Wymagane `name`.

| pole | typ | po co |
|---|---|---|
| `name` | `?string` | nazwa bazy; WYMAGANE |
| `type` | `?string` | rodzaj: `article` albo `procedure` |
| `subtitle` | `?string` | podtytul opisowy |
| `icon` | `?string` | ikona bazy |
| `color` | `?string` | kolor bazy |

Zapis wpisu: `WikiEntryInput` (`src/Dto/WikiEntryInput.php`). Przy tworzeniu
wymagane `categoryId` i `title`.

| pole | typ | po co (skad wziac id/referencje) |
|---|---|---|
| `categoryId` | `?int` | kategoria wpisu; WYMAGANE przy tworzeniu; z `categories($baseId)` (`->id`) |
| `title` | `?string` | tytul wpisu; WYMAGANE przy tworzeniu |
| `content` | `?string` | tresc (HTML) artykulu/procedury |
| `published` | `?bool` | czy opublikowany (widoczny); false = szkic |
| `alias` | `?string` | unikalny alias (adres wpisu) |
| `archived` | `?bool` | archiwizacja; TYLKO przy aktualizacji |
| `createdAt` | `?string` | data utworzenia przy imporcie historycznym (ISO 8601) |
| `creatorUserId` | `?int` | autor; TYLKO przy tworzeniu; `userId` z `resolveUserId()` |

Odczyt bazy: `WikiBase` (`src/Dto/WikiBase.php`) - `id`, `name`, `subtitle`,
`type`, `icon`, `color`, `archived`, `order`, `creatorUserId`, `createdAt`,
`raw`.

Odczyt kategorii: `WikiCategory` (`src/Dto/WikiCategory.php`) - `id`, `baseId`,
`name`, `archived`, `order`, `creatorUserId`, `createdAt`, `raw`.

Odczyt wpisu: `WikiEntry` (`src/Dto/WikiEntry.php`). Istotne pola:

| pole | typ | po co |
|---|---|---|
| `id` | `int` | id wpisu (do `getEntry`/`updateEntry`/`deleteEntry`) |
| `baseId` | `?int` | baza, do ktorej nalezy wpis |
| `categoryId` | `?int` | kategoria wpisu |
| `title` | `?string` | tytul |
| `content` | `?string` | pelna tresc (zwraca ja `getEntry()`) |
| `alias` | `?string` | alias/adres wpisu |
| `published` | `?bool` | czy opublikowany |
| `archived` | `?bool` | czy zarchiwizowany |
| `views` | `?int` | licznik odslon |
| `helpful` | `?int` | licznik glosow "pomocne" |
| `order`, `creatorUserId`, `createdAt`, `updatedAt`, `publishedAt`, `raw` | mieszane | metadane i surowy rekord |

## Mapowanie intencji uzytkownika na dane API

| Uzytkownik mowi | Potrzebujesz | Skad wziac |
|---|---|---|
| "dodaj artykul do bazy Onboarding" | `baseId` -> `categoryId` | `bases()` po nazwie, potem `categories($baseId)` |
| "kategoria Pierwsze kroki" | `categoryId` | `categories($baseId)`, dopasuj nazwe |
| "opublikuj od razu" | `published: true` | wprost (false = szkic) |
| "to procedura, nie artykul" | `type: 'procedure'` | pole bazy `WikiBaseInput` |
| "autor: Piotr" | `creatorUserId` | `resolveUserId()`; tylko przy tworzeniu |
| "znajdz wpis o fakturach" | filtr `search` | `entries(['search' => 'faktury'])` |
| "tylko opublikowane z bazy 7" | `baseId`, `published` | `entries(['baseId' => 7, 'published' => true])` |
| "usun ten wpis" | `deleteEntry($id)` | NIEODWRACALNE - patrz Pulapki |

## Scenariusz flagowy: nowy artykul w istniejacej bazie

Polecenie uzytkownika: *"Dodaj do bazy 'Onboarding' artykul 'Pierwszy dzien'
w kategorii 'Wdrozenie', opublikuj od razu."*

Tok postepowania:

1. Rozwiaz baze po nazwie na `baseId`.
2. Rozwiaz kategorie po nazwie na `categoryId` (w tej bazie).
3. Utworz wpis z `categoryId` i `title`; ustaw `published`.
4. Zwroc id wpisu.

```php
use TillioCrm\Api\Dto\WikiEntryInput;

// Krok 1: znajdz baze po nazwie. bases() zwraca liste bez stronicowania.
$baseId = null;
foreach ($client->wiki()->bases() as $base) {
    if (mb_strtolower((string) $base->name) === mb_strtolower('Onboarding')) {
        $baseId = $base->id;
        break;
    }
}
if ($baseId === null) {
    throw new RuntimeException('Nie znaleziono bazy "Onboarding" - dopytaj albo zaloz baze.');
}

// Krok 2: znajdz kategorie po nazwie w tej bazie.
$categoryId = null;
foreach ($client->wiki()->categories($baseId) as $category) {
    if (mb_strtolower((string) $category->name) === mb_strtolower('Wdrozenie')) {
        $categoryId = $category->id;
        break;
    }
}
if ($categoryId === null) {
    throw new RuntimeException('Nie znaleziono kategorii "Wdrozenie" w bazie Onboarding - dopytaj albo zaloz kategorie.');
}

// Krok 3: utworz wpis. categoryId i title sa WYMAGANE przy tworzeniu.
$result = $client->wiki()->createEntry(new WikiEntryInput(
    categoryId: $categoryId,
    title: 'Pierwszy dzien',
    content: '<h2>Pierwszy dzien</h2><p>Witaj w zespole.</p>',
    published: true,   // false = szkic, niewidoczny
));

// WriteResult->id niesie id nowego wpisu (zrodlo: pole entryId).
echo "Utworzono wpis #{$result->id} w bazie Onboarding / Wdrozenie.\n";
foreach ($result->warnings as $warning) {
    echo "Ostrzezenie: " . (is_string($warning) ? $warning : json_encode($warning)) . "\n";
}
```

Co zwrocic uzytkownikowi: id wpisu, baze i kategorie oraz informacje, czy
opublikowany, czy zapisany jako szkic.

## Warianty

### Nowa baza (article albo procedure)

```php
use TillioCrm\Api\Dto\WikiBaseInput;

// type rozroznia artykuly od procedur. Wymagane tylko name.
$base = $client->wiki()->createBase(new WikiBaseInput(
    name: 'Procedury sprzedazy',
    type: 'procedure',
    subtitle: 'Krok po kroku dla handlowcow',
));
$baseId = $base->id;   // zrodlo: pole baseId

// Kategoria w bazie - createCategory przyjmuje zwykla tablice, wymagane name.
$category = $client->wiki()->createCategory($baseId, ['name' => 'Kwalifikacja']);
```

### Wyszukiwanie pelnotekstowe wpisow

```php
// entries() zwraca Page z paginacja. Filtry: baseId, categoryId, published,
// archived, search. Trasa NIE ma sort - nie ustawiaj go.
$page = $client->wiki()->entries(['search' => 'faktury', 'published' => true, 'limit' => 20]);
foreach ($page as $entry) {
    echo "{$entry->id}: {$entry->title} (odslon: {$entry->views})\n";
}

// Pelny przebieg wszystkich stron. iterateEntries NIE wymusza sort=id, bo trasa
// go nie przyjmuje - inaczej niz standardowe iterate() w innych zasobach.
foreach ($client->wiki()->iterateEntries(['baseId' => 7]) as $entry) {
    echo "{$entry->title}\n";
}
```

### Publikacja i archiwizacja istniejacego wpisu

```php
use TillioCrm\Api\Dto\WikiEntryInput;

// Publikacja szkicu: podajesz tylko zmieniane pole (null = nie ruszaj reszty).
$client->wiki()->updateEntry(42, new WikiEntryInput(published: true));

// Archiwizacja - archived TYLKO przy aktualizacji, nie przy tworzeniu.
$client->wiki()->updateEntry(42, new WikiEntryInput(archived: true));
```

### Podglad pelnej tresci wpisu

```php
// Lista niesie metadane; pelna tresc (content) zwraca dopiero getEntry.
$entry = $client->wiki()->getEntry(42);
echo $entry->content ?? '(brak tresci)';
```

### Usuniecie wpisu (NIEODWRACALNE)

```php
// deleteEntry to jeden z dwoch DELETE w API. Nie ma cofniecia - upewnij sie,
// ze uzytkownik potwierdzil, ktory wpis usunac. Rozwaz zamiast tego archiwizacje.
$client->wiki()->deleteEntry(42);
echo "Usunieto wpis #42.\n";
```

## Pulapki

- **`deleteEntry()` jest NIEODWRACALNE.** To jeden z tylko dwoch DELETE w API v2.
  Zanim usuniesz, upewnij sie co do id i rozwaz archiwizacje
  (`updateEntry($id, new WikiEntryInput(archived: true))`) jako bezpieczniejsza
  alternatywe. Nie usuwaj "przy okazji".
- **Kolejnosc zaleznosci: baza -> kategoria -> wpis.** `createEntry` wymaga
  `categoryId`, wiec baza i kategoria musza istniec wczesniej. Rozwiaz je po
  nazwie, nie zgaduj id.
- **`iterateEntries` NIE wymusza `sort=id`** - trasa `/v2/wiki/entries` nie
  przyjmuje `sort`. To wyjatek od standardu innych zasobow; nie dodawaj `sort`
  do filtrow, bo dostaniesz blad.
- **`createCategory` przyjmuje zwykla tablice**, nie dedykowane DTO - wymagane
  `name`, np. `['name' => 'Kwalifikacja']`.
- **`archived` i pola importu tylko w odpowiednim momencie.** `archived` dziala
  przy AKTUALIZACJI wpisu; `createdAt` i `creatorUserId` przyjmuja sie TYLKO
  przy tworzeniu (import historyczny).
- **`published: false` to szkic** - wpis istnieje, ale nie jest widoczny. Gdy
  uzytkownik chce "dodac i opublikowac", ustaw `published: true`.
- **Pelna tresc dopiero z `getEntry()`.** Lista (`entries()`) niesie metadane;
  `content` czytaj przez `getEntry($id)`.
- **`WriteResult->id` z roznych pol.** Dla baz zrodlem jest `baseId`, dla
  kategorii `categoryId`, dla wpisow `entryId` - ale w kodzie zawsze czytasz je
  jako `->id`.
