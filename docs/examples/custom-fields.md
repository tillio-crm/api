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

// Zmiana przypisania do użytkowników - jedyna edycja definicji.
// Pola z błędną definicją NIE da się poprawić: trzeba założyć nowe.
$client->customFields()->update('contractor', 'contractor_str_2', [
    'assignedTo' => [7, 12],
]);
```

**Pułapka `editableBy`:** pole założone BEZ `editableBy` przyjmuje odczyt, ale
każdy zapis wartości kończy się 422 - na każdym rekordzie, także utworzonym
przed chwilą przez tę samą integrację. Objaw myli (wygląda na brak uprawnień
do rekordu). Zawsze podawaj `editableBy` z użytkownikiem, na którym działa
klucz API.
