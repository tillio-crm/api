# Playbook: Grupy produktów (product-groups)

Realizacja poleceń użytkownika dotyczących kategorii katalogu: zakładanie grup,
budowanie drzewa (podgrupy przez rodzica), wyszukiwanie grupy po nazwie do
wpięcia produktu. Pisane dla asystenta AI - zakłada wspólne wzorce z
[ai_integration.md](../ai_integration.md) (zwłaszcza `findByName()`, `WriteResult`
i "Złotą zasadę: nie zgaduj id").

## Pola

Zapis idzie przez `ProductGroupInput` (`src/Dto/ProductGroupInput.php`). Named
arguments; `null` = pola nie wysyłamy. Przy tworzeniu API wymaga wyłącznie `name`.

| pole | typ | po co (i skąd wziąć id/referencję) |
|---|---|---|
| `name` | `?string` | Nazwa grupy. WYMAGANE przy `create()`. |
| `parentId` | `?int` | Rodzic w drzewie. Grupa najwyższego poziomu = pomiń (`null`). Podgrupa = id rodzica z `productGroups()->list(['name' => ...])` albo `iterate()`; nie zgaduj liczby. |
| `color` | `?string` | Kolor etykiety grupy (np. `"#3366FF"`), format wg CRM. |
| `priority` | `?int` | Kolejność/waga przy wyświetlaniu. Kontrakt nie definiuje skali - ustaw tylko, gdy wiesz, co znaczy w tej instancji. |
| `status` | `?int` | Status grupy (aktywna/ukryta). Kontrakt nie mapuje skali liczbowej - nie zgaduj; podejrzyj `ProductGroup->status` istniejącej grupy albo pomiń. |
| `createdAt` | `?string` | Data utworzenia. Tylko przy imporcie historycznym; ISO 8601. |
| `creatorUserId` | `?int` | Autor. Tylko przy imporcie historycznym; `resolveUserId()`. |

Pola tylko do ODCZYTU (`ProductGroup`, `src/Dto/ProductGroup.php`): `id`
(potrzebne jako `groupId` produktu oraz `parentId` podgrupy). Pełny surowy
rekord: `ProductGroup->raw`.

## Model danych

Grupy tworzą DRZEWO: każda ma opcjonalny `parentId` wskazujący rodzica; brak
`parentId` to grupa najwyższego poziomu. API nie zwraca gotowej hierarchii -
dostajesz płaską listę rekordów `{id, parentId, name, ...}`, a drzewo składasz
sam po `parentId` (patrz Warianty). `id` grupy trafia do produktu jako `groupId`
(patrz playbook [products](../products/README.md)). `create()` i `update()`
zwracają `WriteResult` z `->id` założonej/zmienionej grupy.

## Mapowanie intencji użytkownika na dane API

| Użytkownik mówi | Potrzebujesz | Skąd wziąć |
|---|---|---|
| "zaloz grupe Oprogramowanie" | `name` | wprost z polecenia (wymagane) |
| "podgrupa Licencje w Oprogramowaniu" | `name` + `parentId` | `parentId`: `productGroups()->list(['name' => 'Oprogramowanie'])` |
| "grupa najwyzszego poziomu" | `name`, `parentId: null` | pomiń `parentId` |
| "znajdz grupe X" | filtr `list(['name' => 'X'])` | odczyt, nie zapis |
| "wszystkie grupy" | `iterate()` | pełny przebieg |
| "przenies grupe 12 pod 7" | `update(12, parentId: 7)` | id z odczytu |

## Scenariusz flagowy: podgrupa pod istniejącym rodzicem

Polecenie użytkownika: *"Zaloz podgrupe Licencje wewnatrz grupy Oprogramowanie."*

Twój tok postępowania:

1. Wyłuskaj: nazwę nowej grupy i nazwę rodzica.
2. Rozwiąż nazwę rodzica na `parentId` przez odczyt - nie zgaduj liczby;
   przerwij, jeśli rodzica nie ma albo jest niejednoznaczny.
