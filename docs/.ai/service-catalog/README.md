# Playbook: Katalog uslug (service-catalog)

Realizacja polecen uzytkownika dotyczacych katalogu uslug: dodawanie pozycji
katalogu (szablonow) i grup katalogu, ich domyslnych kwot, ikon i aktywnosci.
Pisane dla asystenta AI - zaklada wspolne wzorce z
[ai_integration.md](../ai_integration.md) (zwlaszcza `findByName()`,
`WriteResult` oraz "Zlota zasada: nie zgaduj id").

## Model danych w skrocie

Katalog uslug to zbior SZABLONOW, z ktorych zaklada sie uslugi u kontrahentow
(same instancje uslug - patrz playbook uslug). Katalog ma dwa poziomy:

- **grupy** (`ServiceCatalogGroup`) - drzewo przez `parentId`,
- **pozycje** (`ServiceCatalogItem`) - kazda nalezy do jednej grupy.

Pozycje czytasz stronicowanym `list()` / `iterate()`, a tworzysz przez
`create(ServiceCatalogItemInput)` - API WYMAGA `name` i `groupId`. Grupy czytasz
przez `groups()` (zwraca cala liste BEZ paginacji), a tworzysz przez
`createGroup(ServiceCatalogGroupInput)` - API WYMAGA `name`.

Zeby zalozyc pozycje, musisz najpierw miec `groupId` - bierzesz go z `groups()`
albo tworzysz grupe. Kwoty (`defaultPayValue`) podajesz jako stringi dziesietne.

Pelna lista pol: `src/Dto/ServiceCatalogItemInput.php` i
`src/Dto/ServiceCatalogGroupInput.php`.

## Pola

### `ServiceCatalogItemInput` (pozycja katalogu)

| pole | typ | po co |
|---|---|---|
| `name` | string | nazwa pozycji katalogu. WYMAGANE przy tworzeniu. |
| `groupId` | int | grupa, do ktorej nalezy pozycja. WYMAGANE przy tworzeniu. Id przez `serviceCatalog()->groups()` (dopasuj przez `findByName`). |
| `icon` | string | ikona pozycji (identyfikator wg CRM). |
| `defaultPayValue` | string | domyslna kwota jako string dziesietny (np. "199.00"), przenoszona do zakladanej uslugi. Wymaga API >= 2.0.4 (starsze przyjmowaly `price`). |
| `currency` | string | domyslna waluta (np. "PLN"). |
| `isAgreement` | bool | czy pozycja reprezentuje usluge umowna. |
| `active` | bool | czy pozycja jest aktywna (dostepna do zakladania uslug). |

### `ServiceCatalogGroupInput` (grupa katalogu)

| pole | typ | po co |
|---|---|---|
| `name` | string | nazwa grupy. WYMAGANE przy tworzeniu. |
| `parentId` | int | grupa nadrzedna (drzewo). Id przez `serviceCatalog()->groups()`. Pomin dla grupy najwyzszego poziomu. |
| `color` | string | kolor grupy jako HEX. |
| `icon` | string | ikona grupy (identyfikator wg CRM). |
| `order` | int | kolejnosc grupy na liscie. |
| `active` | bool | czy grupa jest aktywna. |

### Istotne pola odczytu (`ServiceCatalogItem`)

| pole | typ | po co |
|---|---|---|
| `id` | int | id pozycji - to jest `catalogId` przy zakladaniu uslugi (patrz playbook uslug). |
| `name` | string | nazwa pozycji. |
| `groupId` / `groupName` | int / string | grupa pozycji - do orientacji w drzewie. |
| `defaultPayValue` | string | domyslna kwota (string dziesietny). |
| `defaultTax` | string | domyslna stawka podatku (odczyt, ustawiana po stronie CRM). |
| `active` | bool | czy pozycja aktywna. |

## Mapowanie intencji uzytkownika na dane API

| Uzytkownik mowi | Potrzebujesz | Skad wziac |
|---|---|---|
| "pozycja 'Hosting'" | `name` | wprost z polecenia |
| "w grupie 'Uslugi IT'" | `groupId` | `findByName($client->serviceCatalog()->groups(), 'Uslugi IT')` |
| "domyslna cena 199.00 PLN" | `defaultPayValue` + `currency` | podaj jako string "199.00" i "PLN" |
| "to usluga umowna" | `isAgreement: true` | wprost z intencji |
| "nowa grupa 'Uslugi IT'" | `createGroup` z `name` | `createGroup(new ServiceCatalogGroupInput(name: 'Uslugi IT'))` |
| "podgrupa w 'Uslugi'" | `parentId` | id grupy nadrzednej z `groups()` |

## Scenariusz flagowy: pozycja katalogu w grupie (zaloz grupe, jesli brak)

Polecenie uzytkownika: *"Dodaj do katalogu pozycje 'Hosting' w grupie
'Uslugi IT', domyslna cena 199.00 PLN. Jesli grupy nie ma, zaloz ja."*

Twoj tok postepowania:

