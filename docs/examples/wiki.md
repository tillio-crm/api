# Wiki - baza wiedzy

Struktura: bazy → kategorie → wpisy (artykuły i procedury). Jedyna encja
z DELETE w API v2.

```php
use TillioCrm\Api\Dto\WikiBaseInput;
use TillioCrm\Api\Dto\WikiEntryInput;

// Bazy
$bases = $client->wiki()->bases();
$result = $client->wiki()->createBase(new WikiBaseInput(
    name: 'Procedury wsparcia',
    type: 'procedure',           // 'article' | 'procedure'
    color: '#4b78c5',
));
$client->wiki()->updateBase($result->id ?? 0, new WikiBaseInput(subtitle: 'Dla zespołu BOK'));

// Kategorie bazy
$categories = $client->wiki()->categories($result->id ?? 0);
$category = $client->wiki()->createCategory($result->id ?? 0, ['name' => 'Onboarding']);
$client->wiki()->updateCategory($category->id ?? 0, ['name' => 'Wdrożenie klienta']);

// Wpisy (lista stronicowana)
$page = $client->wiki()->entries(['baseId' => $result->id ?? 0, 'published' => true]);
$entry = $client->wiki()->getEntry($page->first()?->id ?? 0);
$entry->content;
$entry->views;

// Pełny przebieg wszystkich stron wpisów (generator)
foreach ($client->wiki()->iterateEntries(['published' => true]) as $item) {
    $item->title;
}

$created = $client->wiki()->createEntry(new WikiEntryInput(
    categoryId: $category->id ?? 0,
    title: 'Pierwsze kroki',
    content: '<p>Treść procedury...</p>',
    published: true,
));
$client->wiki()->updateEntry($created->id ?? 0, new WikiEntryInput(archived: true));

// Usunięcie - nieodwracalne
$client->wiki()->deleteEntry($created->id ?? 0);
```
