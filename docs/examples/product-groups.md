# Grupy produktów

```php
use TillioCrm\Api\Dto\ProductGroupInput;

// Drzewo przez parentId
$page = $client->productGroups()->list();
foreach ($page as $group) {
    $group->name;
    $group->parentId;   // null = poziom główny
}

// Pełny przebieg wszystkich stron (generator)
foreach ($client->productGroups()->iterate() as $group) {
    $group->name;
}

$group = $client->productGroups()->get(5);

$result = $client->productGroups()->create(new ProductGroupInput(name: 'Licencje', color: '#4b78c5'));
$client->productGroups()->update($result->id ?? 0, new ProductGroupInput(priority: 10));
```
