# Zamówienia

Tworzenie jest podpięte pod kartotekę. `update()` zmienia nagłówek częściowo
(status, daty, notatkę), a `products` w `update()` to PEŁNY stan pozycji
(wymiana); walutę zmienia się tylko razem z pozycjami (API >= 2.19.0). Bez pól
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

// Aktualizacja metadanych (bez products - pozycje nietknięte)
$client->orders()->update($result->id ?? 0, new OrderInput(orderStatusId: 2));

// Nadpisanie pozycji i waluty danymi z ERP (API >= 2.19.0): products = PEŁNY
// stan pozycji (z id = aktualizacja, bez id = nowa, nieobecna = usunięta),
// currency tylko razem z products, z cenami już w nowej walucie
$order = $client->orders()->get($result->id ?? 0);
$client->orders()->update($order->id, new OrderInput(
    currency: 'EUR',
    products: [
        new OrderProductInput(productSku: 'LIC-PRO', quantity: '2', price: '59.00', id: $order->products[0]->id),
    ],
));
```
