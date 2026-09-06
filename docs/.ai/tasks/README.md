# Playbook: Zadania (tasks)

Realizacja poleceń użytkownika dotyczących zadań: tworzenie, przydzielanie
wykonawcom, terminy, powiązania, komentarze i załączniki. Pisane dla asystenta
AI - zakłada wspólne wzorce z [ai_integration.md](../ai_integration.md)
(zwłaszcza `resolveUserId()` i "Złotą zasadę: nie zgaduj id").

## Model danych w skrócie

Zadanie tworzy się przez `TaskInput`. Przy tworzeniu API WYMAGA:

- `title` (string) - tytuł zadania,
- `assignedUserIds` (list<int>) - NIEPUSTA lista id wykonawców.

Reszta jest opcjonalna: `description`, `priority`, `ownerUserId`,
`contractorId`, `contactId`, `pipelineItemId`, `leadId`, `startDate`, `dueDate`,
`taskStatusId`, `customField`. Pełna lista pól: `src/Dto/TaskInput.php`.

Kluczowa konsekwencja dla Ciebie: użytkownik NIGDY nie poda `assignedUserIds`
wprost - poda nazwiska. Twoim pierwszym krokiem jest zawsze zamiana nazwisk
na id (patrz niżej).

## Mapowanie intencji użytkownika na dane API

| Użytkownik mówi | Potrzebujesz | Skąd wziąć |
|---|---|---|
| "dla Jana Kowalskiego i Anny Nowak" | `assignedUserIds: [id, id]` | `users()->list(['lastName' => ...])`, dopasuj imię |
| "termin do piątku" / "na 2026-09-10" | `dueDate` (ISO 8601) | policz datę, sformatuj `DATE_ATOM` (uwaga na "najbliższy piątek" - patrz Pułapki) |
| "pilne" / "wysoki priorytet" | `priority` (int) | kontrakt nie definiuje skali; jeśli nie znasz mapowania tej instancji - pomiń albo dopytaj, nie zgaduj liczby |
| "u kontrahenta Acme" | `contractorId` | `contractors()->list(['name' => 'Acme'])` |
| "osoba kontaktowa Jan Kowalski" | `contactId` (API >= 2.8.0) | `contacts()->list([...])`, dopasuj osobę |
| "status W toku" | `taskStatusId` | `dictionaries()->taskStatuses()`, dopasuj nazwę |
| "prowadzący: Piotr" | `ownerUserId` | `resolveUserId()` jak wykonawcy |

## Scenariusz flagowy: zadanie dla wielu osób z zespołu

Polecenie użytkownika: *"Dodaj zadanie 'Przygotować ofertę dla Acme' dla Jana
Kowalskiego, Anny Nowak, Piotra Wisniewskiego i Marii Zajac, termin na
przyszly piatek."*

Twój tok postępowania:

1. Wyłuskaj z polecenia: tytuł, listę osób, termin.
2. Zamień KAŻDĄ osobę na `userId` (odpytując `users()`), zbierając też te,
   których nie dało się jednoznacznie rozwiązać.
3. Jeśli któraś osoba jest niejednoznaczna albo nieznana - PRZERWIJ i dopytaj,
   nie twórz zadania z niekompletnym zespołem.
4. Policz datę terminu i sformatuj do ISO 8601.
5. Utwórz zadanie i zwróć potwierdzenie z id.

