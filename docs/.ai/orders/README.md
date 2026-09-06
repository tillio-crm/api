# Playbook: Zamowienia (orders)

Realizacja polecen uzytkownika dotyczacych zamowien: wystawianie zamowienia
kontrahentowi z pozycjami z katalogu, aktualizacja metadanych (status, daty,
notatka), odczyt. Pisane dla asystenta AI - zaklada wspolne wzorce z
[ai_integration.md](../ai_integration.md) (zwlaszcza `resolveUserId()`,
`findByName()`, `WriteResult` i "Zlota zasada: nie zgaduj id").

Kluczowa specyfika zamowien: tworzenie jest ZAWSZE podpiete pod kartoteke
kontrahenta (`create(contractorId, OrderInput)`), a lista `products` musi byc
NIEPUSTA i kazda pozycja musi wskazywac istniejacy produkt z katalogu
(`productId` ALBO `productSku`). Bez tego zapis nie ma prawa sie udac.

## Pola

Zapis idzie przez `OrderInput` (`src/Dto/OrderInput.php`), pozycje przez
`OrderProductInput` (`src/Dto/OrderProductInput.php`). Named arguments, `null`
= nie wysylaj pola. Wszystkie pola input-DTO:

### OrderInput

| pole | typ | po co (skad wziac) |
|---|---|---|
| `products` | `list<OrderProductInput>` | Pozycje zamowienia. WYMAGANE i NIEPUSTE przy tworzeniu. Budujesz z pozycji katalogowych (patrz OrderProductInput nizej). |
| `orderStatusId` | `int` | Status zamowienia. Z `dictionaries()->orderStatuses()`, dopasuj nazwe przez `findByName()`. Pominiete = domyslny status instancji. |
| `note` | `string` | Notatka/uwagi do zamowienia. Tekst wprost od uzytkownika. |
| `currency` | `string` | Kod waluty (np. `PLN`, `EUR`). Lista dostepnych: `dictionaries()->currencies()`. Pominiete = domyslna waluta instancji. |
| `place` | `string` | Miejsce wystawienia. Tekst wprost. |
| `orderDate` | `string` | Data zamowienia, ISO 8601. Policz i sformatuj `DATE_ATOM`. |
| `validUntil` | `string` | Waznosc oferty/zamowienia, ISO 8601. |
| `deliveryDate` | `string` | Data dostawy/realizacji, ISO 8601. |
| `number` | `string` | Numer dokumentu. Zwykle nadaje CRM - ustawiaj tylko przy imporcie/gdy uzytkownik podal wlasny. To po tym polu wyszukuje sie zamowienia (nie ma pol niestandardowych). |
| `foreignNumber` | `int` | Numer sekwencyjny/obcy (klucz dla ERP). Ustawiaj przy integracji z systemem zewnetrznym. |
| `sentDate` | `string` | Data wyslania do klienta, ISO 8601. |
| `createdAt` | `string` | Data utworzenia - TYLKO przy imporcie historycznym, nie przy biezacym zamowieniu. |
| `creatorUserId` | `int` | Autor - TYLKO przy imporcie historii. Przez `resolveUserId()`. |

### OrderProductInput (pozycja)

| pole | typ | po co (skad wziac) |
|---|---|---|
| `productId` | `int` | Id produktu z katalogu. WYMAGANE `productId` ALBO `productSku`. Z `products()->list([...])` po nazwie. |
| `productSku` | `string` | SKU produktu z katalogu. Alternatywa dla `productId`. Nieznane SKU = 422 - produkt musi wczesniej istniec (`products()->create()`). |
| `customName` | `string` | Nadpisuje nazwe pozycji wzieta z katalogu. NIE tworzy pozycji wolnej - pozycja i tak wskazuje realny produkt. |
| `quantity` | `string` | Ilosc jako string dziesietny (np. `'2'`, `'1.5'`). |
| `price` | `string` | Cena jednostkowa jako string dziesietny. Pominieta = cena z katalogu. |
| `discount` | `string` | Rabat jako string dziesietny. |
| `taxRate` | `string` | Stawka podatku jako string dziesietny (np. `'23'`). Pominieta = stawka z katalogu. |
| `measureId` | `int` | Jednostka miary (id ze slownika miar instancji). Pominieta = jednostka z katalogu; gdy nie znasz id - pomin, nie zgaduj. |

Pola odczytu warte uwagi (`Order`, `src/Dto/Order.php`): `id`, `number` (klucz
wyszukiwania), `foreignNumber`, `totalAmount` (suma, string dziesietny),
`orderStatusId`, `products` (`list<OrderProduct>`), `raw` (pelny payload).
`OrderProduct` (odczyt) ma `name`, `price`, `quantity`, `taxRate`, `discount`,
`measure` (nazwa jednostki, nie id) - wszystkie kwoty to stringi dziesietne.

## Model danych

