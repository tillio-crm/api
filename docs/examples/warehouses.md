# Magazyny

```php
use TillioCrm\Api\Dto\WarehouseInput;

$page = $client->warehouses()->list();

// Pełny przebieg wszystkich stron (generator)
foreach ($client->warehouses()->iterate() as $warehouse) {
    $warehouse->symbol;
}

$warehouse = $client->warehouses()->get(3);

// Tworzenie: name i symbol wymagane
$result = $client->warehouses()->create(new WarehouseInput(name: 'Magazyn główny', symbol: 'MG'));
$client->warehouses()->update(3, new WarehouseInput(name: 'Magazyn centralny'));
```

Stany magazynowe żyją w osobnym zasobie - patrz [stocks.md](stocks.md).
