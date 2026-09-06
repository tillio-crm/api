# Playbook: Dokumenty z generatora (generated-documents)

Realizacja polecen uzytkownika dotyczacych dokumentow z generatora: oferty
i umowy z szablonow, wygenerowanie dokumentu dla kontrahenta, ponowne
wygenerowanie po poprawce, pobranie pliku. Pisane dla asystenta AI - zaklada
wspolne wzorce z [ai_integration.md](../ai_integration.md) (zwlaszcza
`findByName()`, `WriteResult` i "Zlota zasada: nie zgaduj id").

Kluczowa specyfika: generowanie jest podpiete pod kartoteke kontrahenta
(`create(contractorId, GeneratedDocumentInput)`), formularz typu dokumentu
POBIERA sie osobno przez `typeForm()`, ktore WYMAGA `contractorId` (API wstepnie
wypelnia pola danymi kartoteki), a gotowy plik pobiera sie przez `downloadUrl`
z odczytu dokumentu (link podpisany, TTL ~1 minuta).

## Pola

Zapis idzie przez `GeneratedDocumentInput` (`src/Dto/GeneratedDocumentInput.php`).
Named arguments, `null` = nie wysylaj pola. Wszystkie pola:

### GeneratedDocumentInput

| pole | typ | po co (skad wziac) |
|---|---|---|
| `documentTypeId` | `int` | Typ dokumentu (oferta, umowa, ...). WYMAGANY. Z `types()`, dopasuj nazwe przez `findByName()`. |
| `templateId` | `int` | Szablon HTML. WYMAGANY, gdy typ ma `requiresTemplate = true`. Z `DocumentType::$templates` (lista `{id, name, description}`). |
| `data` | `array<string, mixed>` | Wartosci pol formularza typu. Ksztalt zalezy od typu - pobierz go przez `typeForm($typeId, $contractorId, $templateId)` i wypelnij. |
| `salesPipelineId` | `int` | Powiazana szansa sprzedazy. Z `pipelineItems()->list([...])`. |
| `fillFromPipeline` | `bool` | Uzupelnij dane z powiazanej szansy (wymaga `salesPipelineId`). |
| `updatePipeline` | `bool` | Zapisz wartosc dokumentu w powiazanej szansie. |

Pola odczytu warte uwagi:

`GeneratedDocument` (`src/Dto/GeneratedDocument.php`): `id`, `documentTypeId`,
`typeName`, `templateId`, `documentStatusId`/`statusName`, `number`, `value`
(wartosc, string), `currency`, **`downloadUrl`** (podpisany link do pliku,
TTL ~1 min, SWIEZY po `get()`), `publishUrl`/`publishValidTo` (publiczny adres
opublikowanego dokumentu), `error` (szczegoly nieudanego generowania; null = OK).

`DocumentType` (`src/Dto/DocumentType.php`): `id`, `name`, `description`,
`categoryId`/`categoryName`, `requiresTemplate` (czy trzeba `templateId`),
`templates` (`list<{id, name, description}>`). Pola cyklu zycia typu (`active`,
`draft`, `numerationId`, `variables`, `formFields`, ...) sa dostepne od API 2.4.0
- na starszych instancjach zostaja nullami/pustkami.

## Model danych

Generowanie ma trzy elementy: TYP dokumentu, FORMULARZ typu i sam ZAPIS pod
kartoteke.

- `types(includeInactive)` (`GET /v2/document/types`) - typy dokumentow
  z szablonami. `includeInactive = true` (API >= 2.4.0) pokazuje tez szkice
  i nieaktywne; domyslne wywolanie dziala tez na API 2.0.4.
- `typeForm(typeId, contractorId, templateId)` (`GET /v2/document/types/{id}/form`)
  - definicja formularza + pola wstepnie wypelnione danymi kartoteki.
  `contractorId` jest WYMAGANE (API prefiluje formularz). Zwraca surowa mape
  (ksztalt zalezy od typu).
- `list(contractorId, filters)` (`GET /v2/contractors/{id}/documents`) -
  dokumenty kontrahenta (lista bez stronicowania).
- `get(id)` (`GET /v2/documents/{id}`) - pojedynczy dokument ze SWIEZYM
  `downloadUrl`.
- `create(contractorId, GeneratedDocumentInput)`
  (`POST /v2/contractors/{id}/documents`) - wygenerowanie. Wymagane
  `documentTypeId`; `data` wg `typeForm()`. Zwraca `WriteResult`.
- `regenerate(id, GeneratedDocumentInput)` (`POST /v2/documents/{id}/regenerate`)
  - ponowne wygenerowanie; `data` nadpisuje pola formularza. Zwraca `WriteResult`.

Konsekwencje dla Ciebie:

- Uzytkownik poda nazwe typu ("oferta") i nazwe firmy - rozwiaz typ na
  `documentTypeId` (`types()` + `findByName()`) i kontrahenta na `contractorId`.
