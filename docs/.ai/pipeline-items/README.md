# Playbook: Szanse sprzedaży (pipeline-items)

Realizacja poleceń użytkownika dotyczących szans sprzedaży (pozycji w lejkach):
zakładanie szansy dla kontrahenta, kwota i waluta, lejek i etap, opiekun,
prawdopodobieństwo, odczyt i filtry. Pisane dla asystenta AI - zakłada wspólne
wzorce z [ai_integration.md](../ai_integration.md) (zwłaszcza `resolveUserId()`,
`findByName()`, opis `WriteResult` i "Złotą zasadę: nie zgaduj id").

## Pola

Szansę zapisuje się przez `PipelineItemInput` (named arguments, `null` = nie
wysyłaj pola). Źródło prawdy: `src/Dto/PipelineItemInput.php`. Poniżej KAŻDE pole
zapisu i po co jest.

| pole | typ | po co (i skąd wziąć wartość) |
|---|---|---|
| `name` | string | nazwa szansy - WYMAGANE przy tworzeniu; krótki opis, np. "Wdrozenie CRM - Acme" |
| `pipelineStageId` | int | ETAP w lejku - WYMAGANE przy tworzeniu; z `dictionaries()->pipelineFunnels()`, wybierz lejek i weź `->id` jego etapu (`->stages[]->id`), nie id lejka. Ustawiane TYLKO przy tworzeniu |
| `contractorId` | int | kartoteka kontrahenta, którego dotyczy szansa - WYMAGANE przy tworzeniu; z `contractors()->list(['name' => ...])`. Ustawiane TYLKO przy tworzeniu |
| `note` | string | opis/notatka szansy |
| `amount` | string | wartość szansy jako string dziesiętny, np. `'12000.00'` (NIE float - patrz Pułapki) |
| `currency` | string | waluta kwoty, np. `'PLN'` |
| `closeDate` | string | planowana data zamknięcia (ISO 8601) |
| `probability` | int | prawdopodobieństwo wygranej w procentach (0-100); często wynika z etapu lejka, można nadpisać |
| `ownerUserId` | int | opiekun szansy; z `resolveUserId($client, imie, nazwisko)` |
| `pipelineStatusId` | int | status szansy w ramach lejka; ze słownika/etapów lejka. Ustawiane TYLKO przy tworzeniu |
| `customField` | array<string,mixed> | wartości pól niestandardowych, mapa `klucz => wartosc`; klucze z `customFields()` |
| `createdAt` | string | data utworzenia przy imporcie historycznym (ISO 8601); pomiń dla bieżących szans |
| `creatorUserId` | int | autor przy imporcie historycznym; z `resolveUserId()`. Ustawiane TYLKO przy tworzeniu |

Odczyt (`PipelineItem`, `src/Dto/PipelineItem.php`) niesie pola, których
`PipelineItemInput` NIE przyjmuje - ustawia je CRM. Najważniejsze różnice:

| pole odczytu | typ | znaczenie |
|---|---|---|
| `closerUserId` | ?int | kto zamknął szansę |
| `realCloseDate` | ?string | faktyczna data zamknięcia (vs planowana `closeDate`) |
| `lostReason` | ?string | powód przegranej |
| `externalId` | ?string | identyfikator z systemu zewnętrznego |
| `leadId` | ?int | lead, z którego powstała szansa (patrz playbook leads) |
| `updatedAt` | ?string | znacznik ostatniej zmiany |

## Model danych w skrócie

Szansa tworzy się przez `PipelineItemInput`. Przy tworzeniu API WYMAGA trzech pól:

- `name` (string) - nazwa szansy,
- `pipelineStageId` (int) - etap w lejku,
- `contractorId` (int) - kartoteka kontrahenta.

Konsekwencja dla Ciebie: użytkownik poda nazwę firmy i nazwę lejka/etapu, nie id.
Twoje dwa pierwsze kroki to zawsze zamiana nazwy kontrahenta na `contractorId`
oraz nazwy etapu (w wybranym lejku) na `pipelineStageId`.

Metody zasobu: `create(PipelineItemInput)`, `update(int $id, PipelineItemInput)`,
`get(int $id)`, `list(array $filters)`, `iterate(array $filters)`. NIE ma metody
`upsert` - szansa nie ma wbudowanego wykrywania duplikatów.

