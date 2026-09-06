# Szanse sprzedaży

Pozycje lejków sprzedaży. Lejki i ich etapy: `dictionaries()->pipelineFunnels()`.

```php
use TillioCrm\Api\Dto\PipelineItemInput;

// Odczyt
$page = $client->pipelineItems()->list(['pipelineStageId' => 5]);
foreach ($client->pipelineItems()->iterate() as $item) {
    $item->name;
    $item->amount;         // string dziesiętny
    $item->probability;    // procent
    $item->externalId;     // klucz integracji
}
$item = $client->pipelineItems()->get(3);

// Tworzenie: name, pipelineStageId i contractorId wymagane
$result = $client->pipelineItems()->create(new PipelineItemInput(
    name: 'Oferta na system',
    pipelineStageId: 5,
    contractorId: 12345,
    amount: '15000.00',
    currency: 'PLN',
    closeDate: '2026-10-31',
));

// Aktualizacja (etap/status zmienia proces lejka, nie ten zapis)
$client->pipelineItems()->update($result->id ?? 0, new PipelineItemInput(probability: 60));
```
