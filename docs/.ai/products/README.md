# Playbook: Produkty (products)

Realizacja poleceń użytkownika dotyczących katalogu produktów: zakładanie
pozycji, aktualizacja ceny i danych, wyszukiwanie po nazwie/SKU/kodzie
zewnętrznym, ochrona przed duplikatami. Pisane dla asystenta AI - zakłada
wspólne wzorce z [ai_integration.md](../ai_integration.md) (zwłaszcza
`findByName()`, `WriteResult` i "Złotą zasadę: nie zgaduj id").

## Pola

Zapis idzie przez `ProductInput` (`src/Dto/ProductInput.php`). Named arguments;
`null` = pola nie wysyłamy. Opcje duplikatów jadą OBOK, w `WriteOptions`
(`src/Dto/WriteOptions.php`). Przy tworzeniu API wymaga wyłącznie `name`.

| pole | typ | po co (i skąd wziąć id/referencję) |
|---|---|---|
| `name` | `?string` | Nazwa produktu. WYMAGANE przy `create()`. |
| `description` | `?string` | Opis pozycji katalogowej. |
| `sku` | `?string` | Symbol/kod magazynowy. Filtr `list(['sku' => ...])` oraz klucz `duplicateCheck`. |
| `ean` | `?string` | Kod kreskowy EAN. Klucz `duplicateCheck` (nie ma filtra `list` po EAN). |
| `externalId` | `?string` | Id z systemu zewnętrznego (ERP). GŁÓWNY klucz integracji: filtr `list` i klucz `duplicateCheck`. Produkty nie mają pól niestandardowych, więc to jedyny stały uchwyt na rekord spoza CRM. |
| `groupId` | `?int` | Grupa produktowa. Rozwiąż z nazwy przez `productGroups()->list(['name' => ...])` albo `iterate()` (patrz playbook [product-groups](../product-groups/README.md)); nie zgaduj liczby. |
| `measureId` | `?int` | Jednostka miary (szt., kg, ...). SDK NIE ma słownika jednostek - `measureId` przepisz z istniejącego produktu (`Product->measureId`, obok `Product->measure`) albo z panelu CRM; przy braku pewności pomiń. |
| `price` | `?string` | Cena jako STRING dziesiętny (`"499.00"`), nie float. Pusty/`null` = bez ceny. |
| `currency` | `?string` | Kod waluty (`"PLN"`). Dozwolone kody: `dictionaries()->currencies()`. |
| `taxRate` | `?string` | Stawka VAT jako string (`"23"`). Nie float. |
| `status` | `?int` | Status produktu (aktywny/wycofany). Kontrakt nie definiuje skali liczbowej dla tej instancji - nie zgaduj; podejrzyj `Product->status` istniejącej pozycji albo pomiń. |
| `createdAt` | `?string` | Data utworzenia. Tylko przy imporcie historycznym; ISO 8601. |
| `creatorUserId` | `?int` | Autor. Tylko przy imporcie historycznym; `resolveUserId()`. |

Opcje zapisu (`WriteOptions`, istotne dla produktów):

| pole | typ | po co |
|---|---|---|
| `duplicateCheck` | `?list<string>` | Pola do wyszukania istniejącego produktu. Dla produktów sensowne: `externalId`, `sku`, `ean`. KAŻDE wskazane pole MUSI mieć wartość w payloadzie, inaczej SDK rzuca `IncompleteDuplicateCheckException` PRZED wysłaniem. |
| `allowDuplicates` | `?bool` | `true` = nie szukaj duplikatu, twórz zawsze. |

Pola tylko do ODCZYTU (`Product`, `src/Dto/Product.php`), których `ProductInput`
nie przyjmuje: `id`, `groupName` (nazwa grupy obok `groupId`), `measure` (nazwa
jednostki obok `measureId`), `updatedAt`, `customField` (u produktów zwykle pusta
mapa - patrz Pułapki). Pełny surowy rekord: `Product->raw`.

## Model danych

