# Playbook: Uslugi (services)

Realizacja polecen uzytkownika dotyczacych uslug u kontrahentow: zakladanie
instancji uslugi z pozycji katalogu, kwoty i waluta, handlowiec i opiekun,
daty umowy. Pisane dla asystenta AI - zaklada wspolne wzorce z
[ai_integration.md](../ai_integration.md) (zwlaszcza `resolveUserId()`,
`findByName()`, `WriteResult` oraz "Zlota zasada: nie zgaduj id").

## Model danych w skrocie

Usluga to INSTANCJA pozycji katalogu zalozona u konkretnego kontrahenta.
Tworzy sie ja przez `ServiceInput`. Przy tworzeniu API WYMAGA dwoch pol:

- `catalogId` (int) - pozycja katalogu uslug (szablon),
- `contractorId` (int) - kontrahent, u ktorego usluga powstaje.

Katalog uslug (szablony) czytasz przez `serviceCatalog()` (patrz playbook
service-catalog) - stamtad bierzesz `catalogId`. Kwoty (`payValue`, `costValue`)
podajesz jako stringi dziesietne, nie float.

Uwaga o slownikach: odczyt (`Service`) niesie `paymentTermId`, `billingPeriodId`,
`invoiceTypeId`, `serviceStatusId`, ale `ServiceInput` tych pol NIE przyjmuje -
sa dziedziczone z pozycji katalogu / ustawiane po stronie CRM. Slowniki
`dictionaries()->servicePaymentTerms()` i `dictionaries()->serviceBillingPeriods()`
sluza do zinterpretowania tych wartosci przy odczycie, nie do zapisu.

Pelna lista pol: `src/Dto/ServiceInput.php`.

## Pola

### `ServiceInput` (tworzenie i aktualizacja uslugi)

| pole | typ | po co |
|---|---|---|
| `catalogId` | int | pozycja katalogu (szablon uslugi). WYMAGANE przy tworzeniu. Id przez `serviceCatalog()->list(['name' => ...])` lub `iterate()`. |
| `contractorId` | int | kontrahent, u ktorego zakladasz usluge. WYMAGANE przy tworzeniu. Id przez `contractors()->list(['name' => ...])`. |
| `customName` | string | wlasna nazwa instancji uslugi (nadpisuje nazwe z katalogu). |
| `note` | string | notatka do uslugi. |
| `place` | string | miejsce swiadczenia uslugi. |
| `salesDate` | string | data sprzedazy, ISO 8601 z offsetem strefy. |
| `payValue` | string | kwota przychodu jako string dziesietny (np. "199.00"). |
| `currency` | string | waluta kwot (np. "PLN"). |
| `costValue` | string | koszt jako string dziesietny. |
| `payDay` | int | dzien miesiaca platnosci (1-31). |
| `salesUserId` | int | handlowiec (osoba sprzedajaca). Id przez `resolveUserId()`. |
| `ownerUserId` | int | opiekun uslugi. Id przez `resolveUserId()`. |
| `agreementDate` | string | data zawarcia umowy, ISO 8601. |
| `agreementFrom` | string | data obowiazywania umowy od, ISO 8601. |
| `agreementTo` | string | data obowiazywania umowy do, ISO 8601. |
| `agreementEnd` | string | data zakonczenia umowy, ISO 8601. |
| `agreementTermination` | string | data wypowiedzenia umowy, ISO 8601. |
| `customField` | array | wartosci pol niestandardowych (klucz => wartosc). Definicje przez `customFields()`. |
| `createdAt` | string | data utworzenia przy imporcie historycznym, ISO 8601. |
| `creatorUserId` | int | autor. TYLKO przy tworzeniu. Id przez `resolveUserId()`. |

### Istotne pola odczytu (`Service`)

