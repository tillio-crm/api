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
    $item->externalId;     // klucz integracji (tylko odczyt i filtr)
    $item->contactIds;     // kontakty przypięte do szansy (API >= 2.15.0)
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
    contactIds: [50],      // tylko kontakty kontrahenta szansy - obcy to 422
));

// Aktualizacja. contactIds w PUT to KOMPLETNA lista docelowa ([] odpina wszystkie);
// etapu ani statusu ten zapis nie przyjmuje - są na to osobne metody.
$client->pipelineItems()->update($result->id ?? 0, new PipelineItemInput(probability: 60));

// Przesunięcie na inny etap (API >= 2.15.0). Etap wymagający pól, których szansa
// nie ma, to 422 body.requiredFieldsMissing z ich listą.
$client->pipelineItems()->changeStage($result->id ?? 0, 6);

// Zamknięcie: 3 = wygrana, 2 = stracona, 1 = ponowne otwarcie. Powód i notatkę
// przyjmują tylko 2 i 3, a powód musi należeć do statusu i lejka szansy.
$reasons = $client->dictionaries()->pipelineStatusChangeReasons(2, 1);
$client->pipelineItems()->changeStatus($result->id ?? 0, 2, $reasons[0]->id ?? null, 'Za drogo.');
$client->pipelineItems()->changeStatus($result->id ?? 0, 3);

// Szanse jednej osoby kontaktowej (API >= 2.15.0)
$client->pipelineItems()->list(['contactId' => 50]);
```
