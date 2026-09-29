# Wydajne i poprawne zapytania

Jak czytać dane z API tanio i bez gubienia rekordów. Te reguły obowiązują we
wszystkich playbookach domenowych - warto je znać, zanim zaczniesz odpytywać
listy. Zła strategia zapytań to albo błąd 400, albo pobranie całej bazy do
pamięci, albo cicha utrata rekordów.

## Zasada 1: filtruj po stronie API, nie w PHP

API v2 przyjmuje filtry w zapytaniu i zwraca tylko pasujące rekordy. NIE pobieraj
całej listy, żeby przefiltrować ją w PHP - to marnuje round-tripy i pamięć.

```php
// DOBRZE: API zwraca tylko kontrahentow z tym NIP.
$page = $client->contractors()->list(['taxId' => '0000000000', 'limit' => 1]);
$contractor = $page->first();

// ZLE: pobranie wszystkich i szukanie w PHP - kosztowne i wolne.
// foreach ($client->contractors()->iterate() as $c) { if ($c->taxId === ...) ... }
```

Nieznany filtr to błąd 400 (`ValidationException`), nie ciche zignorowanie -
literówka w nazwie filtra nie zwróci przypadkiem całej bazy. Jakie filtry
przyjmuje dany zasób, sprawdzisz w PHPDoc metody `list()` (dokumentuje komplet
wg kontraktu) albo w playbooku domeny.

## Zasada 2: jedna strona (`list`) kontra pełny przebieg (`iterate`)

- **`list($filters)`** zwraca JEDNĄ stronę jako `Page<T>` z metadanymi
  (`->total`, `->hasNextPage()`, `->first()`). Użyj, gdy potrzebujesz kilku
  rekordów albo szukasz konkretnego po kluczu.
- **`iterate($filters)`** to generator przechodzący WSZYSTKIE strony, oddając
  rekordy po jednym. Użyj do pełnego przetworzenia zbioru. Nie materializuj go
  w tablicę (`iterator_to_array`) - pełny przebieg to potrafi być dziesiątki
  tysięcy rekordów i skończy się wyczerpaniem pamięci.

```php
// Szukanie konkretnego rekordu: jedna strona, limit 1.
$user = $client->users()->list(['email' => 'jan.kowalski@przyklad.example', 'limit' => 1])->first();

// Przetworzenie calego zbioru: generator, rekord po rekordzie.
foreach ($client->contractors()->iterate(['contractorStatusId' => 3]) as $contractor) {
    // przetwarzaj pojedynczy rekord; nic nie zbieraj do tablicy bez potrzeby
}
```

## Zasada 3: nie nadpisuj `sort=id` przy pełnym przebiegu

`iterate()` domyślnie wymusza `sort=id` i to NIE jest kosmetyka. API stronicuje
przez LIMIT/OFFSET; przy domyślnym sortowaniu po dacie modyfikacji rekord
zmieniony w trakcie przebiegu przeskakuje w porządku i albo wypada z niepobranego
zakresu (cicha utrata), albo wraca drugi raz. `id` jest niezmienne, więc daje
porządek, którego nic w trakcie nie przestawi.

Nie podawaj własnego `sort` w filtrach `iterate()`, chyba że naprawdę musisz
i wiesz, że zbiór się nie zmienia w trakcie. Do jednej strony (`list()`) własny
`sort` jest bezpieczny.

## Zasada 4: dobierz `limit` do zadania

Każde żądanie to round-trip i slot w limiterze. Przy pełnym przebiegu SDK sam
używa dużej strony. Przy `list()` bierz tylko tyle, ile potrzebujesz: szukasz
jednego rekordu po kluczu - `'limit' => 1`. Nie pobieraj strony 1000 rekordów,
żeby użyć pierwszego.

## Zasada 5: filtry pól niestandardowych (`customField`)

Filtrowanie po polu niestandardowym podaje się zagnieżdżone pod `customField`:

```php
$page = $client->contractors()->list([
    'customField' => ['erp_id' => 'OPT-8123'],
]);
```

Pusta wartość filtra `customField` w kontrakcie znaczy "pole NIE ustawione" -
i jest pułapką (przypadkowy pusty string zwróciłby wszystkie rekordy bez wartości
i skleiłby różne podmioty). Dlatego SDK ODRZUCA pusty string wyjątkiem. Jeśli
naprawdę chcesz filtrować po braku wartości, użyj jawnego enuma:

```php
use TillioCrm\Api\CustomFieldFilter;

$page = $client->contractors()->list([
    'customField' => ['erp_id' => CustomFieldFilter::NotSet],   // rekordy bez wartosci
]);
```

## Zasada 6: daty w filtrach

