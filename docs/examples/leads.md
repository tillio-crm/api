# Leady

```php
use TillioCrm\Api\Dto\LeadInput;

// Odczyt. Rozróżnienie: leadStatusId to status w procesie leadowym,
// leadStageId to etap tego procesu (oba: dictionaries()->leadProcesses()).
$page = $client->leads()->list(['leadStatusId' => 11]);   // filtr działa też po leadStageId
foreach ($client->leads()->iterate() as $lead) {
    $lead->title;
    $lead->companyName;
    $lead->emails;          // lista adresów
    $lead->leadStatusId;    // status w procesie leadowym
    $lead->leadStageId;     // etap procesu leadowego
}
$lead = $client->leads()->get(9);

// Tworzenie (title wymagane)
$result = $client->leads()->create(new LeadInput(
    title: 'Zapytanie z formularza',
    companyName: 'Przykładowa Firma',
    firstName: 'Jan',
    lastName: 'Przykładowy',
    phone: '+48000000000',
    ownerUserId: 7,
));

// Aktualizacja
$client->leads()->update($result->id ?? 0, new LeadInput(note: 'Umówiony na demo.'));
```