```php
use TillioCrm\Api\Dto\TaskInput;

// Krok 1: dane z polecenia (Ty je wyłuskujesz z tekstu użytkownika).
$title = 'Przygotowac oferte dla Acme';
$people = [
    ['firstName' => 'Jan',   'lastName' => 'Kowalski'],
    ['firstName' => 'Anna',  'lastName' => 'Nowak'],
    ['firstName' => 'Piotr', 'lastName' => 'Wisniewski'],
    ['firstName' => 'Maria', 'lastName' => 'Zajac'],
];

// Krok 2 + 3: rozwiąż każdą osobę na id; zbierz problemy zamiast zgadywać.
$assignedUserIds = [];
$problems = [];
foreach ($people as $p) {
    try {
        // resolveUserId() z ai_integration.md: rzuca przy zeru/wielu trafieniach.
        $assignedUserIds[] = resolveUserId($client, $p['firstName'], $p['lastName']);
    } catch (RuntimeException $e) {
        $problems[] = $p['firstName'] . ' ' . $p['lastName'] . ': ' . $e->getMessage();
    }
}

if ($problems !== []) {
    // NIE twórz zadania z niepelnym zespolem - oddaj liste do wyjasnienia userowi.
    throw new RuntimeException("Nie rozwiazano wszystkich osob:\n" . implode("\n", $problems));
}

// Krok 4: termin. "Przyszly piatek" liczymy jako NASTEPNY piatek.
// UWAGA: 'next friday' w PHP pomija dzisiaj, gdy dzis JEST piatek - patrz
// Pulapki. Godzina 17:00 to zalozenie (koniec dnia roboczego); gdy uzytkownik
// nie poda godziny, mozesz ja przyjac albo dopytac.
$dueDate = (new DateTimeImmutable('next friday'))->setTime(17, 0)->format(DATE_ATOM);

// Krok 5: utworzenie zadania. assignedUserIds jest wymagane i niepuste - mamy je.
// create() zwraca WriteResult: ->id (id zadania), ->created (czy utworzono),
// ->warnings (ciche korekty - warto pokazac userowi).
$result = $client->tasks()->create(new TaskInput(
    title: $title,
    assignedUserIds: $assignedUserIds,
    dueDate: $dueDate,
));

// Potwierdzenie dla użytkownika:
echo "Utworzono zadanie #{$result->id} dla " . count($assignedUserIds) . " osob, termin {$dueDate}.\n";
```

Co zwrócić użytkownikowi: numer zadania (`$result->id`), liczbę i (jeśli pomocne)
nazwiska przydzielonych osób oraz termin. Jeśli krok 3 wykrył problem - zamiast
tworzyć, zapytaj o brakujące/niejednoznaczne osoby (najlepiej prosząc o e-mail).
`WriteResult` niesie też `->warnings` (ciche korekty normalizacji) - jeśli
niepuste, pokaż je użytkownikowi.

## Warianty

### Powiązanie z kontrahentem

Gdy zadanie dotyczy konkretnej firmy ("zadanie u kontrahenta Acme"), rozwiąż
najpierw `contractorId` (patrz playbook kontrahentów), potem podaj go w input:

```php
$contractor = $client->contractors()->list(['name' => 'Acme', 'limit' => 1])->first();
if ($contractor === null) {
    throw new RuntimeException('Nie znaleziono kontrahenta Acme - dopytaj albo zaloz kartoteke.');
}

$client->tasks()->create(new TaskInput(
    title: 'Telefon do klienta',
    assignedUserIds: [$userId],
    contractorId: $contractor->id,
));
```

### Status zadania z nazwy

```php
// Status per wykonawca ze słownika; dopasuj nazwę podaną przez użytkownika.
$statusId = findByName($client->dictionaries()->taskStatuses(), 'W toku');
$client->tasks()->create(new TaskInput(
    title: 'Wdrozenie',
    assignedUserIds: [$userId],
    taskStatusId: $statusId,   // null jesli nazwa nie pasuje - wtedy dopytaj
));
```

### Komentarz do zadania (zapis od API >= 2.7.0)

Odczyt wątku działa wszędzie; dopisywanie i edycja komentarzy wymaga API >= 2.7.0.
`addComment()`/`updateComment()` zwracają `TaskComment` (nie `WriteResult`).
`body` to HTML, `parentCommentId` robi z komentarza odpowiedź w wątku.

```php
use TillioCrm\Api\Dto\TaskCommentInput;
use TillioCrm\Api\Transport\FileUpload;

// Odczyt watku komentarzy istniejacego zadania:
$comments = $client->tasks()->comments($taskId);   // list<TaskComment>

// Dopisanie komentarza (API >= 2.7.0). body to HTML; zwraca TaskComment (nie
// WriteResult). parentCommentId = odpowiedz w watku pod istniejacym komentarzem.
$comment = $client->tasks()->addComment($taskId, new TaskCommentInput(
    body: '<p>Klient prosi o przesuniecie terminu na piatek.</p>',
));

// Odpowiedz pod tym komentarzem (watek).
$reply = $client->tasks()->addComment($taskId, new TaskCommentInput(
    body: '<p>Przesunieto, poinformowalem zespol.</p>',
    parentCommentId: $comment->id,
));

// Edycja tresci komentarza (API >= 2.7.0).
$client->tasks()->updateComment($taskId, $comment->id, new TaskCommentInput(
    body: '<p>Klient prosi o przesuniecie terminu na przyszly piatek.</p>',
));

// Zalacznik POD komentarzem: podaj commentId trzecim argumentem (API >= 2.7.0).
// Bez commentId zalacznik laduje na zadaniu. Upload multipart, BEZ retry.
$client->tasks()->addAttachment($taskId, FileUpload::fromPath('/sciezka/do/plik.pdf'), $comment->id);
```