## Mapowanie intencji użytkownika na dane API

| Użytkownik mówi | Potrzebujesz | Skąd wziąć |
|---|---|---|
| "szansa 'Wdrozenie CRM'" | `name` | tekst polecenia |
| "dla kontrahenta Acme" | `contractorId` | `contractors()->list(['name' => 'Acme', 'limit' => 1])->first()` |
| "w lejku Sprzedaz, etap Oferta" | `pipelineStageId` | `pipelineFunnels()`, wybierz lejek "Sprzedaz", w nim etap "Oferta" -> `->id` etapu |
| "wartość 12 tys. zł" | `amount`, `currency` | `amount: '12000.00'` (string), `currency: 'PLN'` |
| "opiekun Anna Nowak" | `ownerUserId` | `resolveUserId($client, 'Anna', 'Nowak')` |
| "zamknięcie do końca września" | `closeDate` | policz datę, sformatuj ISO 8601 (`DATE_ATOM`) |
| "szansa 60% pewna" | `probability` | `probability: 60` (0-100) |

## Scenariusz flagowy: szansa dla kontrahenta w konkretnym lejku

Polecenie użytkownika: *"Załóż szansę 'Wdrozenie CRM - Acme' dla kontrahenta
Acme, lejek 'Sprzedaz', etap 'Oferta', wartość 12000 PLN, opiekun Anna Nowak."*

Twój tok postępowania:

1. Wyłuskaj z polecenia: nazwę szansy, kontrahenta, lejek, etap, kwotę, opiekuna.
2. Rozwiąż kontrahenta na `contractorId` (odpytując `contractors()`); przy braku
   trafienia PRZERWIJ i dopytaj albo zaproponuj założenie kartoteki.
3. Znajdź lejek po nazwie, a w nim etap po nazwie -> `pipelineStageId`; przy braku
   dopasowania dopytaj, nie zgaduj id.
4. Rozwiąż opiekuna na `ownerUserId`.
5. Utwórz szansę (kwota jako string dziesiętny) i zwróć potwierdzenie z id.

```php
use TillioCrm\Api\Dto\PipelineItemInput;

// Krok 1: dane z polecenia (Ty je wyłuskujesz z tekstu użytkownika).
$name       = 'Wdrozenie CRM - Acme';
$funnelName = 'Sprzedaz';
$stageName  = 'Oferta';
$amount     = '12000.00';   // string dziesiętny, NIE float
$currency   = 'PLN';

// Krok 2: kontrahent - rozwiąż nazwę na id, nie zgaduj.
$contractor = $client->contractors()->list(['name' => 'Acme', 'limit' => 1])->first();
if ($contractor === null) {
    // Bez kartoteki nie ma szansy - dopytaj albo zaproponuj zalozenie kontrahenta.
    throw new RuntimeException('Nie znaleziono kontrahenta Acme - dopytaj albo zaloz kartoteke.');
}

// Krok 3: lejek i etap. pipelineFunnels() zwraca lejki {id, name, stages[...]};
// wybieramy lejek po nazwie, a w nim ETAP po nazwie. pipelineStageId to id ETAPU
// (stage->id), a nie id lejka.
$pipelineStageId = null;
foreach ($client->dictionaries()->pipelineFunnels() as $funnel) {
    if (mb_strtolower((string) $funnel->name) !== mb_strtolower($funnelName)) {
        continue;
    }
    foreach ($funnel->stages as $stage) {
        if (mb_strtolower((string) $stage->name) === mb_strtolower($stageName)) {
            $pipelineStageId = $stage->id;
            break 2;
        }
    }
}
if ($pipelineStageId === null) {
    // Lejek albo etap spoza słownika tej instancji - dopytaj, nie wstawiaj losowego id.
    throw new RuntimeException("Nie znaleziono etapu '{$stageName}' w lejku '{$funnelName}' - dopytaj uzytkownika.");
}

// Krok 4: opiekun szansy.
$ownerUserId = resolveUserId($client, 'Anna', 'Nowak');

// Krok 5: utworzenie szansy. Wymagane name + pipelineStageId + contractorId - mamy je.
// create() zwraca WriteResult: ->id (id szansy), ->created, ->warnings.
$result = $client->pipelineItems()->create(new PipelineItemInput(
    name:            $name,
    pipelineStageId: $pipelineStageId,
    contractorId:    $contractor->id,
    amount:          $amount,
    currency:        $currency,
    ownerUserId:     $ownerUserId,
));

// Potwierdzenie dla użytkownika:
echo "Utworzono szanse #{$result->id}: {$name} ({$amount} {$currency}).\n";
```

