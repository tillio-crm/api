# Osoby kontaktowe

```php
use TillioCrm\Api\Dto\ContactInput;
use TillioCrm\Api\Dto\WriteOptions;

// Odczyt
$page = $client->contacts()->list(['contractorId' => 12345, 'limit' => 100]);
foreach ($client->contacts()->iterate() as $contact) {
    $contact->name;             // złożone z firstName + lastName
    $contact->contractorIds;    // WSZYSTKIE kartoteki, do których osoba jest przypięta
}
$contact = $client->contacts()->get(777);

// Tworzenie z wyszukaniem duplikatu po e-mailu
$result = $client->contacts()->create(
    new ContactInput(
        firstName: 'Jan',
        lastName: 'Przykładowy',
        email: 'jan@przyklad.example',
        contractorId: 12345,
    ),
    new WriteOptions(duplicateCheck: ['email']),
);

// Aktualizacja
$client->contacts()->update(777, new ContactInput(phone: '+48000000000'));

// Upsert: trafienie w duplikat = status "attached" (dane DOPIĘTE do istniejącej
// osoby), nie "updated" jak u kontrahentów
$batch = $client->contacts()->upsert(
    [new ContactInput(firstName: 'Anna', email: 'anna@przyklad.example')],
    new WriteOptions(duplicateCheck: ['email']),
);
$batch->attachedCount();
```
