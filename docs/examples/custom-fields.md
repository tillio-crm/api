# Pola niestandardowe

Tu zarządza się DEFINICJAMI pól; wartości żyją na rekordach encji
(`customField` w odczycie/zapisie kontrahenta, kontaktu itd.). Założenie pola
wymaga klucza API o uprawnieniach administratora.

```php
use TillioCrm\Api\Dto\CustomFieldInput;

// Lista definicji per encja - zakładanie rób IDEMPOTENTNIE (etykieta jest
// unikalna w encji): najpierw lista, twórz tylko brakujące
$fields = $client->customFields()->list('contractor');
$matching = array_filter($fields, fn ($f) => $f->name === 'ERP ID');

if ($matching === []) {
    $result = $client->customFields()->create(new CustomFieldInput(
        entity: 'contractor',
        name: 'ERP ID',
        type: 'string',                     // duplicateCheck działa TYLKO dla INT/STR/VARCHAR
        editableBy: ['userIds' => [7]],     // ACL; ZAWSZE podawaj - patrz pułapka niżej
    ));

    // Klucza NIE da się narzucić - generuje go CRM z etykiety.
    // Odczytaj z odpowiedzi i zapisz po swojej stronie:
    $key = $result->data['key'];      // np. 'contractor_str_2'
}

// Zmiana przypisania do PODTYPÓW rekordów (id typów notatek, procesów zgłoszeń,
// pozycji katalogu usług, procesów leadowych albo lejków) - tylko dla encji
// note/ticket/service/lead/pipeline. assignedTo to KOMPLETNA lista; usunięcie
// podtypu kasuje wartości pola, więc wymaga allowUnassign: true (wyłącznie bool).
// Pola z błędną definicją NIE da się poprawić: trzeba założyć nowe.
$client->customFields()->update('pipeline', 'pipeline_str_2', [
    'assignedTo' => [3, 4, 5],
]);
```

## Dopisywanie opcji i przypisanie do wszystkich podtypów (API >= 2.17.0)

```php
use TillioCrm\Api\Dto\CustomFieldInput;
use TillioCrm\Api\Dto\CustomFieldOptionInput;
use TillioCrm\Api\Dto\CustomFieldUpdateInput;
use TillioCrm\Api\Dto\CustomFieldUpdateResult;

// Dopisanie opcji do pola SELECT/MULTISELECT dowolnej encji. Nazwy już obecne
// API pomija, istniejące opcje zostają bez zmian (id, kolor, kolejność) - pełną
// listę oczekiwanych opcji można wysyłać przy każdej synchronizacji.
$field = $client->customFields()->appendOptions('lead', 'leads_select_3', [
    'Facebook Lead Ads',
    new CustomFieldOptionInput('Polecenie', '#00aa00'),
]);
$field->options;                          // KOMPLET opcji po zapisie: value, name, color
$value = $field->optionValue('Polecenie'); // id opcji do customField i filtrów

// Pole dla wszystkich lejków istniejących w chwili zapisu. Nowego lejka CRM sam
// nie dopnie - powtórz to samo wywołanie po jego dodaniu (lista tylko rośnie).
$result = $client->customFields()->update('pipeline', 'salespipeline_select_1', new CustomFieldUpdateInput(
    assignedTo: 'all',
    options: ['TAK', 'NIE'],   // oba naraz w jednym żądaniu - opcjonalnie
));
$field = CustomFieldUpdateResult::fromArray($result->data);   // typowany widok odpowiedzi

// "all" także przy zakładaniu pola; opcje z kolorem jak przy dopisywaniu.
$client->customFields()->create(new CustomFieldInput(
    entity: 'pipeline',
    name: 'Partner',
    type: 'SELECT',
    options: ['TAK', new CustomFieldOptionInput('NIE', '#ff0000')],
    assignedTo: 'all',
    editableBy: ['userIds' => [7]],
));
```

Usunięcia ani zmiany nazwy opcji API nie robi (CRM kasuje przy tym wartości
w rekordach) - to operacja w panelu. `options` przy polu innego typu niż
SELECT/MULTISELECT i `assignedTo` przy encji bez podtypów to 422 na tym polu.

**Pułapka `editableBy`:** pole założone BEZ `editableBy` przyjmuje odczyt, ale
każdy zapis wartości kończy się 422 - na każdym rekordzie, także utworzonym
przed chwilą przez tę samą integrację. Objaw myli (wygląda na brak uprawnień
do rekordu). Zawsze podawaj `editableBy` z użytkownikiem, na którym działa
klucz API.
