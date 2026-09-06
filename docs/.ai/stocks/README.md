# Playbook: Stany magazynowe (stocks)

Realizacja poleceń użytkownika dotyczących ilości na magazynie: odczyt stanów,
przyjęcie/wydanie towaru (korekta względna), ustawienie stanu na konkretną
wartość (zmiana absolutna). Pisane dla asystenta AI - zakłada wspólne wzorce z
[ai_integration.md](../ai_integration.md) (zwłaszcza "Złotą zasadę: nie zgaduj
id"). UWAGA: `stocks()->update()` NIE zwraca `WriteResult` - patrz niżej.

## Pola

Zmiana stanu idzie przez `StockInput` (`src/Dto/StockInput.php`). Named
arguments; `null` = pola nie wysyłamy. Podajesz DOKŁADNIE JEDNO z dwóch pól.

| pole | typ | po co (i skąd wziąć) |
|---|---|---|
| `quantity` | `?string` | Docelowy stan ABSOLUTNY (`"100.000"`). Ustawia ilość na tę wartość niezależnie od bieżącej. STRING dziesiętny, nie float. |
| `adjustBy` | `?string` | Korekta WZGLĘDNA wobec bieżącego stanu (`"50"` = przyjęcie, `"-2"` = wydanie). STRING dziesiętny, może być ujemny. |

Adresowanie rekordu nie jest polem `StockInput` - podajesz je jako argumenty
`update($warehouseId, $productId, $input)`:

| argument | typ | skąd wziąć |
|---|---|---|
| `warehouseId` | `int` | `warehouses()->list(['symbol' => ...])` albo `['name' => ...]`, weź `->id` (patrz playbook [warehouses](../warehouses/README.md)) |
| `productId` | `int` | `products()->list(['sku' => ...])` albo `['externalId' => ...]`, weź `->id` (patrz playbook [products](../products/README.md)) |

Pola tylko do ODCZYTU (`Stock`, `src/Dto/Stock.php`): `warehouseId`, `productId`
(razem identyfikują rekord - nie ma własnego `id`), `quantity` (string
dziesiętny). Pełny surowy rekord: `Stock->raw`.

## Model danych

Stan magazynowy to READ-ONLY odzwierciedlenie ilości: rekord identyfikuje PARA
magazyn/produkt i NIE ma własnego `id`. Dlatego:

- odczyt przez `list()`/`iterate()` (filtry `warehouseId`, `productId`);
  `iterate()` NIE wymusza `sort=id` (stany nie mają sortowalnego identyfikatora -
  stały porządek domyka API parą kluczy magazyn/produkt);
- jedyny zapis to `update($warehouseId, $productId, StockInput)` - nie ma tu
  `create()` ani `get(id)`;
- `update()` ZWRACA DTO `Stock` z pełnym stanem PO zapisie (bez koperty `info`,
  bo nie ma własnego id) - to wyjątek od reguły `WriteResult`.

## Mapowanie intencji użytkownika na dane API

| Użytkownik mówi | Potrzebujesz | Skąd wziąć |
|---|---|---|
| "przyjmij 50 szt produktu X na magazyn Y" | `adjustBy: "50"` | względna korekta w górę |
| "wydaj 2 szt" / "zdejmij 2" | `adjustBy: "-2"` | względna korekta w dół (minus) |
| "ustaw stan na 100" / "po inwentaryzacji jest 100" | `quantity: "100.000"` | absolutna wartość |
| "magazyn Glowny" | `warehouseId` | `warehouses()->list(['name'/'symbol' => ...])`, `->id` |
| "produkt LIC-PRO" | `productId` | `products()->list(['sku' => 'LIC-PRO'])`, `->id` |
| "ile jest produktu X" | filtr `list(['productId' => ...])` | odczyt |

Reguła wyboru pola: "o ile" (przyjęcie/wydanie, różnica) -> `adjustBy`; "na ile"
(stan docelowy, inwentaryzacja) -> `quantity`. Nigdy oba naraz.

## Scenariusz flagowy: przyjęcie towaru na magazyn

Polecenie użytkownika: *"Przyjmij 50 sztuk Licencji PRO (SKU LIC-PRO) na magazyn
Glowny."*

Twój tok postępowania:

1. Rozwiąż magazyn na `warehouseId` i produkt na `productId` przez odczyt -
   NIGDY nie zgaduj tych liczb.
2. Przerwij, jeśli którykolwiek się nie rozwiązał.
3. To przyjęcie (o ile, w górę) -> korekta WZGLĘDNA `adjustBy`, nie `quantity`.
4. Wywołaj `update()`; odbierz zwrócony `Stock` (stan po operacji).

```php
use TillioCrm\Api\Dto\StockInput;

// Krok 1: rozwiaz uchwyty na id przez odczyt. Symbol/sku sa ludzkimi kluczami.
$warehouse = $client->warehouses()->list(['name' => 'Glowny', 'limit' => 1])->first();
$product   = $client->products()->list(['sku' => 'LIC-PRO', 'limit' => 1])->first();

// Krok 2: bez kompletu id nie ruszamy - dopytaj zamiast zgadywac.
if ($warehouse === null) {
    throw new RuntimeException("Nie znaleziono magazynu 'Glowny' - dopytaj albo zaloz magazyn.");
}
if ($product === null) {
    throw new RuntimeException("Nie znaleziono produktu SKU 'LIC-PRO' - dopytaj albo dodaj produkt.");
}

// Krok 3 + 4: przyjecie to korekta WZGLEDNA. Ilosc jako STRING dziesietny.
// DOKLADNIE JEDNO z quantity/adjustBy - tu adjustBy. update() zwraca Stock,
// nie WriteResult: dostajemy pelny stan PO operacji.
$stock = $client->stocks()->update(
    $warehouse->id,
    $product->id,
    new StockInput(adjustBy: '50'),
);

echo "Przyjeto 50 szt. Stan produktu #{$product->id} na magazynie #{$warehouse->id}: {$stock->quantity}.\n";
```

Co zwrócić użytkownikowi: stan po operacji (`$stock->quantity`) oraz na jakim
magazynie i którego produktu dotyczy. Jeśli któregoś id nie rozwiązano - zapytaj,
zanim ruszysz stan.

## Warianty

### Ustawienie stanu absolutnie (po inwentaryzacji)

```php
use TillioCrm\Api\Dto\StockInput;

// "Po spisie jest 100" -> quantity (wartosc docelowa), nie adjustBy.
$stock = $client->stocks()->update($warehouseId, $productId, new StockInput(quantity: '100.000'));
$stock->quantity;   // "100.000"
```

### Wydanie towaru (korekta ujemna)

```php
use TillioCrm\Api\Dto\StockInput;

// "Wydaj 2 szt" -> adjustBy ujemne.
$stock = $client->stocks()->update($warehouseId, $productId, new StockInput(adjustBy: '-2'));
```

### Odczyt stanów jednego magazynu

```php
// Filtry: warehouseId, productId.
$page = $client->stocks()->list(['warehouseId' => 3]);
foreach ($page as $stock) {
    $stock->productId;
    $stock->quantity;   // string dziesietny
}
```

### Pełny przebieg wszystkich stanów

```php
// iterate() BEZ sort=id - stany nie maja sortowalnego id; porzadek domyka API
// para kluczy magazyn/produkt. Nie dokladaj wlasnego sort=id.
foreach ($client->stocks()->iterate() as $stock) {
    $stock->warehouseId;
    $stock->productId;
    $stock->quantity;
}
```

## Pułapki

- **Dokładnie JEDNO z `quantity`/`adjustBy`.** Oba naraz albo żadne to błąd
  walidacji (422). Reguła: "o ile" (przyjęcie/wydanie) -> `adjustBy`; "na ile"
  (stan docelowy) -> `quantity`.
- **Ilości to STRINGI dziesiętne, nie floaty.** `quantity` i `adjustBy` podawaj
  jako `"100.000"`, `"-2"`, `"0.5"`. Rzutowanie na `float` gubi precyzję przy
  ułamkowych jednostkach; jeśli musisz liczyć różnice, użyj `bcmath` i wynik
  sformatuj z powrotem jako string.
- **`update()` zwraca `Stock`, NIE `WriteResult`.** To wyjątek od reguły
  zapisów - odbierasz DTO z `->quantity` (stan po operacji), nie obiekt z `->id`.
  Stany nie mają własnego id, więc nie ma tu `->created` ani `isDuplicate()`.
- **Rekord identyfikuje PARA magazyn/produkt.** Nie ma `stocks()->get(id)` ani
  `create()` - stan "powstaje" jako efekt istnienia magazynu i produktu, a Ty go
  tylko odczytujesz i korygujesz.
- **`iterate()` bez `sort=id`.** Stany nie mają sortowalnego identyfikatora - nie
  dokładaj `sort=id` do filtrów, bo trasa go nie obsługuje; stały porządek zapewnia
  API parą kluczy magazyn/produkt.
- **Nie zgaduj `warehouseId`/`productId`.** Rozwiąż oba przez odczyt (symbol
  magazynu, sku/externalId produktu) i przerwij, jeśli któryś się nie rozwiązał -
  cicha pomyłka ruszyłaby stan nie tego towaru albo nie tego magazynu.
