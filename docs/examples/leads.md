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
    phone: '+48000000000',
    emails: ['jan@przyklad.example'],
    ownerUserId: 7,
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
    new LeadInput(title: 'Acme', phone: '+48000000001'),
    new LeadInput(title: 'Beta', emails: ['biuro@beta.example']),
]);
$batch->attachedCount();
$batch->hasFailures();

// Aktualizacja. emails w PUT to KOMPLETNA lista docelowa ([] usuwa wszystkie).
$client->leads()->update($result->id ?? 0, new LeadInput(note: 'Umówiony na demo.'));

// Notatka pod leadem (API >= 2.13.0)
$client->leads()->createNote($result->id ?? 0, new NoteInput(noteTypeId: 1, title: 'Rozmowa kwalifikacyjna'));
```