Co zwrócić użytkownikowi: numer szansy (`$result->id`), nazwę, kwotę i etap.
`WriteResult` niesie też `->warnings` (ciche korekty normalizacji) - jeśli
niepuste, pokaż je użytkownikowi.

## Warianty

### Odczyt i filtrowanie listy

```php
// Szanse jednego kontrahenta - filtry wg kontraktu zasobu PipelineItems.
$page = $client->pipelineItems()->list([
    'contractorId' => 42,
    'limit'        => 50,
]);
foreach ($page as $item) {
    echo "#{$item->id} {$item->name} ({$item->amount} {$item->currency})\n";
}
```

Dostępne filtry (komplet wg kontraktu): `contractorId`, `pipelineStageId`,
`ownerUserId`, `name`, `updatedAfter`/`updatedBefore`,
`createdAfter`/`createdBefore`, `customField[klucz]`, `sort`/`sortDir`,
`page`/`limit`. Do pełnego przebiegu wszystkich stron użyj `iterate()`
(wymusza `sort=id` - patrz [queries](../queries/README.md)).

### Przesunięcie szansy na inny etap

```php
// update() wysyła tylko podane pola. Uwaga: etap/status szansy zmienia proces
// lejka po stronie CRM; kontrakt oznacza pipelineStageId jako pole tworzenia.
// Do zwykłej korekty danych (kwota, opiekun) używaj update() normalnie:
$client->pipelineItems()->update(7, new PipelineItemInput(
    amount:      '15000.00',
    ownerUserId: resolveUserId($client, 'Piotr', 'Wisniewski'),
));
```

### Powiązania: jak inne encje wskazują szansę

Ta sama szansa jest adresowana różnymi nazwami pól w innych zasobach (to kontrakt,
nie literówka):

- `pipelineItemId` w `TaskInput` (zadanie powiązane z szansą),
- `pipelineId` w notatce (`Note`),
- `salesPipelineId` w leadzie (`Lead`) i dokumencie (`GeneratedDocument`).

Wszystkie znaczą id pozycji lejka, czyli `PipelineItem->id`.

## Pułapki

- **Trzy pola wymagane przy tworzeniu.** Brak `name`, `pipelineStageId` albo
  `contractorId` = 422. Rozwiąż kontrahenta i etap ZANIM wywołasz `create()`.
- **`pipelineStageId` to ETAP, nie lejek.** `pipelineFunnels()` zwraca lejki
  z zagnieżdżonymi etapami (`->stages`); wysyłasz id konkretnego etapu
  (`stage->id`), nie id lejka. Podanie id lejka da 422 albo trafienie w zły etap.
- **`amount` jako string dziesiętny.** Wysyłaj `'12000.00'`, nie `12000` ani
  `12000.0` jako float - kwoty jadą i wracają jako stringi, żeby nie tracić
  groszy na zaokrągleniach.
- **`pipelineStageId`/`contractorId`/`pipelineStatusId`/`creatorUserId` tylko
  przy tworzeniu.** Kontrakt oznacza je jako ustawiane w `create()`; etap/status
  potem zmienia proces lejka w CRM, nie ten zapis.
- **Brak upsert - brak wykrywania duplikatów.** Dwa `create()` dadzą dwie
  szanse. Chcesz uniknąć duplikatu - najpierw `list(['contractorId' => ..., 'name' => ...])`.
- **Nie zgaduj `contractorId` ani `ownerUserId`.** Rozwiąż nazwę na id przez
  odczyt; przy zerze/wielu trafieniach dopytaj, nie wybieraj pierwszego z brzegu.
- **Odczyt vs zapis.** `PipelineItem` (odczyt) ma pola nieobecne w input
  (`closerUserId`, `realCloseDate`, `lostReason`, `externalId`, `leadId`,
  `updatedAt`) - ustawia je CRM.
- **`create()` zwraca `WriteResult`** (`->id`, `->created`, `->warnings`), nie
  samo id. Szczegóły: sekcja "Co zwracają zapisy" w
  [ai_integration.md](../ai_integration.md).