| pole | typ | po co |
|---|---|---|
| `id` | int | id uslugi - do powiazan (np. zgloszenie o `serviceId`) i dalszych operacji. |
| `catalogId` | int | pozycja katalogu, z ktorej powstala usluga. |
| `catalogName` | string | nazwa pozycji katalogu (gdy nie nadpisano `customName`). |
| `serviceStatusId` | int | status uslugi (odczyt); interpretacja przez `dictionaries()->serviceStatuses()`. |
| `paymentTermId` | int | termin platnosci (odczyt); interpretacja przez `dictionaries()->servicePaymentTerms()`. |
| `billingPeriodId` | int | okres rozliczeniowy (odczyt); interpretacja przez `dictionaries()->serviceBillingPeriods()`. |
| `invoiceTypeId` | int | typ faktury (odczyt); interpretacja przez `dictionaries()->serviceInvoiceTypes()`. |
| `salesUserId` / `ownerUserId` | int | handlowiec / opiekun - osobne role (kanon 2.0.0). |

## Mapowanie intencji uzytkownika na dane API

| Uzytkownik mowi | Potrzebujesz | Skad wziac |
|---|---|---|
| "usluga Hosting" (pozycja katalogu) | `catalogId` | `serviceCatalog()->list(['name' => 'Hosting', 'limit' => 1])->first()` |
| "u kontrahenta Acme" | `contractorId` | `contractors()->list(['name' => 'Acme'])` |
| "za 199 zl" / "kwota 199.00 PLN" | `payValue` + `currency` | podaj jako string "199.00" i "PLN", nie float |
| "handlowiec Jan Kowalski" | `salesUserId` | `resolveUserId($client, 'Jan', 'Kowalski')` |
| "opiekun Anna Nowak" | `ownerUserId` | `resolveUserId($client, 'Anna', 'Nowak')` |
| "wlasna nazwa 'Hosting Premium'" | `customName` | wprost z polecenia |
| "umowa od 2026-09-01 do 2027-08-31" | `agreementFrom` / `agreementTo` | ISO 8601 |
| "platne 10. dnia miesiaca" | `payDay` | int 10 |

## Scenariusz flagowy: usluga z katalogu dla kontrahenta

Polecenie uzytkownika: *"Zaloz usluge Hosting dla kontrahenta Acme, kwota
199.00 PLN, handlowiec Jan Kowalski."*

Twoj tok postepowania:

1. Wyluskaj pozycje katalogu, kontrahenta, kwote/walute i handlowca.
2. Rozwiaz pozycje katalogu na `catalogId` (moze byc zero/wiele - wtedy dopytaj).
3. Rozwiaz kontrahenta na `contractorId` i handlowca na `salesUserId`.
4. Utworz usluge (`catalogId` i `contractorId` wymagane); kwoty jako stringi.
5. Zwroc potwierdzenie z id.

```php
use TillioCrm\Api\Dto\ServiceInput;

// Krok 1: dane z polecenia (Ty je wyluskujesz z tekstu uzytkownika).
$catalogName = 'Hosting';

// Krok 2: pozycja katalogu po nazwie. Sprawdz, ile trafien - nie bierz na slepo.
$catalogPage = $client->serviceCatalog()->list(['name' => $catalogName, 'limit' => 2]);
$catalogItem = $catalogPage->first();
if ($catalogItem === null) {
    throw new RuntimeException("Nie znaleziono pozycji katalogu '{$catalogName}' - dopytaj albo zaloz ja w katalogu.");
}
if ($catalogPage->count() > 1) {
    // Kilka pozycji o tej nazwie - popros uzytkownika o wskazanie wlasciwej.
    throw new RuntimeException("Wiele pozycji katalogu '{$catalogName}' - dopytaj, ktora.");
}

// Krok 3: kontrahent i handlowiec na id - nie zgaduj.
$contractor = $client->contractors()->list(['name' => 'Acme', 'limit' => 1])->first();
if ($contractor === null) {
    throw new RuntimeException('Nie znaleziono kontrahenta Acme - dopytaj albo zaloz kartoteke.');
}
$salesUserId = resolveUserId($client, 'Jan', 'Kowalski');

// Krok 4: utworzenie uslugi. Kwoty jako stringi dziesietne, nie float.
// create() zwraca WriteResult: ->id, ->created, ->warnings.
$result = $client->services()->create(new ServiceInput(
    catalogId: $catalogItem->id,
    contractorId: $contractor->id,
    payValue: '199.00',
    currency: 'PLN',
    salesUserId: $salesUserId,
));

// Krok 5: potwierdzenie dla uzytkownika.
echo "Utworzono usluge #{$result->id} ({$catalogItem->name}) dla {$contractor->name}, 199.00 PLN.\n";
```