### Załącznik do zadania

```php
use TillioCrm\Api\Transport\FileUpload;

// Upload jest multipart i BEZ retry (powtorka po timeoutcie zostawilaby drugi plik).
// Bez commentId zalacznik siedzi na zadaniu; z commentId (API >= 2.7.0) - pod komentarzem.
$client->tasks()->addAttachment($taskId, FileUpload::fromPath('/sciezka/do/plik.pdf'));
```

### Szablon zadania (API >= 2.4.0)

Jeśli w instancji są szablony zadań, możesz je wypisać i użyć jako punktu
wyjścia (nazwa, domyślni wykonawcy, szacowany czas, termin w dniach):

```php
$templates = $client->tasks()->templateCategories();   // kategorie szablonow
// tworzenie z szablonu: patrz Tasks::createTemplate i docs/examples/tasks.md
```

## Pułapki

- **`assignedUserIds` jest wymagane i niepuste.** Zadanie bez wykonawcy = 422.
  Zawsze rozwiąż przynajmniej jedną osobę przed `create()`.
- **Nie zgaduj `userId`.** Kilku pracowników może mieć to samo nazwisko -
  `resolveUserId()` celowo rzuca przy wielu trafieniach. Dopytaj o e-mail.
- **Daty w ISO 8601 z offsetem strefy** (`DATE_ATOM`, np.
  `2026-09-10T17:00:00+02:00`). Inny format to 400.
- **`taskStatusId` z nazwy bywa null**, gdy użytkownik poda nazwę spoza słownika
  danej instancji - wtedy dopytaj, nie wstawiaj przypadkowego id.
- **Odczyt vs zapis**: `Task` (odczyt) ma pola, których `TaskInput` nie przyjmuje
  (np. `estimatedTime`, `projectId`, `endDate`) - są ustawiane po stronie CRM.
- **"Najbliższy piątek" a `next friday`.** PHP `new DateTimeImmutable('next friday')`
  daje NASTĘPNY piątek i POMIJA dzisiaj, gdy dziś jest piątek. Jeśli użytkownik
  mówi "najbliższy piątek" (zwykle: najwcześniejszy nadchodzący, także dziś),
  obsłuż dziś-piątek osobno:
  ```php
  $now = new DateTimeImmutable('now');
  $friday = (int) $now->format('N') === 5 ? $now : new DateTimeImmutable('next friday');
  $dueDate = $friday->setTime(17, 0)->format(DATE_ATOM);
  ```
- **Godzina terminu bywa niepodana.** Gdy użytkownik poda tylko dzień, przyjmij
  rozsądną godzinę (np. koniec dnia roboczego) ALBO dopytaj - nie wstawiaj cicho
  losowej wartości bez świadomości, że to założenie.
- **`create()` zwraca `WriteResult`** (`->id`, `->created`, `->warnings`,
  `isDuplicate()`), nie samo id. Szczegóły: sekcja "Co zwracają zapisy"
  w [ai_integration.md](../ai_integration.md).
- **Zapis komentarzy wymaga API >= 2.7.0, `contactId` API >= 2.8.0.**
  `addComment()`/`updateComment()` (oraz `commentId` w `addAttachment()`) i pole
  `contactId` na starszej instancji zwrócą `FeatureNotSupportedException` (501) -
  nie ponawiaj, zaktualizuj CRM. Odczyt komentarzy (`comments()`) działa niezależnie.
- **`addComment()` zwraca `TaskComment`, nie `WriteResult`.** Nie szukaj tu
  `->created` ani `isDuplicate()` - masz pełne DTO komentarza (`->id` to id
  komentarza, użyjesz go jako `parentCommentId` albo `commentId` załącznika).