Zamowienie tworzy sie przez `create(contractorId, OrderInput)`
(`POST /v2/contractors/{contractorId}/orders`). API WYMAGA:

- `contractorId` w sygnaturze metody (do czyjej kartoteki),
- niepustej listy `products`, w ktorej KAZDA pozycja ma `productId` albo
  `productSku`.

Konsekwencje dla Ciebie:

- Uzytkownik poda nazwe firmy, nie `contractorId` - najpierw rozwiaz kontrahenta
  (patrz playbook kontrahentow), potem produkty na `productId`/`productSku`.
- `update(id, OrderInput)` (`PUT /v2/orders/{id}`) zmienia TYLKO metadane
  (status, daty, notatke, numer). NIE edytuje pozycji zamowienia - `products`
  podane przy update nie sa sciezka do wstecznej korekty pozycji, nie licz na to.
- Kwoty (`quantity`, `price`, `taxRate`, `discount`, `totalAmount`) to STRINGI
  dziesietne, nie floaty - przekazuj je jako `'2'`, `'149.00'`, nie `2`, `149.0`.
- Zamowienia NIE maja pol niestandardowych - do wyszukania konkretnego sluzy
  filtr `number` albo `foreignNumber`.

## Mapowanie intencji

| Uzytkownik mowi | Potrzebujesz | Skad wziac |
|---|---|---|
| "dla kontrahenta Acme" | `contractorId` (argument `create`) | `contractors()->list(['name' => 'Acme'])` |
| "2 licencje LIC-PRO" | `OrderProductInput(productSku: 'LIC-PRO', quantity: '2')` | SKU wprost albo `products()->list(['name' => ...])` -> `productId` |
| "produkt Wdrozenie" | `OrderProductInput(productId: ...)` | `products()->list(['name' => 'Wdrozenie'])`, dopasuj |
| "status Przyjete" | `orderStatusId` | `dictionaries()->orderStatuses()` + `findByName()` |
| "w euro" | `currency: 'EUR'` | `dictionaries()->currencies()` - sprawdz, czy kod jest na liscie |
| "z dostawa na 2026-09-20" | `deliveryDate` (ISO 8601) | policz date, `DATE_ATOM` |
| "wazne do konca miesiaca" | `validUntil` (ISO 8601) | policz date, `DATE_ATOM` |
| "cena 149 za sztuke" | `price: '149.00'` (string!) | wprost, jako string dziesietny |

## Scenariusz flagowy: zamowienie dla kontrahenta z pozycjami z katalogu

Polecenie uzytkownika: *"Wystaw zamowienie dla Acme na 2 licencje LIC-PRO
i 1 wdrozenie, status Przyjete."*

Tok postepowania:

1. Rozwiaz kontrahenta Acme na `contractorId` (zero trafien = przerwij i dopytaj).
2. Zbuduj pozycje: kazda musi wskazywac produkt z katalogu przez `productSku`
   albo `productId`. Nieznany produkt = przerwij, nie zgaduj SKU.
3. Rozwiaz status na `orderStatusId` (null = dopytaj, nie wstawiaj losowego id).
4. Utworz zamowienie i zwroc potwierdzenie z id oraz suma.

```php
use TillioCrm\Api\Dto\OrderInput;
use TillioCrm\Api\Dto\OrderProductInput;

// Krok 1: kontrahent po nazwie -> contractorId. NIE zgaduj id kartoteki.
$contractor = $client->contractors()->list(['name' => 'Acme', 'limit' => 1])->first();
if ($contractor === null) {
    // Brak kartoteki - nie tworz zamowienia w prozni, oddaj to uzytkownikowi.
    throw new RuntimeException('Nie znaleziono kontrahenta Acme - dopytaj albo zaloz kartoteke.');
}

// Krok 2: pozycje. Kazda wskazuje realny produkt z katalogu (productSku ALBO
// productId). Ilosci i ceny jako STRINGI dziesietne. Nieznane SKU = 422, wiec
// dla produktow podanych nazwa (nie SKU) rozwiaz je przez products()->list().
$deployment = $client->products()->list(['name' => 'Wdrozenie', 'limit' => 1])->first();
if ($deployment === null) {
    throw new RuntimeException('Nie znaleziono produktu "Wdrozenie" w katalogu - dopytaj albo dodaj produkt.');
}

$products = [
    // LIC-PRO podane wprost jako SKU - product istnieje w katalogu.
    new OrderProductInput(productSku: 'LIC-PRO', quantity: '2'),
    // Wdrozenie rozwiazane na productId z katalogu.
    new OrderProductInput(productId: $deployment->id, quantity: '1'),
];

// Krok 3: status ze slownika po nazwie. null = nazwa spoza slownika instancji.
$statusId = findByName($client->dictionaries()->orderStatuses(), 'Przyjete');
if ($statusId === null) {
    // Nie wstawiaj przypadkowego id statusu - dopytaj o poprawna nazwe.
    throw new RuntimeException('Status "Przyjete" nie pasuje do slownika instancji - dopytaj o nazwe statusu.');
}

// Krok 4: utworzenie. products jest wymagane i niepuste - mamy je.
// create() zwraca WriteResult: ->id (id zamowienia), ->created, ->warnings.
$result = $client->orders()->create($contractor->id, new OrderInput(
    products: $products,
    orderStatusId: $statusId,
));

echo "Utworzono zamowienie #{$result->id} dla kontrahenta {$contractor->id} (" . count($products) . " pozycje).\n";

// Suma i pozycje sa policzone po stronie CRM - odczytaj je swiezym get().
$order = $client->orders()->get((int) $result->id);
echo "Wartosc: {$order->totalAmount} {$order->currency}.\n";
```

