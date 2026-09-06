# Usługi

Instancje pozycji katalogu u kontrahentów (katalog: [service-catalog.md](service-catalog.md)).
Kanon 2.0.0: `salesUserId` (handlowiec) i `ownerUserId` (opiekun) to osobne pola.

```php
use TillioCrm\Api\Dto\ServiceInput;

// Odczyt
$page = $client->services()->list(['contractorId' => 12345]);
foreach ($client->services()->iterate(['serviceStatusId' => 1]) as $service) {
    $service->catalogName;
    $service->payValue;        // string dziesiętny
    $service->agreementTo;     // koniec umowy
}
$service = $client->services()->get(4);

// Tworzenie: catalogId i contractorId wymagane
$result = $client->services()->create(new ServiceInput(
    catalogId: 5,
    contractorId: 12345,
    payValue: '99.00',
    currency: 'PLN',
    salesDate: '2026-08-27',
    salesUserId: 7,
));

// Aktualizacja
$client->services()->update($result->id ?? 0, new ServiceInput(note: 'Aneks od września.'));
```
