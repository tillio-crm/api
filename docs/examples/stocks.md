# Stany magazynowe

Rekord identyfikuje **para magazyn/produkt** - stany nie mają własnego `id`.
To odzwierciedlenie read-only ilości z magazynu, bez filtra przyrostowego
(czyta się pełnym przebiegiem).

```php
use TillioCrm\Api\Dto\StockInput;

// Odczyt
$page = $client->stocks()->list(['warehouseId' => 3]);

// Pełny przebieg - iterate() BEZ wymuszania sort=id (stany nie mają
// sortowalnego identyfikatora; porządek domyka API parą kluczy)
foreach ($client->stocks()->iterate() as $stock) {
    $stock->warehouseId;
    $stock->productId;
    $stock->quantity;      // string dziesiętny ("12.500")
}

// Zmiana stanu: absolutna (quantity) ALBO względna (adjustBy) - dokładnie jedno.
// Odpowiedź niesie pełny stan po zapisie (DTO Stock, nie WriteResult).
$stock = $client->stocks()->update(3, 42, new StockInput(quantity: '100.000'));
$stock = $client->stocks()->update(3, 42, new StockInput(adjustBy: '-2'));
$stock->quantity;      // stan po korekcie
```