Co zwrocic uzytkownikowi: numer uslugi (`$result->id`), pozycje katalogu,
kontrahenta, kwote i handlowca. `WriteResult` niesie tez `->warnings` (ciche
korekty normalizacji) - jesli niepuste, pokaz je uzytkownikowi.

## Warianty

### Usluga z wlasna nazwa i notatka

```php
$client->services()->create(new ServiceInput(
    catalogId: $catalogItem->id,
    contractorId: $contractor->id,
    customName: 'Hosting Premium',
    note: 'Migracja z poprzedniego dostawcy w toku.',
    payValue: '349.00',
    currency: 'PLN',
));
```

### Usluga z umowa

```php
// Daty umowy w ISO 8601 z offsetem strefy.
$client->services()->create(new ServiceInput(
    catalogId: $catalogItem->id,
    contractorId: $contractor->id,
    payValue: '1200.00',
    currency: 'PLN',
    agreementFrom: (new DateTimeImmutable('2026-09-01'))->format(DATE_ATOM),
    agreementTo: (new DateTimeImmutable('2027-08-31'))->format(DATE_ATOM),
    payDay: 10,
));
```

### Odczyt uslug kontrahenta

```php
// Wszystkie uslugi kontrahenta - iterate przechodzi wszystkie strony.
foreach ($client->services()->iterate(['contractorId' => $contractor->id]) as $service) {
    $label = $service->customName ?? $service->catalogName;
    echo "#{$service->id} {$label}: {$service->payValue} {$service->currency}\n";
}
```

## Pulapki

- **`catalogId` i `contractorId` sa wymagane.** Usluga bez pozycji katalogu albo
  bez kontrahenta = 422. Rozwiaz oba przed `create()`.
- **Kwoty jako stringi dziesietne.** `payValue` i `costValue` to np. "199.00",
  nie float `199.0` - inaczej ryzykujesz utrate groszy albo 400.
- **`catalogId` z nazwy bywa niejednoznaczny.** Katalog moze miec kilka pozycji
  o tej samej nazwie - sprawdz liczbe trafien i dopytaj, nie bierz pierwszej.
- **`salesUserId` i `ownerUserId` to osobne role** (handlowiec vs opiekun) -
  nie wpisuj tego samego id w oba, jesli uzytkownik rozroznia te osoby.
- **Nie zgaduj `userId`.** `resolveUserId()` celowo rzuca przy wielu trafieniach.
  Dopytaj o e-mail.
- **`paymentTermId` / `billingPeriodId` / `serviceStatusId` / `invoiceTypeId` to
  tylko odczyt.** `ServiceInput` ich nie przyjmuje - dziedziczy je z pozycji
  katalogu / CRM. Slowniki `servicePaymentTerms()`, `serviceBillingPeriods()`,
  `serviceStatuses()`, `serviceInvoiceTypes()` sluza do interpretacji odczytu.
- **Daty w ISO 8601 z offsetem strefy** (`DATE_ATOM`). Inny format to 400.
- **`create()` zwraca `WriteResult`** (`->id` = `serviceId`, plus `->created`,
  `->warnings`, `isDuplicate()`), nie samo id. Szczegoly: sekcja "Co zwracaja
  zapisy" w [ai_integration.md](../ai_integration.md).
