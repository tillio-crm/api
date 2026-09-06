# Generator dokumentów

Oferty/umowy z szablonów, z publikacją online. PDF pobiera się przez
`downloadUrl` (świeży po `get()`, TTL ~1 minuta).

```php
use TillioCrm\Api\Dto\GeneratedDocumentInput;

// Typy dokumentów - typ z requiresTemplate=true wymaga templateId
$types = $client->generatedDocuments()->types();
foreach ($types as $type) {
    $type->name;
    $type->requiresTemplate;
    $type->templates;        // [{id, name, description}]
}

// Formularz typu: jakie pola przyjmuje `data` przy generowaniu.
// contractorId wymagane - API prefiluje formularz danymi kartoteki.
$form = $client->generatedDocuments()->typeForm($types[0]->id, 12345);

// Generowanie pod kartoteką
$result = $client->generatedDocuments()->create(12345, new GeneratedDocumentInput(
    documentTypeId: $types[0]->id,
    templateId: 1,
    data: ['cena' => '15000.00'],
    salesPipelineId: 3,          // powiązanie z szansą
    fillFromPipeline: true,      // uzupełnij dane z szansy
));

// Dokumenty kontrahenta i pojedynczy dokument
$documents = $client->generatedDocuments()->list(12345);
$document = $client->generatedDocuments()->get($result->id ?? 0);
$document->publishUrl;       // publiczny adres opublikowanego dokumentu
$document->error;            // szczegóły nieudanego generowania (null = OK)
if ($document->downloadUrl !== null) {
    $pdf = $client->download($document->downloadUrl);
}

// Ponowne wygenerowanie po poprawce danych
$client->generatedDocuments()->regenerate($document->id, new GeneratedDocumentInput(
    data: ['cena' => '14000.00'],
));
```

## Zakładanie typu dokumentu przez API (wymaga API >= 2.4.0)

Typ powstaje jako szkic i przechodzi pełny cykl: `createType()` ->
`uploadTypeSource()` -> `updateTypeForm()` -> `activateType()`. Szkic
(`draft`) schodzi automatycznie, gdy formularz pokryje wszystkie zmienne
`{{...}}` wykryte w pliku źródłowym.

```php
use TillioCrm\Api\Dto\DocumentTypeInput;
use TillioCrm\Api\Transport\FileUpload;

// Kategorie typów (płaskie drzewo po parentId; kategorie system tylko
// do odczytu) i schematy numeracji do wyboru numerationId
$categories = $client->generatedDocuments()->categories();
$numerations = $client->generatedDocuments()->numerations();
$numerations[0]->schema;    // wzór numeru, np. OF/[NR]/[MM]/[RRRR]

// 1. Szkic typu (name wymagane)
$type = $client->generatedDocuments()->createType(new DocumentTypeInput(
    name: 'Umowa wdrożeniowa',
    categoryId: $categories[0]->id,
    numerationId: $numerations[0]->id,
    store: true,        // wygenerowane dokumenty zapisują się w CRM
    publishDays: 14,    // dni publikacji online; 0 = bez publikacji
));

// 2. Plik źródłowy (multipart, pole file, BEZ retry jak każdy upload);
//    API wykrywa w pliku zmienne {{...}} i zwraca je w wyniku
$source = $client->generatedDocuments()->uploadTypeSource(
    $type->id,
    FileUpload::fromPath('/sciezka/umowa-szablon.docx'),
);
$source['variables'];    // np. ['numer_umowy', 'nazwa_klienta']

// 3. Formularz: grupy pól mapujące zmienne na pola wypełniane przy
//    generowaniu; gdy pokryje wszystkie zmienne, draft schodzi automatycznie
$form = $client->generatedDocuments()->updateTypeForm($type->id, [
    'formFields' => [
        [
            'name' => 'Dane umowy',
            'items' => [
                ['name' => 'Numer umowy', 'type' => 'DOCUMENT_NUMBER', 'variable' => 'numer_umowy'],
                ['name' => 'Klient', 'type' => 'Contractor', 'variable' => 'nazwa_klienta', 'options' => ['field' => 'cc_name']],
            ],
        ],
    ],
]);
$form['draft'];               // false = wszystkie zmienne pokryte, można aktywować
$form['missingVariables'];    // zmienne z pliku wciąż bez pola w formularzu

// 4. Aktywacja - dopiero aktywny typ generuje dokumenty
$client->generatedDocuments()->activateType($type->id);

// Lista typów razem ze szkicami i typami nieaktywnymi (parametr
// includeInactive wymaga API >= 2.4.0; domyślne types() działa też na 2.0.4)
$all = $client->generatedDocuments()->types(includeInactive: true);
foreach ($all as $type) {
    $type->draft;     // szkic w trakcie cyklu
    $type->active;    // tylko aktywne generują dokumenty
}
```
