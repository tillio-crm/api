# Playbook: Notatki (notes)

Realizacja poleceń użytkownika dotyczących notatek u kontrahentów: zapisanie
notatki (rozmowa, ustalenie, przypomnienie), dołączenie pliku, odczyt
załączników i szablony notatek. Pisane dla asystenta AI - zakłada wspólne wzorce
z [ai_integration.md](../ai_integration.md) (zwłaszcza helper `findByName()`
i "Złotą zasadę: nie zgaduj id").

## Model danych w skrócie

Notatkę tworzy się przez `notes()->create(int $contractorId, NoteInput $input)`.
Uwaga strukturalna: `contractorId` jest w ŚCIEŻCE (argument metody), a NIE w
`NoteInput`. Przy tworzeniu API WYMAGA:

- `noteTypeId` (int) - typ notatki ze słownika `dictionaries()->noteTypes()`,
- `title` (string) - tytuł notatki.

Reszta jest opcjonalna: `body`, `pinned`, `noteDate`, `contactIds`, `customField`,
`createdAt`, `creatorUserId`. Pełna lista pól: `src/Dto/NoteInput.php`.

Pole `contactIds` (`list<int>`, API >= 2.8.0) przypina osoby kontaktowe od razu
przy tworzeniu notatki. Przy notatce kontrahenta wolno wskazać wyłącznie kontakty
tego kontrahenta - obce id zwróci błąd. Kontakty można też dopiąć osobno po
utworzeniu notatki (patrz "Kontakty przy notatce" w Wariantach).