Produkt to pozycja katalogu. Kluczem łączącym z systemem zewnętrznym jest
`externalId` (produkty nie mają pól niestandardowych). Duplikat rozpoznaje się po
`externalId`, `sku` lub `ean` - wskazujesz JEDNO albo kilka przez `duplicateCheck`.
`create()` zwraca `WriteResult`: przy trafieniu w istniejący rekord `->created`
jest `false`, a `->matchedBy()` mówi, po którym polu dopasowano. Kwoty (`price`,
`taxRate`) API oddaje i przyjmuje jako STRINGI dziesiętne.

## Mapowanie intencji użytkownika na dane API

| Użytkownik mówi | Potrzebujesz | Skąd wziąć |
|---|---|---|
| "dodaj produkt Licencja PRO" | `name` | wprost z polecenia (wymagane) |
| "SKU LIC-PRO" / "indeks LIC-PRO" | `sku` | wprost; użyj też jako `duplicateCheck` |
| "kod z ERP to ERP-001" | `externalId` | wprost; główny klucz `duplicateCheck` |
| "grupa Oprogramowanie" | `groupId` | `productGroups()->list(['name' => 'Oprogramowanie'])`, dopasuj po nazwie |
| "cena 499 zl netto" | `price: "499.00"`, `currency: "PLN"` | sformatuj jako string; waluta ze `dictionaries()->currencies()` |
| "VAT 23%" | `taxRate: "23"` | string, bez znaku procenta |
| "znajdz produkt po SKU X" | filtr `list(['sku' => 'X'])` | odczyt, nie zapis |
| "podnies cene 42 do 549" | `update(42, ...)` | id produktu z wcześniejszego odczytu |

## Scenariusz flagowy: dodanie produktu z ochroną przed duplikatem

Polecenie użytkownika: *"Dodaj do katalogu Licencje PRO, SKU LIC-PRO, kod z ERP
ERP-001, cena 499 zl netto, VAT 23%, grupa Oprogramowanie. Nie chcemy dubla,
gdyby juz byla."*

Twój tok postępowania:

1. Wyłuskaj z polecenia: nazwę, SKU, externalId, cenę, VAT, nazwę grupy.
2. Rozwiąż nazwę grupy na `groupId` przez odczyt - nie zgaduj liczby.
3. Ustaw `duplicateCheck` na pola, które faktycznie masz (tu `sku` i `externalId`).
4. Utwórz produkt; rozróżnij utworzenie od trafienia w istniejący rekord.

```php
use TillioCrm\Api\Dto\ProductInput;
use TillioCrm\Api\Dto\WriteOptions;

// Krok 1: dane z polecenia (Ty je wyluskujesz z tekstu uzytkownika).
$name       = 'Licencja PRO';
$sku        = 'LIC-PRO';
$externalId = 'ERP-001';
$price      = '499.00';   // STRING dziesietny, nie float
$taxRate    = '23';       // STRING, bez znaku procenta
$groupName  = 'Oprogramowanie';

// Krok 2: nazwa grupy -> groupId przez odczyt. Nie zgaduj liczby.
$group = $client->productGroups()->list(['name' => $groupName, 'limit' => 1])->first();
if ($group === null) {
    // Grupy nie ma - dopytaj albo zaloz ja (patrz playbook product-groups),
    // nie wstawiaj przypadkowego groupId.
    throw new RuntimeException("Nie znaleziono grupy '$groupName' - dopytaj albo zaloz grupe.");
}

// Krok 3 + 4: tworzymy z wyszukaniem duplikatu po sku i externalId.
// KAZDE pole z duplicateCheck musi miec wartosc w payloadzie - oba mamy,
// wiec straznik IncompleteDuplicateCheckException nie zatrzyma zapisu.
$result = $client->products()->create(
    new ProductInput(
        name: $name,
        sku: $sku,
        externalId: $externalId,
        groupId: $group->id,
        price: $price,
        currency: 'PLN',
        taxRate: $taxRate,
    ),
    new WriteOptions(duplicateCheck: ['sku', 'externalId']),
);

// WriteResult: ->id, ->created (true=201/utworzono, false=200/duplikat),
// ->matchedBy() (po ktorym polu dopasowano), ->warnings (ciche korekty).
if ($result->isDuplicate()) {
    echo "Produkt juz istnial (#{$result->id}), dopasowano po {$result->matchedBy()}.\n";
} else {
    echo "Utworzono produkt #{$result->id} (SKU {$sku}).\n";
}
if ($result->warnings !== []) {
    echo "Uwagi: " . implode('; ', $result->warnings) . "\n";
}
```

