# Pola niestandardowe (custom fields)

Pola niestandardowe to dodatkowe pola definiowane per encja (kontrahent, kontakt,
lead, ...) ponad standardowy zestaw. Ten dokument mówi, jak czytać i zapisywać
ICH WARTOŚCI na rekordach oraz jak zarządzać ich DEFINICJAMI. Filtrowanie po
polach niestandardowych opisuje [../queries/README.md](../queries/README.md),
a pliki pól typu FILE - [../files/README.md](../files/README.md).

## Dwie warstwy: definicje i wartości

- **Definicja** - opisuje pole (nazwa, typ, przypisania). Zarządza nią zasób
  `customFields()`. Klucz pola (`key`) GENERUJE CRM z etykiety - nie da się go
  narzucić; odczytaj go po założeniu i zapisz po swojej stronie.
- **Wartość** - konkretna dana na rekordzie, w kluczu `customField[<key>]`
  (odczyt i zapis kontrahenta, leada, ...). NIE zarządza nią `customFields()` -
  wartości ustawia się w input-DTO danej encji przez pole `customField`.

## Odczyt wartości na rekordzie

Każdy odczyt rekordu niesie `customField` (mapa `key => wartość`). Typ wartości
zależy od typu pola:

```php
$contractor = $client->contractors()->get(42);
$erpId = $contractor->customField['erp_id'] ?? null;   // np. string
```

Kształt wartości wg typu pola (kolumna `type` w definicji):

| Typ pola | Wartość w `customField[key]` |
|---|---|
| `STR`, `TEXT`, `VARCHAR`, `INT`, `DECIMAL`, `DATETIME` | skalar (string/liczba; kwoty jako string dziesiętny) |
| `SELECT` | wybrana opcja (string) |
| `MULTISELECT`, `USERS` | lista wartości |
| `CONTRACTOR`, `PRODUCT`, `PROJECT` | id powiązanego rekordu (pola wielowartościowe: lista id) |
| `SALESPIPELINE` | id powiązanej szansy sprzedaży (pole wielowartościowe: lista id), API >= 2.8.0 |
| `FILE` | obiekt `{fileName, mimeType, sizeBytes, storagePath, fileUrl}` albo `null` |

## Zapis wartości na rekordzie

Wartości pól ustawia się przez `customField` w input-DTO encji (mapa `key => wartość`):

```php
use TillioCrm\Api\Dto\ContractorInput;

$client->contractors()->update(42, new ContractorInput(
    customField: ['erp_id' => 'OPT-8123', 'segment' => 'premium'],
));
```

Pola WIELOWARTOŚCIOWE (`MULTISELECT`, wielowartościowe `PRODUCT`,
`SALESPIPELINE`) przyjmują przy zapisie LISTĘ wartości - nawet dla jednej
wartości podaj listę (API >= 2.9.0):

```php
$client->contractors()->update(42, new ContractorInput(
    customField: [
        'branze'   => ['IT', 'produkcja'],   // MULTISELECT: lista opcji
        'produkty' => ['12', '15'],          // wiele id produktow
        'szanse'   => ['3'],                 // jedna wartosc, ale nadal LISTA
    ],
));
```

WYJĄTEK: pola typu **FILE**. Ich wartości NIE przechodzą przez `customField`
w PUT (core je odrzuca) - plik wgrywa się osobną metodą, patrz niżej.

## Definicje: odczyt, zakładanie, przypisania

```php
use TillioCrm\Api\Dto\CustomFieldInput;

// Lista definicji encji - stad bierzesz `key` do adresowania wartosci.
$defs = $client->customFields()->list('contractor');
foreach ($defs as $def) {
    // $def->key, $def->name, $def->type (STR/SELECT/FILE/...), $def->required
}

// Zalozenie pola (wymaga klucza API administratora). Klucz nadaje CRM - odczytaj z wyniku.
$result = $client->customFields()->create(new CustomFieldInput(
    entity: 'contractor',
    name: 'ERP ID',
    type: 'STR',
    editableBy: ['userIds' => [7]],   // ACL: kto edytuje; PODAJ ZAWSZE (patrz Pulapki)
));
$key = $result->data['key'];   // np. "contractor_str_3" - zapisz po swojej stronie

// Zmiana PRZYPISANIA pola do uzytkownikow (jedyna edycja definicji przez API).
$client->customFields()->update('contractor', $key, ['assignedTo' => [7, 12]]);
```