Co zwrocic uzytkownikowi: numer zamowienia (`$result->id`), liczbe pozycji,
sume (`$order->totalAmount` z waluta) i status. `WriteResult` niesie tez
`->warnings` (ciche korekty normalizacji) - jesli niepuste, pokaz je.

## Warianty

### Zamowienie z niestandardowa nazwa pozycji

`customName` NADPISUJE nazwe z katalogu, ale pozycja i tak wskazuje realny
produkt (nie ma pozycji wolnych):

```php
use TillioCrm\Api\Dto\OrderInput;
use TillioCrm\Api\Dto\OrderProductInput;

$client->orders()->create($contractorId, new OrderInput(
    products: [
        new OrderProductInput(
            productSku: 'LIC-PRO',
            customName: 'Licencja Pro - pakiet roczny Acme',
            quantity: '1',
            price: '1200.00',   // string dziesietny, nie float
        ),
    ],
));
```

### Aktualizacja metadanych (status, daty, notatka)

`update()` zmienia metadane, NIE pozycje. Typowe: przesuniecie statusu albo
dopisanie daty wyslania.

```php
use TillioCrm\Api\Dto\OrderInput;

$statusId = findByName($client->dictionaries()->orderStatuses(), 'Wyslane');
$client->orders()->update($orderId, new OrderInput(
    orderStatusId: $statusId,
    sentDate: (new DateTimeImmutable('now'))->format(DATE_ATOM),
    note: 'Wyslano kurierem, numer przesylki 123.',
));
```

### Znalezienie zamowienia po numerze

```php
// number = pelny numer dokumentu; foreignNumber = numer sekwencyjny (pewny
// klucz dla ERP). Zamowienia nie maja pol niestandardowych - to jedyne klucze.
$order = $client->orders()->list(['number' => 'ZAM/2026/07', 'limit' => 1])->first();
```

### Import historyczny

Przy imporcie starych zamowien mozesz ustawic date utworzenia i autora
(pola ignorowane przy zwyklym tworzeniu):

```php
use TillioCrm\Api\Dto\OrderInput;
use TillioCrm\Api\Dto\OrderProductInput;

$client->orders()->create($contractorId, new OrderInput(
    products: [new OrderProductInput(productSku: 'LIC-PRO', quantity: '1')],
    createdAt: '2024-01-15T09:00:00+01:00',
    creatorUserId: $importUserId,
));
```

## Pulapki

- **`products` jest wymagane i NIEPUSTE.** Zamowienie bez pozycji = 422.
  Zbuduj przynajmniej jedna pozycje przed `create()`.
- **Kazda pozycja MUSI miec `productId` albo `productSku`.** Nie ma pozycji
  wolnych - `customName` tylko nadpisuje nazwe realnego produktu. Nieznane
  SKU = 422; produkt musi wczesniej istniec w katalogu.
- **`update()` nie edytuje pozycji.** Zmienia metadane (status, daty, notatke,
  numer). Nie licz na wsteczna korekte pozycji przez update.
- **Kwoty to STRINGI dziesietne**, nie floaty. `'149.00'`, nie `149.0`. Dotyczy
  `quantity`, `price`, `taxRate`, `discount`, a przy odczycie `totalAmount`.
- **`contractorId` nie jest polem `OrderInput`** - to pierwszy argument
  `create()`. Rozwiaz kontrahenta z nazwy przed zapisem.
- **Brak pol niestandardowych.** Do wyszukania konkretnego zamowienia uzyj
  `number` albo `foreignNumber`, nie `customField`.
- **`orderStatusId` z nazwy bywa null**, gdy nazwa jest spoza slownika instancji
  - wtedy dopytaj, nie wstawiaj przypadkowego id.
- **`create()` zwraca `WriteResult`** (`->id`, `->created`, `->warnings`),
  nie samo id ani nie `Order`. Sume i policzone pozycje odczytaj swiezym
  `get()`. Szczegoly: sekcja "Co zwracaja zapisy" w
  [ai_integration.md](../ai_integration.md).