Co zwrócić użytkownikowi: id produktu i informację, czy powstał, czy trafiono
w istniejący (i po czym). Jeśli grupy nie było - zapytaj, zanim utworzysz.

## Warianty

### Aktualizacja ceny istniejącego produktu

```php
use TillioCrm\Api\Dto\ProductInput;

// update() wysyla TYLKO podane pola. Cena jako string dziesietny.
$client->products()->update(42, new ProductInput(price: '549.00'));
```

### Wyszukanie produktu przed zapisem

```php
// Filtry list: name, sku, externalId (oraz updatedAfter/updatedBefore).
$byExternal = $client->products()->list(['externalId' => 'ERP-001', 'limit' => 1])->first();
$byName     = $client->products()->list(['name' => 'Licencja', 'limit' => 100]);
```

### Pełny przebieg katalogu

```php
// iterate() wymusza sort=id i przechodzi wszystkie strony bez gubienia rekordow.
foreach ($client->products()->iterate() as $product) {
    $product->sku;
    $product->price;   // string dziesietny
}
```

### Tworzenie bez sprawdzania duplikatu

```php
use TillioCrm\Api\Dto\WriteOptions;

// Gdy swiadomie chcemy nowy rekord mimo mozliwego dubla.
$client->products()->create(
    new ProductInput(name: 'Pozycja jednorazowa'),
    new WriteOptions(allowDuplicates: true),
);
```

## Pułapki

- **Kwoty to STRINGI dziesiętne, nie floaty.** `price` i `taxRate` przyjmuj
  i oddawaj jako `"499.00"`, `"23"`. Rzutowanie na `float` gubi grosze i psuje
  porównania (`0.1 + 0.2`); jeśli musisz liczyć, użyj arytmetyki na stringach
  (`bcmath`) i wynik z powrotem sformatuj jako string.
- **`duplicateCheck` wymaga wartości w payloadzie.** Wskazanie `['sku']` bez
  podania `sku` w `ProductInput` to `IncompleteDuplicateCheckException` rzucony
  LOKALNIE, zanim żądanie wyjdzie. Uzupełnij pole albo zdejmij je z listy.
- **Produkty NIE mają pól niestandardowych.** Filtr `customField` na
  `products()->list()` zwraca 400, a `Product->customField` bywa pustą mapą.
  Do wiązania z systemem zewnętrznym używaj `externalId`, nie custom fields.
- **`measureId` bez słownika w SDK.** Nie ma końcówki listującej jednostki miary
  - nie zgaduj liczby. Przepisz `measureId` z istniejącego produktu albo pomiń.
- **`status` bez zdefiniowanej skali.** Kontrakt nie mapuje liczb na "aktywny/
  wycofany" dla tej instancji. Podejrzyj `Product->status` istniejącej pozycji
  albo pomiń pole, zamiast wstawiać przypadkową wartość.
- **`create()` zwraca `WriteResult`** (`->id`, `->created`, `->matchedBy()`,
  `->warnings`), nie samo id. `update()` też zwraca `WriteResult`. Szczegóły:
  sekcja "Co zwracają zapisy" w [ai_integration.md](../ai_integration.md).
- **Odczyt vs zapis**: `Product` niesie `groupName`, `measure`, `updatedAt`,
  których `ProductInput` nie przyjmuje - to pola ustawiane/wyliczane po stronie
  CRM.
