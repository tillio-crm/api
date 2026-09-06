# Projekty

```php
use TillioCrm\Api\Dto\ProjectInput;

// Odczyt - z licznikami zadań
$page = $client->projects()->list(['archived' => false]);
foreach ($client->projects()->iterate() as $project) {
    $project->name;
    $project->tasksDoneCount;
    $project->tasksOverdueCount;
}
$project = $client->projects()->get(2);

// Tworzenie (name wymagane)
$result = $client->projects()->create(new ProjectInput(
    name: 'Wdrożenie systemu',
    contractorId: 12345,
    ownerUserId: 7,
    startDate: '2026-09-01',
    dueDate: '2026-12-31',
));

// Aktualizacja
$client->projects()->update($result->id ?? 0, new ProjectInput(projectStatusId: 2));
```
