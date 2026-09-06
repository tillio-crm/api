# Playbook: Kontrahenci (contractors)

Realizacja poleceń użytkownika dotyczących kartotek firm i osób: zakładanie,
wyszukiwanie po NIP i nazwie, adresy, pola niestandardowe oraz masowy upsert.
Pisane dla asystenta AI - zakłada wspólne wzorce z
[ai_integration.md](../ai_integration.md) (zwłaszcza `findByName()` i "Złotą
zasadę: nie zgaduj id, odpytaj końcówkę").

## Model danych w skrócie

Kontrahenta tworzy się przez `ContractorInput`. Przy tworzeniu API WYMAGA:

- `alias` (string) - NIEPUSTY; bez aliasu jest 422, CRM generuje go z nazwy
  tylko na niektórych ścieżkach, więc podaj go zawsze,
- `name` (string) - nazwa kartoteki,
- `contractorTypeId` (int) - typ ze słownika.

Typowo dokładasz `taxId` (NIP) - i to on zwykle służy do wyszukania duplikatu.
Reszta jest opcjonalna: `fullName`, `regon`, `email`, `phone`, `domain`,
`contractorStatusId`, `industryId`, `ownerUserId`, `customField`, `address`
i inne. Pełna lista pól: `src/Dto/ContractorInput.php`.

Opcje sterujące zapisem (`duplicateCheck`, `taxIdLookup`, `createSystemNote`...)
NIE są polami encji - jadą osobno przez `WriteOptions` (`src/Dto/WriteOptions.php`).

Kluczowa konsekwencja dla Ciebie: użytkownik poda nazwę typu ("klient",
"dostawca"), a nie `contractorTypeId`. Twoim pierwszym krokiem jest zamiana tej
nazwy na id przez słownik (patrz niżej), tak samo jak przy adresach i statusach.

## Mapowanie intencji użytkownika na dane API

| Użytkownik mówi | Potrzebujesz | Skąd wziąć |
|---|---|---|
| "dodaj kontrahenta Acme" | `ContractorInput(alias, name, contractorTypeId)` | alias wymagany, typ ze słownika |
| "NIP 0000000000" | `taxId` | wprost z polecenia; użyj też jako `duplicateCheck` |
| "typ: klient" / "dostawca" | `contractorTypeId` | `dictionaries()->contractorTypes()` + `findByName()` |
| "status: aktywny" | `contractorStatusId` | `dictionaries()->contractorStatuses()` + `findByName()` |
| "znajdź firmę po NIP" | `list(['taxId' => ...])->first()` | odczyt, nie zgadywanie id |
| "znajdź firmę po nazwie" | `list(['name' => ...])->first()` | odczyt |
| "dodaj adres w Warszawie" | `addAddress(id, AddressInput)` | `addressTypeId` ze słownika `addressTypes()` |
| "nasze id ERP to K-0001" | `customField: ['erp_id' => 'K-0001']` | pole niestandardowe instancji |
| "zaimportuj listę firm" | `upsert([...], WriteOptions)` | batch, wynik per pozycja |

## Scenariusz flagowy: dodanie kontrahenta z kontrolą duplikatu

Polecenie użytkownika: *"Dodaj kontrahenta Acme, NIP 0000000000, typ klient."*

Twój tok postępowania:

1. Wyłuskaj z polecenia: nazwę, NIP, nazwę typu.
2. Zamień nazwę typu na `contractorTypeId` przez słownik; jeśli nie pasuje -
   PRZERWIJ i dopytaj, nie wstawiaj przypadkowego id.
3. Zbuduj `alias` (niepusty - wymagany przez API).
4. Włącz `duplicateCheck` po `taxId`, żeby nie założyć drugiej kartoteki tej
   samej firmy.
5. Utwórz kontrahenta i ROZRÓŻNIJ: czy powstał nowy rekord (201), czy trafiłeś
   w istniejący (200 z tym rekordem, bez zmiany danych).

```php
use TillioCrm\Api\Dto\ContractorInput;
use TillioCrm\Api\Dto\WriteOptions;

// Krok 1: dane z polecenia (Ty je wyłuskujesz z tekstu użytkownika).
$name    = 'Acme';
$taxId   = '0000000000';
$typeName = 'klient';

// Krok 2: nazwa typu -> contractorTypeId ze słownika. findByName() z
// ai_integration.md zwraca null, gdy nazwa nie pasuje - wtedy NIE zgaduj.
$contractorTypeId = findByName($client->dictionaries()->contractorTypes(), $typeName);
if ($contractorTypeId === null) {
    // Zły typ = albo 422, albo cicho zła kategoria firmy. Dopytaj użytkownika.
    throw new RuntimeException("Nieznany typ kontrahenta: $typeName. Podaj typ ze slownika.");
}

// Krok 3: alias jest WYMAGANY i niepusty - zbuduj go z nazwy, nie zostawiaj pustego.
$alias = mb_strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));

// Krok 4 + 5: tworzenie z duplicateCheck po taxId. taxId MA wartosc w payloadzie,
// wiec straznik SDK (IncompleteDuplicateCheckException) przepusci zadanie.
$result = $client->contractors()->create(
    new ContractorInput(
        name: $name,
        alias: $alias,
        contractorTypeId: $contractorTypeId,
        taxId: $taxId,
    ),
    new WriteOptions(duplicateCheck: ['taxId']),
);

// ROZRÓŻNIENIE 201-vs-200: przy trafieniu w duplikat API zwraca 200 z ISTNIEJĄCYM
// rekordem i NIE zmienia jego danych. $result->created odróżnia realną kreację od
// znaleziska - bez tego raportowalibyśmy fałszywe "utworzono" przy każdym przebiegu.
if ($result->created) {
    echo "Utworzono kontrahenta #{$result->id} ({$name}).\n";
} else {
    // matchedBy() mowi, po ktorym polu dopasowano istniejacy rekord (tu: 'taxId').
    echo "Kontrahent juz istnieje: #{$result->id}, dopasowano po {$result->matchedBy()}.\n";
}
```

Co zwrócić użytkownikowi: id kartoteki oraz informację, czy powstała nowa, czy
firma już była w CRM (i po czym ją rozpoznano - `matchedBy()`). Jeśli krok 2
nie rozwiązał typu - zamiast tworzyć, zapytaj o właściwą nazwę ze słownika.

## Warianty

### Szukanie firmy po NIP albo po nazwie

Nie zakładaj kartoteki w ciemno - najpierw sprawdź, czy jej nie ma. `taxId`
porównuje się po wartości znormalizowanej, `name` po nazwie.

```php
// Po NIP (najpewniejsze - NIP jest zwykle unikalny):
$contractor = $client->contractors()->list(['taxId' => '0000000000', 'limit' => 1])->first();
if ($contractor === null) {
    // Brak - mozesz zalozyc kartoteke (patrz scenariusz flagowy).
    echo "Nie znaleziono firmy po NIP.\n";
} else {
    echo "Znaleziono #{$contractor->id}: {$contractor->name}.\n";
}

// Po nazwie (bywa wiele trafien - nie bierz pierwszej z brzegu w waznej operacji):
$page = $client->contractors()->list(['name' => 'Acme', 'limit' => 25]);
$first = $page->first();   // ?Contractor
```

### Dodanie i aktualizacja adresu (z rozwiązaniem typu z nazwy)

Adresy to osobne trasy. `addAddress()` wymaga `addressTypeId` - rozwiąż go
z nazwy przez słownik `addressTypes()`, tak jak każdy inny identyfikator.

```php
use TillioCrm\Api\Dto\AddressInput;

// Nazwa typu adresu -> addressTypeId. Bez trafienia - dopytaj, nie zgaduj.
$addressTypeId = findByName($client->dictionaries()->addressTypes(), 'korespondencyjny');
if ($addressTypeId === null) {
    throw new RuntimeException('Nieznany typ adresu - podaj typ ze slownika addressTypes().');
}

// Dodanie adresu do istniejacej kartoteki (id kontrahenta znasz z wyszukania).
$client->contractors()->addAddress(7, new AddressInput(
    addressTypeId: $addressTypeId,
    street: 'Prosta 51',
    postCode: '00-838',
    city: 'Warszawa',
));

// Aktualizacja konkretnego adresu - po jego id z listy adresow kartoteki:
$addresses = $client->contractors()->addresses(7);   // list<Address>
$client->contractors()->updateAddress($addresses[0]->id, new AddressInput(city: 'Krakow'));
```

### Upsert paczki firm

Import listy firm rób przez `upsert()` - jedno żądanie na paczkę. HTTP jest
ZAWSZE 200, nawet gdy wszystkie pozycje poległy - wynik siedzi per pozycja
w `UpsertResult`. Sprawdzaj `hasFailures()`, bo kod HTTP tego nie powie.

```php
use TillioCrm\Api\Dto\ContractorInput;
use TillioCrm\Api\Dto\WriteOptions;

$result = $client->contractors()->upsert(
    [
        new ContractorInput(name: 'Acme', alias: 'acme', contractorTypeId: 1, taxId: '0000000000'),
        new ContractorInput(name: 'Beta', alias: 'beta', contractorTypeId: 1, taxId: '1111111111'),
    ],
    // duplicateCheck obowiazuje KAZDA pozycje - kazda musi miec taxId, inaczej
    // straznik SDK rzuci IncompleteDuplicateCheckException przed wyslaniem.
    new WriteOptions(duplicateCheck: ['taxId']),
);

echo "Utworzono {$result->createdCount()}, zaktualizowano {$result->updatedCount()}.\n";
if ($result->hasFailures()) {
    foreach ($result->failed() as $item) {
        // $item['index'] - pozycja w wyslanej paczce, $item['errors'] - powody {field, code, message}
        echo "Pozycja {$item['index']} nieudana.\n";
    }
}
```

### Pole niestandardowe jako klucz dedup (custom:erp_id)

Gdy firma jest identyfikowana po własnym kluczu ERP, dedupuj po polu
niestandardowym. Wartość MUSI trafić do `customField`, bo `duplicateCheck`
sprawdza payload lokalnie przed wysyłką.

```php
use TillioCrm\Api\Dto\ContractorInput;
use TillioCrm\Api\Dto\WriteOptions;

$result = $client->contractors()->create(
    new ContractorInput(
        name: 'Acme',
        alias: 'acme',
        contractorTypeId: 1,
        taxId: '0000000000',
        customField: ['erp_id' => 'K-0001'],   // wartosc pola, po ktorym dedupujemy
    ),
    // Sprawdzamy najpierw wlasny klucz ERP, potem NIP. Oba maja wartosc w payloadzie.
    new WriteOptions(duplicateCheck: ['custom:erp_id', 'taxId']),
);

// matchedBy() moze przyjsc jako 'custom:erp_id' albo 'taxId' - przewidz prefiks custom:.
if (!$result->created && $result->matchedBy() === 'custom:erp_id') {
    echo "Firma juz jest pod tym samym erp_id: #{$result->id}.\n";
}
```

### Wyszukanie rekordów bez wartości pola (pierwsze zasilenie)

Przy pierwszym powiązaniu integracji chcesz firmy, które jeszcze nie mają
klucza ERP. PUSTA wartość filtra `customField` znaczy "pole NIE ustawione" -
ale pusty string jest odrzucany, żebyś nie zrobił tego przez pomyłkę. Poproś
o to jawnie enumem `CustomFieldFilter::NotSet`.

```php
use TillioCrm\Api\CustomFieldFilter;

$unlinked = $client->contractors()->list([
    'customField' => ['erp_id' => CustomFieldFilter::NotSet],   // rekordy bez erp_id
]);
```

## Pułapki

- **`alias` jest wymagany i niepusty.** Bez aliasu API zwraca 422. Zbuduj go
  z nazwy przed `create()` - nie licz na to, że CRM go dogeneruje.
- **`duplicateCheck` ma strażnika w SDK.** Jeśli wskażesz pole (`taxId`,
  `custom:erp_id`...), które NIE ma wartości w payloadzie, SDK rzuca
  `IncompleteDuplicateCheckException` PRZED wysyłką. To celowe: API pominęłoby
  taki warunek po cichu i założyłoby duplikat. Uzupełnij wartość albo zdejmij
  pole z `duplicateCheck`.
- **`custom:erp_id` w `duplicateCheck` wymaga wartości w `customField`.**
  Sam wpis w `duplicateCheck` nie wystarczy - wartość klucza musi być
  w `customField['erp_id']`, inaczej strażnik przerwie zapis.
- **200 to nie zawsze "utworzono".** POST z trafieniem w duplikat zwraca 200
  z istniejącym rekordem i NIE zmienia jego danych. Rozróżniaj po
  `$result->created`; po czym dopasowano - `$result->matchedBy()`.
- **`matchedBy()` nie jest domkniętym zbiorem.** Poza `taxId|email|phone|name|domain`
  przychodzi `custom:<klucz>` - kod porównujący tę wartość musi przewidzieć
  wariant z prefiksem `custom:`.
- **`upsert()` zawsze daje HTTP 200.** Nawet gdy wszystkie pozycje poległy.
  Sprawdzaj `hasFailures()` i czytaj `failed()`, bo sam kod HTTP zgubi błędy.
- **Pusta wartość filtra `customField` = `CustomFieldFilter::NotSet`.** Pusty
  string jest odrzucany wyjątkiem, żeby przypadkiem nie zwrócić wszystkich
  niepowiązanych rekordów zamiast jednego. Brak wartości podawaj tylko jawnym
  enumem.
- **`address` to LISTA.** Pojedynczy obiekt adresu w `ContractorInput` jest
  odrzucany - podawaj `[new AddressInput(...)]`, nawet dla jednego adresu.
- **Odczyt vs zapis**: `include=address` działa tylko na liście
  (`list(['include' => 'address'])`), nie na `get()` - pojedynczą kartotekę
  z adresami złóż z `get()` plus `addresses()`.
