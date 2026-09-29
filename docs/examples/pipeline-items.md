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

## Synchronizacja szans z kontrahentami (API >= 2.17.0)

`include=contractor` osadza kontrahenta szansy w `$item->contractor` (kształt jak
w `contractors()->list()`: z `customField`, bez adresów) - dla całej strony jednym
zapytaniem zamiast osobnego `contractors()->get()` na każdą szansę.

```php
// Szanse oznaczone flagą w polu niestandardowym, z kontrahentami, w jednym lejku.
foreach ($client->pipelineItems()->iterate([
    'customField'      => ['partner_flag' => 3],   // id opcji TAK
    'pipelineFunnelId' => 2,                       // lejek (API >= 2.17.0)
    'include'          => 'contractor',
]) as $item) {
    $item->pipelineFunnelId;          // lejek, wynika z etapu - tylko odczyt
    $item->url;                       // link "otwórz w CRM" do karty szansy
    $item->contractor?->taxId;        // null = kontrahenta już nie ma w CRM
    $item->contractor?->customField;  // pola niestandardowe kontrahenta
}

// To samo dla jednej szansy.
$item = $client->pipelineItems()->get(6, include: ['contractor']);
```

Bez `include` pola `contractor` nie ma w odpowiedzi, a `$item->contractor` jest
`null` - "nie pytano" od "kontrahenta nie ma" odróżnisz po
`array_key_exists('contractor', $item->raw)`.

## Wykrywanie usuniętych szans (API >= 2.17.0)

CRM kasuje szanse bez śladu, więc usunięcie widać tylko jako brak rekordu. Filtry
po liczbie całkowitej przyjmują listę do 100 wartości - paczka znanych id jednym
zapytaniem:

```php
use TillioCrm\Api\QueryBuilder;

$known = [6, 7, 9];   // id szans zapamiętane po swojej stronie
$deleted = [];
foreach (array_chunk($known, QueryBuilder::MAX_LIST_VALUES) as $chunk) {
    $found = [];
    foreach ($client->pipelineItems()->list(['id' => $chunk, 'limit' => QueryBuilder::MAX_LIST_VALUES]) as $item) {
        $found[] = $item->id;
    }
    array_push($deleted, ...array_diff($chunk, $found));
}
// $deleted - szanse usunięte w CRM (także te ze statusem "Usunięta", które znikają z listy)
```

## Notatka pod szansą (API >= 2.17.0)

```php
use TillioCrm\Api\Dto\NoteInput;

// Kontrahenta API bierze z szansy - notatka wisi na nim i jest przypięta do szansy
// (w odczycie Note::$pipelineId). pipelineItemId w NoteInput to 422: szansę wskazuje
// ścieżka. Szansa usunięta = NotFoundException.
$result = $client->pipelineItems()->createNote(6, new NoteInput(
    noteTypeId: 1,
    title: 'Konflikt blokady',
    body: '<p>Firma zablokowana przez innego partnera do 30.10.</p>',
));

// Odczyt notatek szans - także paczką id.
$client->notes()->list(['pipelineId' => [6, 7]]);
```
