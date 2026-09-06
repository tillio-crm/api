# Katalog usług

Szablony usług + grupy katalogu (drzewo). Wymaga API >= 2.0.4
(zapis `defaultPayValue` - ta sama nazwa co odczyt).

```php
use TillioCrm\Api\Dto\ServiceCatalogItemInput;
use TillioCrm\Api\Dto\ServiceCatalogGroupInput;

// Pozycje katalogu
$page = $client->serviceCatalog()->list(['limit' => 100]);
foreach ($page as $item) {
    $item->name;
    $item->defaultPayValue;    // string dziesiętny
    $item->groupName;
}

// Pełny przebieg wszystkich stron (generator)
foreach ($client->serviceCatalog()->iterate(['active' => true]) as $item) {
    $item->name;
}

$result = $client->serviceCatalog()->create(new ServiceCatalogItemInput(
    name: 'Abonament serwisowy',
    groupId: 59,
    defaultPayValue: '99.00',
    currency: 'PLN',
    isAgreement: true,
));
$client->serviceCatalog()->update($result->id ?? 0, new ServiceCatalogItemInput(active: false));

// Grupy katalogu (drzewo przez parentId) - zwykła lista ServiceCatalogGroup,
// bez stronicowania i bez filtrów
$groups = $client->serviceCatalog()->groups();
$client->serviceCatalog()->createGroup(new ServiceCatalogGroupInput(name: 'Usługi serwisowe', color: '#4b78c5'));
$client->serviceCatalog()->updateGroup(59, new ServiceCatalogGroupInput(order: 2));
```