1. Wyluskaj nazwe pozycji, nazwe grupy i domyslna kwote/walute.
2. Pobierz grupy (`groups()` - cala lista) i znajdz grupe po nazwie.
3. Jesli grupy nie ma - zaloz ja przez `createGroup()` i wez jej id z `WriteResult`.
4. Utworz pozycje (`name` i `groupId` wymagane); kwote jako string.
5. Zwroc potwierdzenie z id pozycji.

```php
use TillioCrm\Api\Dto\ServiceCatalogGroupInput;
use TillioCrm\Api\Dto\ServiceCatalogItemInput;

// Krok 1: dane z polecenia (Ty je wyluskujesz z tekstu uzytkownika).
$itemName  = 'Hosting';
$groupName = 'Uslugi IT';

// Krok 2: grupy to pelna lista bez paginacji - findByName dopasowuje nazwe.
$groupId = findByName($client->serviceCatalog()->groups(), $groupName);

// Krok 3: brak grupy -> zaloz ja. createGroup() zwraca WriteResult z ->id (groupId).
if ($groupId === null) {
    $groupResult = $client->serviceCatalog()->createGroup(new ServiceCatalogGroupInput(
        name: $groupName,
    ));
    $groupId = $groupResult->id;
}

// Krok 4: utworzenie pozycji katalogu. name + groupId wymagane; kwota jako string.
// create() zwraca WriteResult: ->id (catalogId), ->created, ->warnings.
$result = $client->serviceCatalog()->create(new ServiceCatalogItemInput(
    name: $itemName,
    groupId: $groupId,
    defaultPayValue: '199.00',
    currency: 'PLN',
    active: true,
));

// Krok 5: potwierdzenie dla uzytkownika.
echo "Dodano pozycje katalogu #{$result->id} '{$itemName}' w grupie '{$groupName}' (id {$groupId}).\n";
```

Co zwrocic uzytkownikowi: numer pozycji (`$result->id` = przyszly `catalogId`),
nazwe, grupe i domyslna kwote. `WriteResult` niesie tez `->warnings` (ciche
korekty normalizacji) - jesli niepuste, pokaz je uzytkownikowi.

## Warianty

### Przeglad drzewa grup

```php
// groups() zwraca cala liste bez paginacji; drzewo budujesz po parentId.
foreach ($client->serviceCatalog()->groups() as $group) {
    $level = $group->parentId === null ? 'root' : "pod {$group->parentId}";
    echo "#{$group->id} {$group->name} ({$level})\n";
}
```

### Podgrupa pod istniejaca grupa

```php
$parentId = findByName($client->serviceCatalog()->groups(), 'Uslugi');
if ($parentId === null) {
    throw new RuntimeException('Brak grupy nadrzednej "Uslugi" - dopytaj albo zaloz ja najpierw.');
}
$client->serviceCatalog()->createGroup(new ServiceCatalogGroupInput(
    name: 'Uslugi IT',
    parentId: $parentId,
));
```

### Przeglad pozycji w grupie

```php
// Pozycje sa stronicowane - iterate przechodzi wszystkie strony.
foreach ($client->serviceCatalog()->iterate(['groupId' => $groupId]) as $item) {
    echo "#{$item->id} {$item->name}: {$item->defaultPayValue} {$item->currency}\n";
}
```

### Dezaktywacja pozycji

```php
// Nie usuwaj pozycji uzywanych przez uslugi - wystarczy ustawic active: false.
$client->serviceCatalog()->update($catalogItemId, new ServiceCatalogItemInput(
    active: false,
));
```

## Pulapki

- **`name` i `groupId` sa wymagane dla pozycji.** Pozycja bez nazwy albo bez
  grupy = 422. Najpierw miej `groupId` (z `groups()` albo `createGroup()`).
- **Grupa wymaga `name`.** Grupa bez nazwy = 422.
- **`defaultPayValue` jako string dziesietny** (np. "199.00"), nie float. Wymaga
  API >= 2.0.4 - starsze wydania przyjmowaly tu `price` (inna nazwa pola).
- **`groups()` NIE stronicuje** - zwraca cala liste naraz; pozycje (`list()` /
  `iterate()`) sa stronicowane. Nie mieszaj tych dwoch trybow.
- **`groupId` z nazwy bywa null**, gdy grupy nie ma - wtedy albo zaloz ja przez
  `createGroup()`, albo dopytaj; nie wstawiaj przypadkowego id.
- **Katalog to szablony, nie instancje.** Zeby usluga powstala u kontrahenta,
  uzyj `id` pozycji jako `catalogId` w `services()->create()` (patrz playbook
  uslug). Sam wpis w katalogu nie tworzy uslugi u nikogo.
- **`create()` i `createGroup()` zwracaja `WriteResult`** (`->id` = odpowiednio
  `catalogId` / `groupId`, plus `->created`, `->warnings`, `isDuplicate()`), nie
  samo id. Szczegoly: sekcja "Co zwracaja zapisy" w
  [ai_integration.md](../ai_integration.md).
