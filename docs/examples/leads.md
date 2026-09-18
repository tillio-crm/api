# Leady

```php
use TillioCrm\Api\Dto\LeadInput;
use TillioCrm\Api\Dto\NoteInput;
use TillioCrm\Api\Dto\WriteOptions;

// Odczyt. Rozróżnienie: leadStatusId to status w procesie leadowym,
// leadStageId to etap tego procesu (oba: dictionaries()->leadProcesses()).
$page = $client->leads()->list(['leadStatusId' => 11]);   // filtr działa też po leadStageId
foreach ($client->leads()->iterate() as $lead) {
    $lead->title;
    $lead->companyName;
    $lead->emails;          // lista adresów, główny pierwszy
    $lead->leadStatusId;    // status w procesie leadowym
    $lead->leadStageId;     // etap procesu leadowego
    $lead->categoryId;      // kategoria (dictionaries()->leadCategories())
    $lead->leadTagIds;      // tagi (dictionaries()->leadTags())
}
$lead = $client->leads()->get(9);

// Tworzenie (title wymagane). Od API 2.13.0 API najpierw szuka istniejącego
// leada po e-mailu i telefonie - trafienie = 200 z tym leadem i podpiętymi
// danymi zamiast dubla.
$result = $client->leads()->create(new LeadInput(
    title: 'Zapytanie z formularza',
    companyName: 'Przykładowa Firma',
    firstName: 'Jan',
    lastName: 'Przykładowy',
    phone: '+48601234567',
    emails: ['jan@przyklad.example'],
    ownerUserId: 7,
    priority: 1,            // 0 standard, 1 wysoki, 2 najwyższy - inna wartość to 422
    categoryId: 3,
    leadTagIds: [12, 15],   // przy podpięciu tagi są DOKŁADANE do istniejącego leada
));
$result->created;        // false = lead już był, dane dopięte
$result->matchedBy();    // np. "email"

// Własny klucz integracji w polu niestandardowym zamiast e-maila
$client->leads()->create(
    new LeadInput(title: 'Formularz', customField: ['zapier_id' => 'ZAP-1042']),
    new WriteOptions(duplicateCheck: ['custom:zapier_id']),
);

// Zawsze nowy lead, bez sprawdzania
$client->leads()->create(new LeadInput(title: 'Drugi lead'), new WriteOptions(allowDuplicates: true));

// Upsert paczki (do 100): statusy created|attached|failed per pozycja
$batch = $client->leads()->upsert([
    new LeadInput(title: 'Acme', phone: '+48601800001'),
    new LeadInput(title: 'Beta', emails: ['biuro@beta.example']),
]);
$batch->attachedCount();
$batch->hasFailures();

// Aktualizacja. emails i leadTagIds w PUT to KOMPLETNE listy docelowe
// ([] czyści je do zera); leadStatusId tu nie przejdzie - patrz changeStatus().
$client->leads()->update($result->id ?? 0, new LeadInput(note: 'Umówiony na demo.'));

// Zdjęcie kategorii wymaga jawnego nulla, więc tablicy zamiast DTO.
$client->leads()->update($result->id ?? 0, ['categoryId' => null]);

// Zmiana statusu (API >= 2.15.0) - osobna trasa, bo CRM prowadzi historię.
// Powód i notatkę przyjmują TYLKO statusy kończące (qualified/disqualified).
$reasons = $client->dictionaries()->leadStatusChangeReasons(403);
$client->leads()->changeStatus($result->id ?? 0, 403, $reasons[0]->id ?? null, 'Klient wybrał konkurencję.');

// Notatka pod leadem (API >= 2.13.0)
$client->leads()->createNote($result->id ?? 0, new NoteInput(noteTypeId: 1, title: 'Rozmowa kwalifikacyjna'));
```
