# Zamówienia

Tworzenie jest podpięte pod kartotekę; zamówienia nie podlegają wstecznej
edycji pozycji - `update()` zmienia metadane (status, daty, notatkę). Bez pól
niestandardowych - do wyszukiwania służy `number`.

```php
use TillioCrm\Api\Dto\OrderInput;
use TillioCrm\Api\Dto\OrderProductInput;

// Odczyt
$page = $client->orders()->list(['contractorId' => 12345]);
$order = $client->orders()->get(77);
foreach ($order->products as $line) {
    $line->name;
    $line->quantity;    // stringi dziesiętne
    $line->price;
}

// Tworzenie: pozycja WYMAGA productId albo productSku (produkt musi istnieć
// w katalogu - nieznane SKU to 422); customName tylko nadpisuje nazwę z katalogu
$result = $client->orders()->create(12345, new OrderInput(
    products: [
        new OrderProductInput(productSku: 'LIC-PRO', quantity: '2'),
        new OrderProductInput(productId: 42, customName: 'Licencja PRO - promocja', quantity: '1', price: '399.00'),
    ],
    orderDate: '2026-08-01',
    currency: 'PLN',
));

// Aktualizacja metadanych
$client->orders()->update($result->id ?? 0, new OrderInput(orderStatusId: 2));
```