Kluczowa konsekwencja dla Ciebie: użytkownik NIGDY nie poda `contractorId` ani
`noteTypeId` wprost - poda nazwę firmy ("Acme") i nazwę typu ("Rozmowa
telefoniczna"). Twoje pierwsze dwa kroki to zawsze zamiana obu nazw na id
(patrz niżej).

Odczyt notatki zwraca `Note` (`src/Dto/Note.php`) - ma pola, których `NoteInput`
nie przyjmuje (np. `leadId`, `serviceId`, `pipelineId`), bo notatka może być
przypięta nie tylko do kontrahenta.

## Mapowanie intencji użytkownika na dane API

| Użytkownik mówi | Potrzebujesz | Skąd wziąć |
|---|---|---|
| "u kontrahenta Acme" | `contractorId` (argument ścieżki) | `contractors()->list(['name' => 'Acme'])->first()` |
| "typ Rozmowa telefoniczna" | `noteTypeId` | `findByName($client->dictionaries()->noteTypes(), 'Rozmowa telefoniczna')` |
| "o tytule ..." / pierwsze zdanie | `title` | wprost z polecenia (wymagane) |
| "treść: ..." / szczegóły rozmowy | `body` | wprost z polecenia (opcjonalne) |
| "przypnij" / "na górze" | `pinned: true` | wprost z polecenia |
| "z datą wczorajszą" / "z dnia ..." | `noteDate` (ISO 8601) | policz datę, sformatuj `DATE_ATOM` |
| "z załącznikiem plik.pdf" | osobne `addAttachment(noteId, ...)` | najpierw `create()`, potem upload (patrz Warianty) |
| "przypnij kontakt Jan Kowalski" | `contactIds: [id]` albo `addContact(noteId, id)` | `contacts()->list([...])`, dopasuj osobę (API >= 2.8.0) |

Rozwiązywanie `contractorId` z nazwy albo NIP-u ma własne pułapki (duplikaty,
brak kartoteki) - szczegóły w playbooku kontrahentów. Tu używamy go w
najprostszej postaci.

## Scenariusz flagowy: notatka o rozmowie telefonicznej u kontrahenta

Polecenie użytkownika: *"Zapisz notatke u kontrahenta Acme o rozmowie
telefonicznej - klient prosi o oferte na wdrozenie do konca tygodnia."*

Twój tok postępowania:

1. Rozwiąż `contractorId` z nazwy "Acme"; brak kartoteki -> PRZERWIJ i dopytaj.
2. Rozwiąż `noteTypeId` z nazwy typu "Rozmowa telefoniczna" przez słownik;
   `null` (nazwa spoza słownika instancji) -> PRZERWIJ i dopytaj o typ.
3. Zbuduj `NoteInput` z `noteTypeId` i `title` (oba wymagane), resztę rozmowy
   wrzuć do `body`.
4. Wywołaj `create($contractorId, $input)` i zwróć potwierdzenie z `id`.

```php
use TillioCrm\Api\Dto\NoteInput;

// Krok 1: nazwa firmy -> contractorId. Pobieramy pierwsze trafienie; brak = stop.
$contractor = $client->contractors()->list(['name' => 'Acme', 'limit' => 1])->first();
if ($contractor === null) {
    // Bez kontrahenta nie ma gdzie zapisac notatki - oddaj sprawe userowi,
    // nie zakladaj kartoteki po cichu.
    throw new RuntimeException('Nie znaleziono kontrahenta Acme - dopytaj albo zaloz kartoteke.');
}

// Krok 2: nazwa typu -> noteTypeId ze slownika. findByName z ai_integration.md
// zwraca null, gdy nazwa nie pasuje do zadnej pozycji slownika tej instancji.
$noteTypeId = findByName($client->dictionaries()->noteTypes(), 'Rozmowa telefoniczna');
if ($noteTypeId === null) {
    // NIE wstawiaj przypadkowego typu - popros usera o wskazanie typu z listy.
    throw new RuntimeException('Nie znaleziono typu notatki "Rozmowa telefoniczna" - dopytaj, ktory typ uzyc.');
}

// Krok 3: input. noteTypeId i title sa wymagane; contractorId TU NIE WCHODZI
// (idzie argumentem sciezki w kroku 4).
$input = new NoteInput(
    noteTypeId: $noteTypeId,
    title: 'Rozmowa telefoniczna - prosba o oferte',
    body: 'Klient prosi o oferte na wdrozenie do konca tygodnia.',
);

// Krok 4: zapis. Pierwszy argument to contractorId (sciezka), drugi to input.
$result = $client->notes()->create($contractor->id, $input);

// Potwierdzenie dla uzytkownika: WriteResult niesie id nowej notatki.
echo "Zapisano notatke #{$result->id} u kontrahenta {$contractor->id} (Acme).\n";
```

Co zwrócić użytkownikowi: numer notatki i firmę, u której powstała. Jeśli krok 1
albo 2 wykrył problem - zamiast tworzyć, zapytaj o brakującego kontrahenta albo
o właściwy typ notatki.

## Warianty

### Notatka z załącznikiem

Załącznik to OSOBNE wywołanie po utworzeniu notatki - potrzebujesz najpierw
`noteId` z `create()`, potem uploadujesz plik.

```php
use TillioCrm\Api\Dto\NoteInput;
use TillioCrm\Api\Transport\FileUpload;

// Najpierw notatka (jak w scenariuszu flagowym) - potrzebujemy jej id.
$result = $client->notes()->create($contractor->id, new NoteInput(
    noteTypeId: $noteTypeId,
    title: 'Podpisana umowa',
));

// Potem plik. Upload jest multipart i BEZ retry: powtorka po timeoutcie, ktory
// mimo wszystko doszedl, zostawilaby w CRM drugi plik. Przy bledzie sieci
// sprawdz liste zalacznikow, zanim ponowisz.
$client->notes()->addAttachment($result->id, FileUpload::fromPath('/sciezka/do/plik.pdf'));

echo "Notatka #{$result->id} zapisana z zalacznikiem.\n";
```

### Odczyt załączników z pobraniem pliku

`downloadUrl` to podpisany link magazynu ważny ~1 minutę - pobieraj OD RAZU,
nie buforuj. `null` oznacza środowisko bez podpisywania (pliku nie da się pobrać
wcale), a nie "brak pliku".

```php
// Lista zalacznikow notatki #7 -> list<Attachment>.
$attachments = $client->notes()->attachments(7);

foreach ($attachments as $attachment) {
    if ($attachment->downloadUrl === null) {
        // Srodowisko bez podpisywania linkow - pliku nie pobierzesz, pomin.
        continue;
    }

    // Pobranie tresci pliku zwyklym GET-em (bez naglowkow Tillio). Rob to zaraz
    // po odczycie listy - link zyje okolo minuty, potem wygasa.
    $bytes = $client->download($attachment->downloadUrl);
    file_put_contents('/sciezka/do/' . ($attachment->fileName ?? 'plik.bin'), $bytes);
}
```

### Szablon notatki (API >= 2.4.0)

Szablony to gotowe treści (tytuł + HTML), z których użytkownik CRM tworzy notatkę
jednym kliknięciem. Tworzenie szablonu wymaga `noteTypeId`, `name` i `title`;
opcjonalnie przypniesz go do kategorii z `templateCategories()`.

```php
use TillioCrm\Api\Dto\NoteTemplateInput;

// Kategorie szablonow (plaska lista, drzewo po parentId) - opcjonalne.
$categories = $client->notes()->templateCategories();

// Typ notatki rozwiazujemy tak samo jak przy zwyklej notatce.
$noteTypeId = findByName($client->dictionaries()->noteTypes(), 'Rozmowa telefoniczna');
if ($noteTypeId === null) {
    throw new RuntimeException('Nie znaleziono typu notatki - dopytaj, ktory uzyc.');
}

// noteTypeId, name i title sa wymagane; body to surowy HTML (WYSIWYG w CRM).
$template = $client->notes()->createTemplate(new NoteTemplateInput(
    noteTypeId: $noteTypeId,
    name: 'Szablon rozmowy telefonicznej',
    title: 'Rozmowa telefoniczna',
    body: '<p>Ustalenia:</p>',
));

echo "Utworzono szablon notatki #{$template->id}.\n";
```

### Kontakty przy notatce (API >= 2.8.0)

Do notatki można przypiąć osoby kontaktowe - albo od razu przez `contactIds`
w `NoteInput`, albo osobnymi metodami po utworzeniu. `addContact()` i `contacts()`
zwracają PEŁNĄ listę `NoteContact` (imię, nazwisko, `email`, `phone`, `position`).
Przy notatce kontrahenta wolno wskazać tylko kontakty tego kontrahenta.

```php
use TillioCrm\Api\Dto\NoteInput;

// Wariant A: przypnij kontakty od razu przy tworzeniu notatki (contactIds).
// Przy notatce kontrahenta wolno wskazac TYLKO kontakty tego kontrahenta.
$jan = $client->contacts()->list(['contractorId' => $contractor->id, 'lastName' => 'Kowalski', 'limit' => 1])->first();
if ($jan === null) {
    throw new RuntimeException('Nie znaleziono kontaktu Kowalski u tego kontrahenta - dopytaj.');
}

$result = $client->notes()->create($contractor->id, new NoteInput(
    noteTypeId: $noteTypeId,
    title: 'Rozmowa telefoniczna',
    contactIds: [$jan->id],   // od razu przypiete; obce id (spoza kontrahenta) = blad
));

// Wariant B: dopnij kontakt do ISTNIEJACEJ notatki. addContact zwraca PELNA
// liste kontaktow po zmianie (NoteContact). Ponowne przypiecie jest bezpieczne.
$contactsAfter = $client->notes()->addContact($result->id, $jan->id);   // list<NoteContact>
echo "Notatka #{$result->id} ma teraz " . count($contactsAfter) . " kontaktow.\n";

// Odczyt przypietych kontaktow i odpiecie jednego.
$current = $client->notes()->contacts($result->id);   // list<NoteContact>
$client->notes()->removeContact($result->id, $jan->id);   // void
```

## Pułapki

- **`contractorId` jest w ŚCIEŻCE, nie w input.** To pierwszy argument
  `create($contractorId, $input)`; w `NoteInput` takiego pola nie ma. Notatki nie
  zapisuje się "w powietrzu" - zawsze u konkretnego kontrahenta.
- **`noteTypeId` jest wymagane, a z nazwy bywa null.** Gdy użytkownik poda typ
  spoza słownika danej instancji, `findByName()` zwróci `null` - wtedy dopytaj,
  nie wstawiaj przypadkowego typu. Notatka bez `noteTypeId` albo `title` to 422.
- **Upload załącznika jest BEZ retry.** `addAttachment()` idzie multipartem i SDK
  go nie ponawia - powtórka po timeoutcie, który mimo wszystko doszedł,
  zostawiłaby drugi plik. Po błędzie sieci sprawdź listę załączników przed
  ponowieniem.
- **`downloadUrl` żyje ~1 minutę.** Nie buforuj linku - pobieraj plik zaraz po
  odczycie `attachments()` przez `$client->download($url)`. Po wygaśnięciu
  odczytaj listę ponownie po świeży link. `downloadUrl === null` to środowisko
  bez podpisywania, nie "brak pliku".
- **Szablony wymagają API >= 2.4.0.** `createTemplate()` i `templateCategories()`
  na starszej instancji odpowiedzą błędem - sprawdź `$client->health()['version']`,
  gdy nie masz pewności.
- **Kontakty przy notatce wymagają API >= 2.8.0.** `contacts()`, `addContact()`,
  `removeContact()` i pole `contactIds` na starszej instancji zwrócą
  `FeatureNotSupportedException` (501) - nie ponawiaj, zaktualizuj CRM. Przy
  notatce kontrahenta wolno wskazać tylko kontakty tego kontrahenta - obce id to
  błąd, nie ciche pominięcie.
- **Odczyt vs zapis**: `Note` (odczyt) ma pola `leadId`, `serviceId`,
  `pipelineId`, których `NoteInput` nie przyjmuje - notatka bywa przypięta do
  leada, usługi albo szansy, ale przez to API tworzysz ją wyłącznie u kontrahenta.