- Typ z `requiresTemplate = true` WYMAGA `templateId` - wybierz z `templates`.
- Formularz (`data`) pobierz przez `typeForm()` z `contractorId`, nie zgaduj
  ksztaltu pol.
- Pliku NIE dostaniesz z `create()` - `WriteResult` niesie tylko `id`. Plik
  pobierz przez `downloadUrl` ze SWIEZEGO `get()` (TTL ~1 min - uzyj od razu).

## Mapowanie intencji

| Uzytkownik mowi | Potrzebujesz | Skad wziac |
|---|---|---|
| "wygeneruj oferte" | `documentTypeId` | `types()` + `findByName(..., 'Oferta')` |
| "dla kontrahenta Acme" | `contractorId` (argument `create`/`typeForm`) | `contractors()->list(['name' => 'Acme'])` |
| typ z szablonem | `templateId` | `DocumentType::$templates` - wybierz `{id, name}` |
| "wypelnij dane z szansy" | `salesPipelineId` + `fillFromPipeline: true` | `pipelineItems()->list([...])` |
| "zapisz wartosc w szansie" | `updatePipeline: true` | z tekstu |
| "pobierz PDF" | `downloadUrl` | `get($id)->downloadUrl` (swiezy, TTL ~1 min) |
| "wygeneruj jeszcze raz" | `regenerate($id, ...)` | id istniejacego dokumentu |

## Scenariusz flagowy: oferta z szablonu dla kontrahenta

Polecenie uzytkownika: *"Wygeneruj oferte dla Acme i daj mi link do PDF."*

Tok postepowania:

1. Rozwiaz kontrahenta Acme na `contractorId`.
2. Rozwiaz typ "Oferta" na `documentTypeId` (`types()` + `findByName()`).
3. Jesli typ wymaga szablonu - wybierz `templateId` z jego `templates`.
4. Pobierz formularz przez `typeForm($typeId, $contractorId, $templateId)`
   (WYMAGA contractorId - API prefiluje pola) i przygotuj `data`.
5. Utworz dokument, potem pobierz SWIEZY `downloadUrl` przez `get()`.

```php
use TillioCrm\Api\Dto\GeneratedDocumentInput;

// Krok 1: kontrahent po nazwie -> contractorId. Nie zgaduj id kartoteki.
$contractor = $client->contractors()->list(['name' => 'Acme', 'limit' => 1])->first();
if ($contractor === null) {
    throw new RuntimeException('Nie znaleziono kontrahenta Acme - dopytaj albo zaloz kartoteke.');
}

// Krok 2: typ dokumentu po nazwie -> DocumentType. types() zwraca liste DTO,
// wiec dopasowujemy po polu name recznie (findByName dziala na {id, name}).
$type = null;
foreach ($client->generatedDocuments()->types() as $candidate) {
    if (mb_strtolower((string) $candidate->name) === mb_strtolower('Oferta')) {
        $type = $candidate;
        break;
    }
}
if ($type === null) {
    // Nie zgaduj documentTypeId - dopytaj o poprawna nazwe typu.
    throw new RuntimeException('Typ dokumentu "Oferta" nie istnieje w tej instancji - dopytaj o nazwe typu.');
}

// Krok 3: szablon, gdy typ go wymaga. Bierzemy pierwszy dostepny; przy wielu
// szablonach warto dopytac uzytkownika, ktory (kazdy ma {id, name, description}).
$templateId = null;
if ($type->requiresTemplate === true) {
    if ($type->templates === []) {
        throw new RuntimeException('Typ "Oferta" wymaga szablonu, ale nie ma zadnego - dopytaj/skonfiguruj.');
    }
    $templateId = (int) $type->templates[0]['id'];
}

// Krok 4: formularz typu. typeForm WYMAGA contractorId - API prefiluje pola
// danymi kartoteki. Zwraca surowa mape (ksztalt zalezy od typu); tu bierzemy
// prefill jako punkt wyjscia i ewentualnie nadpisujemy wybrane pola.
$form = $client->generatedDocuments()->typeForm($type->id, $contractor->id, $templateId);
$data = $form['data'] ?? [];   // prefill z kartoteki; uzupelnij czego brakuje

// Krok 5: wygenerowanie. Wymagane documentTypeId; templateId gdy typ go wymaga.
// create() zwraca WriteResult (->id), NIE gotowy plik.
$result = $client->generatedDocuments()->create($contractor->id, new GeneratedDocumentInput(
    documentTypeId: $type->id,
    templateId: $templateId,
    data: $data,
));

// Plik pobiera sie przez downloadUrl - SWIEZY po get(), TTL ~1 min. Odczytaj
// tuz przed uzyciem linku i sprawdz, czy generowanie sie powiodlo (error).
$document = $client->generatedDocuments()->get((int) $result->id);
if ($document->error !== null) {
    throw new RuntimeException('Generowanie dokumentu nie powiodlo sie: ' . json_encode($document->error));
}

echo "Wygenerowano dokument #{$document->id} ({$document->typeName}). ";
echo "Link do pobrania (wazny ~1 min): {$document->downloadUrl}\n";
```

