# Zadania

```php
use TillioCrm\Api\Dto\CategoryInput;
use TillioCrm\Api\Dto\TaskInput;
use TillioCrm\Api\Dto\TaskTemplateInput;
use TillioCrm\Api\Transport\FileUpload;

// Odczyt
$page = $client->tasks()->list(['done' => false, 'projectId' => 2]);
foreach ($client->tasks()->iterate(['contractorId' => 12345]) as $task) {
    $task->title;
    $task->done;
    $task->assignedUserIds;    // wykonawcy (kanon 2.0.0)
    $task->dueDate;
}
$task = $client->tasks()->get(7);

// Tworzenie: title + niepusta lista assignedUserIds wymagane
$result = $client->tasks()->create(new TaskInput(
    title: 'Przygotować ofertę',
    assignedUserIds: [7],
    contractorId: 12345,
    dueDate: '2026-09-01',
));

// Aktualizacja
$client->tasks()->update($result->id ?? 0, new TaskInput(description: 'Zakres rozszerzony.'));

// Komentarze (wątki przez parentCommentId)
foreach ($client->tasks()->comments($result->id ?? 0) as $comment) {
    $comment->body;
}

// Załączniki - multipart, bez retry; commentId wskazuje załącznik z komentarza
$client->tasks()->addAttachment($result->id ?? 0, FileUpload::fromPath('/sciezka/specyfikacja.pdf'));
foreach ($client->tasks()->attachments($result->id ?? 0) as $attachment) {
    if ($attachment->downloadUrl !== null) {
        $bytes = $client->download($attachment->downloadUrl);   // link żyje ~1 min
    }
}

// --- Szablony zadań (wymaga API >= 2.4.0) --------------------------------------
// Kategorie szablonów: płaska lista, drzewo składa się po parentId
$categories = $client->tasks()->templateCategories();
$category = $client->tasks()->createTemplateCategory(new CategoryInput(name: 'Wdrożenia'));

// Nowy szablon (name wymagane); eta w MINUTACH, dueInDays = termin w dniach
// od utworzenia zadania z szablonu, taskPriority: 0 standard, 1 wysoki, 2 najwyższy
$template = $client->tasks()->createTemplate(new TaskTemplateInput(
    categoryId: $category->id,
    name: 'Przygotowanie oferty',
    title: 'Przygotować ofertę',
    assignedUserIds: [7],          // domyślni wykonawcy (Users::list())
    tagIds: [3],                   // dictionaries()->taskTags()
    eta: 90,
    dueInDays: 3,
    taskPriority: 1,
));
```