Zakładaj pola IDEMPOTENTNIE: etykieta (`name`) jest unikalna w encji, więc
najpierw `list()`, twórz tylko brakujące.

## Pliki w polu typu FILE (API >= 2.6.0)

Pole FILE ma osobne metody - wartości nie idą przez `customField`:

```php
use TillioCrm\Api\Transport\FileUpload;

// Wgranie (multipart, zastepuje poprzedni plik, limit 25 MB, bez retry).
$file = $client->customFields()->uploadFieldFile('contractor', 42, 'contractor_file_2', FileUpload::fromPath('/sciezka/umowa.pdf'));

// Odczyt metadanych + swiezy downloadUrl (null = pole bez pliku).
$file = $client->customFields()->getFieldFile('contractor', 42, 'contractor_file_2');
if ($file !== null && $file->downloadUrl !== null) {
    $bytes = $client->download($file->downloadUrl);
}

// Usuniecie pliku z pola.
$client->customFields()->deleteFieldFile('contractor', 42, 'contractor_file_2');
```

Encje z polami FILE: `contractor`, `contact`, `note`, `lead`, `ticket`,
`service`, `project`, `pipeline` (zadania NIE mają tej końcówki). Pełny opis:
[../files/README.md](../files/README.md).

## Custom field jako klucz deduplikacji

W upsertach i tworzeniu z `duplicateCheck` można szukać duplikatu po polu
niestandardowym, prefiksując klucz `custom:`:

```php
use TillioCrm\Api\Dto\WriteOptions;

$client->contractors()->create($input, new WriteOptions(duplicateCheck: ['custom:erp_id']));
```

STRAŻNIK: jeśli `duplicateCheck` wskazuje `custom:erp_id`, to `customField['erp_id']`
MUSI mieć wartość w tym samym payloadzie - inaczej SDK rzuca
`IncompleteDuplicateCheckException` przed wysłaniem (API pominęłoby warunek
i mogłoby założyć duplikat).

## Pułapki

- **`key` generuje CRM.** Nie zgadujesz go - odczytujesz z `list()` albo z wyniku
  `create()`. Etykieta (`name`) jest unikalna, klucz jest pochodną.
- **`editableBy` przy zakładaniu podawaj ZAWSZE.** Pole założone bez ACL przyjmuje
  odczyt, ale KAŻDY zapis wartości kończy się 422 - i definicji nie da się
  poprawić (trzeba założyć nowe pole).
- **`SELECT`/`MULTISELECT` wymagają `options`** w definicji; wyszukiwanie
  duplikatów po polu wyboru nie zadziała (tylko INT/STR/VARCHAR).
- **Pola wielowartościowe piszesz LISTĄ, nawet dla jednej wartości** (API >= 2.9.0).
  `MULTISELECT`, wielowartościowe `PRODUCT` i `SALESPIPELINE` przyjmują
  `['12', '15']`, a nie goły skalar - pojedynczą wartość też opakuj w listę.
  Na instancji < 2.9.0 lista wartości odpadnie z `FeatureNotSupportedException`
  (501) - nie ponawiaj, zaktualizuj CRM.
- **Typ `SALESPIPELINE` wymaga API >= 2.8.0.** Założenie definicji tego typu na
  starszej instancji nie przejdzie - sprawdź `$client->health()['version']`.
- **Pole FILE nie przez `customField`.** Zapis/odczyt pliku to osobne metody
  `*FieldFile()`, nie `customField` w PUT rekordu.
- **`custom:<key>` w `duplicateCheck` wymaga wartości** w `customField[<key>]`
  tego samego żądania (strażnik lokalny).
- **Filtr po wartości pustej** = `CustomFieldFilter::NotSet`, nie pusty string
  (patrz [../queries/README.md](../queries/README.md)).
