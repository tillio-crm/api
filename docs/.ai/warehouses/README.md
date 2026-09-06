# Playbook: Magazyny (warehouses)

Realizacja poleceń użytkownika dotyczących magazynów: zakładanie magazynu,
aktualizacja danych, wyszukiwanie po nazwie/symbolu do operacji na stanach.
Pisane dla asystenta AI - zakłada wspólne wzorce z
[ai_integration.md](../ai_integration.md) (zwłaszcza `WriteResult` i "Złotą
zasadę: nie zgaduj id").

## Pola

Zapis idzie przez `WarehouseInput` (`src/Dto/WarehouseInput.php`). Named
arguments; `null` = pola nie wysyłamy. Przy tworzeniu API wymaga `name` ORAZ
`symbol` - to jedyny zasób z tej czwórki, który wymaga dwóch pól.

| pole | typ | po co (i skąd wziąć id/referencję) |
|---|---|---|
| `name` | `?string` | Nazwa magazynu. WYMAGANE przy `create()`. |
| `symbol` | `?string` | Krótki symbol/kod magazynu (np. `"MAG-GL"`). WYMAGANE przy `create()`. Filtr `list(['symbol' => ...])` - najpewniejszy uchwyt na magazyn. |
| `status` | `?int` | Status magazynu (aktywny/zamknięty). Kontrakt nie mapuje skali liczbowej dla tej instancji - nie zgaduj; podejrzyj `Warehouse->status` istniejącego magazynu albo pomiń. |
| `createdAt` | `?string` | Data utworzenia. Tylko przy imporcie historycznym; ISO 8601. |
| `creatorUserId` | `?int` | Autor. Tylko przy imporcie historycznym; `resolveUserId()`. |

Pola tylko do ODCZYTU (`Warehouse`, `src/Dto/Warehouse.php`): `id` (potrzebne
jako `warehouseId` w operacjach na stanach - patrz playbook
[stocks](../stocks/README.md)). Pełny surowy rekord: `Warehouse->raw`.

## Model danych

Magazyn to prosty rekord `{id, name, symbol, status}`. Nie ma zagnieżdżeń ani
pól niestandardowych. `symbol` jest krótkim, ludzkim uchwytem (jak `sku` przy
produkcie) i najlepiej filtruje listę. `id` magazynu jest kluczem do stanów
magazynowych: stany żyją w osobnym zasobie `Stocks`, a rekord stanu identyfikuje
para magazyn/produkt (patrz playbook [stocks](../stocks/README.md)).
`create()`/`update()` zwracają `WriteResult`.

## Mapowanie intencji użytkownika na dane API

| Użytkownik mówi | Potrzebujesz | Skąd wziąć |
|---|---|---|
| "zaloz magazyn Glowny" | `name` + `symbol` | nazwa wprost; symbol z polecenia albo dopytaj (wymagane oba) |
| "symbol MAG-GL" | `symbol` | wprost |
| "znajdz magazyn po symbolu" | filtr `list(['symbol' => ...])` | odczyt, nie zapis |
| "magazyn Glowny" (do stanow) | `warehouseId` | `warehouses()->list(['name' => 'Glowny'])`, weź `->id` |
| "wszystkie magazyny" | `iterate()` | pełny przebieg |
| "zmien nazwe magazynu 7" | `update(7, name: ...)` | id z odczytu |

## Scenariusz flagowy: założenie magazynu

Polecenie użytkownika: *"Zaloz magazyn Glowny o symbolu MAG-GL."*

Twój tok postępowania:

1. Wyłuskaj: nazwę i symbol. Oba są wymagane - jeśli użytkownik nie podał
   symbolu, dopytaj, nie wymyślaj go.
2. (Opcjonalnie) sprawdź, czy magazyn o tym symbolu już nie istnieje - ten zasób
   nie ma `duplicateCheck`, więc ochronę robisz sam odczytem.
3. Utwórz magazyn.

```php
use TillioCrm\Api\Dto\WarehouseInput;

// Krok 1: dane z polecenia. Oba pola wymagane.
$name   = 'Glowny';
$symbol = 'MAG-GL';
if ($symbol === '') {
    // create() bez symbolu to 422 - lepiej dopytac niz wymyslac symbol.
    throw new RuntimeException('Brak symbolu magazynu - dopytaj uzytkownika, symbol jest wymagany.');
}

// Krok 2: reczna ochrona przed dublem (brak duplicateCheck w tym zasobie).
$existing = $client->warehouses()->list(['symbol' => $symbol, 'limit' => 1])->first();
if ($existing !== null) {
    echo "Magazyn o symbolu {$symbol} juz istnieje (#{$existing->id}).\n";
    return;
}

// Krok 3: utworzenie. create() zwraca WriteResult.
$result = $client->warehouses()->create(new WarehouseInput(
    name: $name,
    symbol: $symbol,
));

echo "Utworzono magazyn #{$result->id} '{$name}' (symbol {$symbol}).\n";
if ($result->warnings !== []) {
    echo "Uwagi: " . implode('; ', $result->warnings) . "\n";
}
```

Co zwrócić użytkownikowi: id magazynu, nazwę i symbol. Jeśli symbol był zajęty -
powiedz, który magazyn go już nosi, zamiast tworzyć drugi.

## Warianty

### Wyszukanie magazynu do operacji na stanach

```php
// warehouseId do playbooka stocks - najpewniej po symbolu (krotki, ludzki).
$warehouse = $client->warehouses()->list(['symbol' => 'MAG-GL', 'limit' => 1])->first();
$warehouseId = $warehouse?->id;   // null = magazynu nie ma, dopytaj
```

### Pełny przebieg magazynów

```php
// iterate() wymusza sort=id i przechodzi wszystkie strony.
foreach ($client->warehouses()->iterate() as $warehouse) {
    $warehouse->symbol;
    $warehouse->name;
}
```

### Aktualizacja nazwy magazynu

```php
use TillioCrm\Api\Dto\WarehouseInput;

// update() wysyla tylko podane pola.
$client->warehouses()->update(7, new WarehouseInput(name: 'Glowny (Warszawa)'));
```

## Pułapki

- **`create()` wymaga `name` I `symbol`.** Sam `name` to 422. Gdy użytkownik nie
  podał symbolu, dopytaj - nie wymyślaj go, bo symbol jest ludzkim uchwytem na
  magazyn i pomyłka tu utrudni późniejsze wyszukiwanie.
- **Brak `duplicateCheck` w tym zasobie.** Nie ma automatycznej ochrony przed
  dublem po symbolu - jeśli jej potrzebujesz, zrób odczyt `list(['symbol' => ...])`
  przed `create()`.
- **`status` bez zdefiniowanej skali.** Kontrakt nie mapuje liczb dla tej
  instancji - podejrzyj `Warehouse->status` istniejącego magazynu albo pomiń
  pole.
- **Stany żyją osobno.** Magazyn tu tylko zakładasz i opisujesz; zmiany ilości
  robisz w zasobie `Stocks` (patrz playbook [stocks](../stocks/README.md)),
  używając `Warehouse->id` jako `warehouseId`.
- **`create()`/`update()` zwracają `WriteResult`** (`->id`, `->warnings`), nie
  samo id. Szczegóły: sekcja "Co zwracają zapisy" w
  [ai_integration.md](../ai_integration.md).
