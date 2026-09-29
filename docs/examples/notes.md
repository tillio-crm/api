# Notatki

```php
use TillioCrm\Api\Dto\CategoryInput;
use TillioCrm\Api\Dto\NoteInput;
use TillioCrm\Api\Dto\NoteTemplateInput;
use TillioCrm\Api\Transport\FileUpload;

// Odczyt
$page = $client->notes()->list(['contractorId' => 12345]);
foreach ($client->notes()->iterate(['noteTypeId' => 1]) as $note) {
    $note->title;
    $note->body;
    $note->pinned;
}
$note = $client->notes()->get(555);

// Tworzenie pod kartoteką (noteTypeId i title wymagane;
// typy: dictionaries()->noteTypes())
$result = $client->notes()->create(12345, new NoteInput(
    noteTypeId: 1,
    title: 'Rozmowa telefoniczna',
    body: 'Ustalenia: ...',
    noteDate: '2026-08-27',
));

// Pod szansą sprzedaży bez podawania kontrahenta - API bierze go z szansy
// (API >= 2.17.0); pipelineItemId w NoteInput to tu 422, szansę wskazuje ścieżka
$client->pipelineItems()->createNote(6, new NoteInput(noteTypeId: 1, title: 'Konflikt blokady'));

// Notatki paczki leadów albo szans jednym zapytaniem (API >= 2.17.0, do 100 id)
$client->notes()->list(['pipelineId' => [6, 7]]);

// Aktualizacja
$client->notes()->update($result->id ?? 0, new NoteInput(pinned: true));

// Załączniki - upload WYŁĄCZNIE multipart, BEZ retry (powtórka po timeoutcie,
// który doszedł, zostawiłaby w CRM drugi plik)
$client->notes()->addAttachment($result->id ?? 0, FileUpload::fromPath('/sciezka/oferta.pdf'));

// Odczyt załączników: downloadUrl żyje ~1 minutę - pobieraj od razu
foreach ($client->notes()->attachments($result->id ?? 0) as $attachment) {
    if ($attachment->downloadUrl !== null) {
        $bytes = $client->download($attachment->downloadUrl);
    }
}

// --- Szablony notatek (wymaga API >= 2.4.0) ------------------------------------
// Kategorie szablonów: płaska lista, drzewo składa się po parentId
$categories = $client->notes()->templateCategories();
$category = $client->notes()->createTemplateCategory(new CategoryInput(name: 'Handel'));

// Nowy szablon (noteTypeId, name i title wymagane); alias to skrót do wywołania
// w edytorze - CRM znormalizuje go do małych liter z prefiksem !
$template = $client->notes()->createTemplate(new NoteTemplateInput(
    noteTypeId: 1,
    name: 'Rozmowa telefoniczna',
    title: 'Rozmowa telefoniczna',
    body: '<p>Ustalenia: ...</p>',    // surowy HTML (WYSIWYG w CRM)
    categoryId: $category->id,
    alias: 'rozmowa_tel',
));
$template->alias;    // znormalizowany, np. !rozmowa_tel
```