Filtry dat (`updatedAfter`, `createdBefore`, ...) przyjmuj jako `DateTimeInterface`
albo string. SDK sformatuje `DateTimeInterface` poprawnie. Ręczny string API
sprawdza ściśle (od API 2.14.0) i przyjmuje wyłącznie:
`2026-08-20T10:00:00+02:00`, `2026-08-20T08:00:00Z`, `2026-08-20T08:00:00.250Z`,
`2026-08-20T10:00:00`, `2026-08-20 10:00:00` i `2026-08-20` (północ). Każdy inny
zapis, PUSTA wartość i nieistniejący dzień (np. 31 lutego) to 400
`query.invalidDate`. Wcześniej API po cichu brało "teraz", więc synchronizacja
przyrostowa gubiła rekordy bez żadnego błędu. Przy pierwszym przebiegu pomiń
filtr, zamiast wysyłać pusty `updatedAfter`. `page` i `limit` to liczby całkowite
(`page=2abc` = 400).

```php
$page = $client->contractors()->list([
    'updatedAfter' => new DateTimeImmutable('-7 days'),   // SDK sformatuje do ISO 8601
]);
```

## Zasada 7: zapisy zbiorcze zamiast pętli pojedynczych

Gdy masz do zapisania wiele rekordów tej samej encji, użyj upsertu paczki
(tam, gdzie zasób go ma - np. kontrahenci, kontakty), zamiast wołać `create()`
w pętli. Jedno żądanie zamiast N, wynik per pozycja w `UpsertResult`.

```php
$result = $client->contractors()->upsert($listaInputow, new WriteOptions(duplicateCheck: ['taxId']));
if ($result->hasFailures()) {
    // obsluz $result->failed() - reszta poszla
}
```

## Zasada 8: paczka id jednym zapytaniem (API >= 2.17.0)

Każdy filtr dokładny po liczbie całkowitej (`id`, `contractorId`, `leadId`,
`pipelineId`, `ownerUserId` i pozostałe `*Id` na wszystkich listach) przyjmuje
LISTĘ do 100 wartości. Podajesz tablicę - SDK wyśle ją jako `?id=12,15,18`.
Pojedyncza wartość działa jak dotąd.

```php
use TillioCrm\Api\QueryBuilder;

// Stan paczki znanych leadów i ich notatki - dwa zapytania zamiast 2 x N.
$leads = $client->leads()->list(['id' => [659, 660, 702], 'limit' => 100]);
$notes = $client->notes()->list(['leadId' => [659, 660, 702], 'limit' => 100]);

// Więcej niż 100 id: dziel na paczki.
foreach (array_chunk($ids, QueryBuilder::MAX_LIST_VALUES) as $chunk) {
    $page = $client->contractors()->list(['id' => $chunk, 'limit' => QueryBuilder::MAX_LIST_VALUES]);
}
```

**Wykrywanie usuniętych rekordów.** CRM kasuje leady i szanse bez śladu (nie ma
tabeli usuniętych ani filtra `deletedAfter`), więc usunięcie widać wyłącznie jako
BRAK rekordu. Id z paczki nieobecne w odpowiedzi = rekordu już nie ma. Pamiętaj
o `limit` co najmniej równym długości paczki - inaczej brak na pierwszej stronie
wziąłbyś za usunięcie.

SDK odrzuca lokalnie (`InvalidFilterException`, żądanie nie wychodzi): pustą listę
(bez filtra zapytanie zwróciłoby WSZYSTKIE rekordy), więcej niż 100 wartości
i element, który nie jest liczbą całkowitą ani tekstem bez przecinka. Filtr
`customField[...]` zostaje pojedynczą wartością. Instancja starsza niż 2.17.0
odrzuci listę błędem 400 `query.invalidValue`.

## Rate limiter jest wbudowany

Nie musisz sam pilnować limitów - SDK ma limiter okienkowy, który przytrzyma
żądanie, zanim dojdzie do 429, oraz retry na 429/5xx. Ale to nie znaczy, że
możesz strzelać bez sensu: zasady 1-8 wyżej redukują LICZBĘ żądań, a to jest
tańsze niż jakiekolwiek ponawianie. Nie odpytuj rekordów po jednym w pętli, jeśli
jeden filtr listy załatwia sprawę.

## Anti-wzorce (nie rób tak)

- Pobranie całej listy `iterate()` tylko po to, by znaleźć jeden rekord - użyj
  filtra i `list(['...' => ..., 'limit' => 1])->first()`.
- `iterator_to_array($resource->iterate())` na dużym zbiorze - materializacja
  dziesiątek tysięcy rekordów w pamięci.
- Własny `sort` w `iterate()` na zmieniającym się zbiorze - cicha utrata rekordów.
- Pętla `create()` po wielu rekordach, gdy zasób ma `upsert()`.
- Filtrowanie w PHP po pobraniu wszystkiego, zamiast filtra API.
- `get()` w pętli po znanych id (albo `list(['leadId' => $id])` na każdy rekord),
  gdy jedna lista z filtrem-listą id załatwia paczkę 100 rekordów (API >= 2.17.0).
- Buforowanie `downloadUrl` plików - podpisany link żyje ~1 minutę; pobieraj od
  razu po odczycie metadanych, po wygaśnięciu odczytaj metadane ponownie.