Co zwrocic uzytkownikowi: numer/id dokumentu (`$document->id`), typ i SWIEZY
`downloadUrl` (z zastrzezeniem, ze wygasa po okolo minucie - jesli minelo,
odczytaj `get()` ponownie). `WriteResult` z `create()` niesie tez `->warnings`
- jesli niepuste, pokaz je.

## Warianty

### Ponowne wygenerowanie po poprawce danych

`regenerate()` nadpisuje pola formularza i tworzy dokument od nowa:

```php
use TillioCrm\Api\Dto\GeneratedDocumentInput;

$client->generatedDocuments()->regenerate($documentId, new GeneratedDocumentInput(
    data: ['discount' => '10', 'validUntil' => '2026-10-01'],
));
// Po regenerate pobierz SWIEZY downloadUrl:
$fresh = $client->generatedDocuments()->get($documentId);
```

### Wypelnienie danymi z szansy sprzedazy

```php
use TillioCrm\Api\Dto\GeneratedDocumentInput;

$client->generatedDocuments()->create($contractorId, new GeneratedDocumentInput(
    documentTypeId: $typeId,
    templateId: $templateId,
    salesPipelineId: $pipelineId,
    fillFromPipeline: true,   // dane z szansy
    updatePipeline: true,     // i zapisz wartosc dokumentu z powrotem w szansie
));
```

### Lista dokumentow kontrahenta

```php
// Wszystkie dokumenty kartoteki (lista bez stronicowania). Filtr salesPipelineId
// zawezi do dokumentow jednej szansy.
$docs = $client->generatedDocuments()->list($contractorId);            // list<GeneratedDocument>
$forPipeline = $client->generatedDocuments()->list($contractorId, ['salesPipelineId' => $pipelineId]);
```

### Pelny cykl typow dokumentow (API >= 2.4.0)

Zakladanie wlasnego typu dokumentu (szkic -> plik zrodlowy -> formularz ->
aktywacja) i kategorie/numeracje sa dostepne od API 2.4.0:

```php
use TillioCrm\Api\Transport\FileUpload;

// 1) szkic typu -> 2) plik zrodlowy (wykrycie zmiennych {{...}}, upload BEZ retry)
// -> 3) formularz mapujacy zmienne -> 4) aktywacja.
$type = $client->generatedDocuments()->createType(['name' => 'Umowa NDA']);
$src = $client->generatedDocuments()->uploadTypeSource($type->id, FileUpload::fromPath('/sciezka/szablon.docx'));
$client->generatedDocuments()->updateTypeForm($type->id, ['formFields' => [/* grupy pol mapujace $src['variables'] */]]);
$client->generatedDocuments()->activateType($type->id);
```

Szkic (`draft`) schodzi automatycznie, gdy formularz pokryje wszystkie zmienne
wykryte w pliku zrodlowym. Kategorie: `categories()`/`createCategory()`;
numeracje: `numerations()`. Szczegoly w `src/Resources/GeneratedDocuments.php`.

## Pulapki

- **`documentTypeId` jest wymagane.** Rozwiaz typ z nazwy przez `types()` +
  dopasowanie po `name`; nie zgaduj id.
- **Typ z `requiresTemplate = true` wymaga `templateId`.** Wez go z
  `DocumentType::$templates` (`{id, name, description}`). Brak szablonu przy
  takim typie = 422.
- **`typeForm()` WYMAGA `contractorId`** - to nie opcja. API prefiluje formularz
  danymi kartoteki; bez tego nie dostaniesz sensownego ksztaltu pol.
- **`create()`/`regenerate()` zwracaja `WriteResult`, nie plik.** Plik pobiera
  sie przez `downloadUrl` z odczytu dokumentu.
- **`downloadUrl` ma TTL ~1 minuta i jest SWIEZY dopiero po `get()`.** Odczytaj
  `get($id)` tuz przed uzyciem linku; jesli wygasl - odczytaj ponownie. Nie
  cache'uj tego linku.
- **Sprawdz `error` po generowaniu.** `GeneratedDocument::$error` niepuste
  oznacza nieudane generowanie mimo utworzenia rekordu - pokaz powod, nie
  podawaj linku do niedokonczonego pliku.
- **Kwoty (`value`) i wersje**: `value` to string; pelny cykl typow, kategorie
  i numeracje wymagaja API >= 2.4.0 - na starszej instancji te trasy nie
  zadzialaja, a pola cyklu zycia `DocumentType` beda puste.
- **`create()` zwraca `WriteResult`** (`->id`, `->created`, `->warnings`).
  Szczegoly: sekcja "Co zwracaja zapisy" w [ai_integration.md](../ai_integration.md).
