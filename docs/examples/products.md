# Produkty

```php
use TillioCrm\Api\Dto\ProductInput;
use TillioCrm\Api\Dto\WriteOptions;

// Odczyt. UWAGA: produkty NIE mają pól niestandardowych w API - kluczem
// integracji jest externalId (a duplicateCheck zna też sku i ean).
$page = $client->products()->list(['name' => 'Licencja', 'limit' => 100]);
foreach ($client->products()->iterate() as $product) {
    $product->sku;
    $product->price;      // string dziesiętny ("499.00") - nie float!
}
$product = $client->products()->get(42);

// Tworzenie z wyszukaniem duplikatu po SKU
$result = $client->products()->create(
    new ProductInput(
        name: 'Licencja PRO',
        sku: 'LIC-PRO',
        externalId: 'ERP-001',
        price: '499.00',
        currency: 'PLN',
        taxRate: '23',
    ),
    new WriteOptions(duplicateCheck: ['sku']),
);

// Aktualizacja
$client->products()->update(42, new ProductInput(price: '549.00'));
```
