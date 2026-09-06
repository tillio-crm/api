# Kontrahenci

```php
use TillioCrm\Api\Dto\ContractorInput;
use TillioCrm\Api\Dto\AddressInput;
use TillioCrm\Api\Dto\WriteOptions;

// --- Odczyt --------------------------------------------------------------------

// Strona listy z filtrami (nieznany parametr = 400, literówka nie przejdzie)
$page = $client->contractors()->list([
    'taxId' => '0000000000',        // porównanie po wartości znormalizowanej
    'updatedAfter' => '2026-01-01T00:00:00+00:00',
    'include' => 'address',
    'limit' => 100,
]);

// Pełny przebieg - generator, wymuszone sort=id (nic nie zginie w trakcie)
foreach ($client->contractors()->iterate(['createdAfter' => '2026-01-01T00:00:00+00:00']) as $contractor) {
    $contractor->name;
    $contractor->customField['erp_id'] ?? null;   // wartości pól niestandardowych
    $contractor->address[0]->city ?? null;        // LISTA adresów
    $contractor->raw;                             // pełny surowy rekord
}

// Wyszukanie po polu niestandardowym; rekordy BEZ wartości pola - jawnym enumem
use TillioCrm\Api\CustomFieldFilter;
$unlinked = $client->contractors()->list([
    'customField' => ['erp_id' => CustomFieldFilter::NotSet],
]);

// Pojedyncza kartoteka - trasa nie przyjmuje parametrów (include=address działa
// tylko na liście); adresy pobiera addresses()
$contractor = $client->contractors()->get(12345);

// --- Zapis ---------------------------------------------------------------------

// Tworzenie z wyszukaniem duplikatu. Trafienie w istniejący rekord = 200 z tym
// rekordem (bez zmiany danych) - rozróżniaj po $result->created.
$result = $client->contractors()->create(
    new ContractorInput(
        name: 'Przykładowa Firma Sp. z o.o.',
        alias: 'przykladowa-firma',            // WYMAGANY przy tworzeniu
        contractorTypeId: 1,                   // typy: dictionaries()->contractorTypes()
        taxId: '0000000000',
        email: 'biuro@przyklad.example',
        customField: ['erp_id' => 'K-0001'],
        address: [new AddressInput(addressTypeId: 1, city: 'Warszawa', street: 'Przykładowa 1')],
    ),
    new WriteOptions(duplicateCheck: ['custom:erp_id', 'taxId'], createSystemNote: true),
);
// UWAGA: każde pole z duplicateCheck MUSI mieć wartość w payloadzie - inaczej
// SDK rzuci IncompleteDuplicateCheckException zanim żądanie wyjdzie
// (API pominęłoby warunek po cichu i założyło duplikat).

if (!$result->created) {
    $existingId = $result->id;                 // znaleziony istniejący rekord
    $result->matchedBy();                      // 'taxId' albo 'custom:erp_id'
}

// Aktualizacja - tylko podane pola; JAWNY null (czyszczenie) przez tablicę:
$client->contractors()->update(12345, new ContractorInput(phone: '+48000000000'));
$client->contractors()->update(12345, ['externalId' => null]);   // odpięcie klucza integracji

// Upsert paczką - HTTP zawsze 200, wynik per item:
$batch = $client->contractors()->upsert(
    [new ContractorInput(name: 'A', alias: 'a', contractorTypeId: 1, taxId: '1111111111')],
    new WriteOptions(duplicateCheck: ['taxId']),
);
if ($batch->hasFailures()) {
    foreach ($batch->failed() as $item) {
        // $item['index'] - pozycja w wysłanej paczce, $item['errors'] - powody
    }
}

// --- Adresy (osobne trasy) -----------------------------------------------------

$addresses = $client->contractors()->addresses(12345);           // list<Address>
$client->contractors()->addAddress(12345, new AddressInput(addressTypeId: 2, city: 'Kraków'));
$client->contractors()->updateAddress($addresses[0]->id, ['city' => 'Gdańsk']);
$client->contractors()->deleteAddress($addresses[0]->id);
```