3. Utwórz podgrupę z `parentId`.

```php
use TillioCrm\Api\Dto\ProductGroupInput;

// Krok 1: dane z polecenia.
$childName  = 'Licencje';
$parentName = 'Oprogramowanie';

// Krok 2: nazwa rodzica -> parentId przez odczyt. Bierzemy strone i sprawdzamy
// jednoznacznosc - kilka grup o tej samej nazwie znaczy "dopytaj", nie "wez pierwsza".
$candidates = $client->productGroups()->list(['name' => $parentName, 'limit' => 50]);
$exact = [];
foreach ($candidates as $g) {
    if (mb_strtolower((string) $g->name) === mb_strtolower($parentName)) {
        $exact[] = $g;
    }
}
if ($exact === []) {
    throw new RuntimeException("Nie znaleziono grupy nadrzednej '$parentName' - dopytaj albo zaloz ja najpierw.");
}
if (count($exact) > 1) {
    throw new RuntimeException("Wiele grup o nazwie '$parentName' - dopytaj, ktora ma byc rodzicem.");
}
$parentId = $exact[0]->id;

// Krok 3: utworzenie podgrupy. create() zwraca WriteResult.
$result = $client->productGroups()->create(new ProductGroupInput(
    name: $childName,
    parentId: $parentId,
));

echo "Utworzono podgrupe #{$result->id} '{$childName}' pod '{$parentName}' (#{$parentId}).\n";
if ($result->warnings !== []) {
    echo "Uwagi: " . implode('; ', $result->warnings) . "\n";
}
```

Co zwrócić użytkownikowi: id nowej grupy oraz do jakiego rodzica ją wpięto.
Jeśli rodzica nie było albo był niejednoznaczny - zapytaj, zanim utworzysz.

## Warianty

### Grupa najwyższego poziomu

```php
use TillioCrm\Api\Dto\ProductGroupInput;

// Bez parentId = grupa korzeniowa.
$client->productGroups()->create(new ProductGroupInput(name: 'Sprzet'));
```

### Złożenie drzewa z płaskiej listy

```php
// API oddaje plaska liste; hierarchie budujesz po parentId.
$byParent = [];   // parentId (0 = korzen) => list<ProductGroup>
foreach ($client->productGroups()->iterate() as $group) {
    $byParent[$group->parentId ?? 0][] = $group;
}
// $byParent[0] to grupy najwyzszego poziomu; dzieci grupy o id X to $byParent[X].
```

### Przeniesienie grupy pod innego rodzica

```php
use TillioCrm\Api\Dto\ProductGroupInput;

// update() wysyla tylko podane pola - tu zmieniamy samo parentId.
$client->productGroups()->update(12, new ProductGroupInput(parentId: 7));
```

## Pułapki

- **Drzewo składasz sam po `parentId`.** `list()`/`iterate()` oddają płaską
  listę, nie zagnieżdżoną strukturę. Grupa korzeniowa ma `parentId === null`.
- **Nie zgaduj `parentId`.** Rozwiąż nazwę rodzica przez odczyt i sprawdź
  jednoznaczność; kilka grup o tej samej nazwie to sygnał "dopytaj", nie "weź
  pierwszą z brzegu".
- **`status` i `priority` bez zdefiniowanej skali.** Kontrakt nie mapuje liczb
  na znaczenia dla tej instancji - ustawiaj je tylko, gdy wiesz, co znaczą;
  inaczej pomiń.
- **Uwaga na cykle przy przenoszeniu.** Ustawienie `parentId` na własnego
  potomka rozspójniłoby drzewo - przy "przenies X pod Y" upewnij się, że Y nie
  leży w poddrzewie X.
- **`create()`/`update()` zwracają `WriteResult`** (`->id`, `->warnings`), nie
  samo id. Szczegóły: sekcja "Co zwracają zapisy" w
  [ai_integration.md](../ai_integration.md).
