# Playbook: Integracje instancji (integrations)

Zarzadzanie integracja instancji z usluga zewnetrzna. Na razie jedna: Tillio
Calls (telefonia) - odczyt stanu, rejestracja i usuniecie. Pisane dla asystenta
AI - zaklada wspolne wzorce z [ai_integration.md](../ai_integration.md)
(zwlaszcza sekcje o obsludze bledow i typach zwrotnych). Wymaga API >= 2.11.0.

Zasob: `$client->integrations()`. Zarejestrowana integracja Tillio Calls jest
tym, co zaczyna dostarczac rekordy do [phone-calls](../phone-calls/README.md)
i [text-messages](../text-messages/README.md) oraz zasila
[lookup](../lookup/README.md).

## Model danych w skrocie

Trzy operacje na Tillio Calls:

- `tillioCalls(): TillioCallsIntegration` - odczyt stanu (`GET`),
- `registerTillioCalls(string $apiUrl, string $apiKey): TillioCallsIntegration`
  - rejestracja/aktualizacja (`PUT`, oba pola wymagane); zwraca stan po zapisie,
- `deleteTillioCalls(): void` - usuniecie integracji (`DELETE`), nic nie zwraca.

Najwazniejszy fakt bezpieczenstwa: **klucz API NIGDY nie wraca z odczytu.**
`tillioCalls()` mowi tylko, CZY klucz jest ustawiony (flaga `hasApiKey`), nigdy
jaki. Nie da sie go odczytac po zapisie - jesli uzytkownik go zgubil, trzeba
zarejestrowac na nowo.

`registerTillioCalls()` dziala jak upsert (`PUT`): wywolane, gdy integracja juz
istnieje, nadpisuje `apiUrl` i `apiKey`.

Poniewaz rejestracja przyjmuje dwa proste argumenty (nie DTO), sekcja "Pola"
opisuje te dwa argumenty wejscia oraz pola odczytu `TillioCallsIntegration`.

## Pola

Wejscie `registerTillioCalls($apiUrl, $apiKey)`:

| pole | typ | po co (skad wziac) |
|---|---|---|
| `apiUrl` | string | WYMAGANE. Adres API uslugi Tillio Calls, do ktorego CRM ma sie laczyc. Podaje go uzytkownik/administrator uslugi telefonii |
| `apiKey` | string | WYMAGANE. Klucz uwierzytelniajacy do tej uslugi. Podaje uzytkownik; po zapisie nigdy nie wraca z API - traktuj jak sekret |

Odczyt `TillioCallsIntegration`:

| pole | typ | po co |
|---|---|---|
| `registered` | ?bool | Czy integracja jest zarejestrowana w tej instancji. Pierwsza rzecz do sprawdzenia |
| `apiUrl` | ?string | Zapisany adres API uslugi (jawny, wraca z odczytu) |
| `hasApiKey` | ?bool | Czy klucz API jest ustawiony. Sam klucz NIGDY nie wraca - masz tylko te flage |
| `providerId` | ?int | Id dostawcy telefonii utworzonego/powiazanego w CRM przy rejestracji (odczyt) |
| `configId` | ?int | Id konfiguracji integracji w CRM (odczyt) |
| `raw` | array | Pelny surowy rekord z API |

Pelna lista: `src/Dto/TillioCallsIntegration.php`, `src/Resources/Integrations.php`.

## Mapowanie intencji uzytkownika na dane API

| Uzytkownik mowi | Potrzebujesz | Skad wziac |
|---|---|---|
| "czy telefonia jest podpieta" | `tillioCalls()->registered` | odczyt stanu |
| "czy jest ustawiony klucz" | `tillioCalls()->hasApiKey` | odczyt stanu (samego klucza nie dostaniesz) |
| "podepnij Tillio Calls" | `registerTillioCalls($apiUrl, $apiKey)` | oba parametry od uzytkownika/admina uslugi |
| "zmien klucz/adres telefonii" | `registerTillioCalls($apiUrl, $apiKey)` | PUT nadpisuje istniejaca konfiguracje |
| "odepnij telefonie" | `deleteTillioCalls()` | bez parametrow |

## Scenariusz flagowy: rejestracja Tillio Calls (idempotentnie)

Polecenie: *"Podepnij telefonie Tillio Calls: adres https://calls.example/api,
klucz podam osobno. Jesli juz jest podpieta, nie dubluj - tylko zaktualizuj."*

Tok postepowania:

1. Sprawdz aktualny stan (`tillioCalls()`), zeby wiedziec, co pokazac
   uzytkownikowi (nowa rejestracja czy nadpisanie istniejacej).
2. Zarejestruj przez `registerTillioCalls()` - `PUT` jest idempotentny, wiec
   dziala i na czysto, i jako aktualizacja.
3. Potwierdz na podstawie zwroconego stanu (`registered`, `hasApiKey`), nie
   zakladajac sukcesu w ciemno.

```php
<?php

use TillioCrm\Api\Exception\ServiceUnavailableException;
use TillioCrm\Api\Exception\ValidationException;

$apiUrl = 'https://calls.example/api';
$apiKey = 'secret-key-value';   // sekret od uzytkownika; nie loguj go

try {
    // Krok 1: stan przed zmiana - do sensownego komunikatu.
    $before = $client->integrations()->tillioCalls();
    $action = $before->registered === true ? 'zaktualizowano' : 'zarejestrowano';

    // Krok 2: rejestracja/nadpisanie. PUT - idempotentny upsert.
    $after = $client->integrations()->registerTillioCalls($apiUrl, $apiKey);

    // Krok 3: potwierdzenie z faktycznego stanu po zapisie.
    if ($after->registered === true && $after->hasApiKey === true) {
        echo "Telefonia Tillio Calls {$action}. Adres: {$after->apiUrl}.\n";
    } else {
        // Nietypowe - zapis przeszedl, ale stan nie potwierdza kompletu.
        echo "Uwaga: integracja zapisana, ale stan niepelny (registered="
            . var_export($after->registered, true)
            . ", hasApiKey=" . var_export($after->hasApiKey, true) . ").\n";
    }
} catch (ValidationException $e) {
    // Zle dane (np. pusty apiUrl/apiKey albo bledny adres) - popraw i powtorz.
    throw new RuntimeException('Odrzucono rejestracje telefonii: ' . $e->getMessage(), 0, $e);
} catch (ServiceUnavailableException $e) {
    // Modul telefonii wylaczony w tej instancji - nie ponawiaj, sprawdz modules().
    throw new RuntimeException('Modul telefonii niedostepny w tej instancji.', 0, $e);
}
```

Co zwrocic uzytkownikowi: czy to nowa rejestracja czy aktualizacja, zapisany
`apiUrl` oraz potwierdzenie, ze klucz jest ustawiony (`hasApiKey`). NIGDY nie
odczytuj i nie pokazuj samego klucza - i tak nie wraca z API.

## Warianty

### Sprawdzenie stanu przed dalsza praca

Zanim zaczniesz zapisywac rozmowy albo liczyc na lookup, upewnij sie, ze
integracja stoi:

```php
$state = $client->integrations()->tillioCalls();
if ($state->registered !== true) {
    echo "Telefonia niepodpieta - najpierw registerTillioCalls(...).\n";
} elseif ($state->hasApiKey !== true) {
    echo "Integracja jest, ale bez klucza - zarejestruj ponownie z apiKey.\n";
} else {
    echo "Telefonia dziala. Adres: {$state->apiUrl}.\n";
}
```

### Usuniecie integracji

`deleteTillioCalls()` nie zwraca nic (`void`). Sukces = brak wyjatku.

```php
$client->integrations()->deleteTillioCalls();
echo "Telefonia Tillio Calls odpieta.\n";
```

### Wymiana samego klucza

Nie ma osobnej metody "zmien klucz" - `PUT` nadpisuje calosc, wiec podaj oba
pola (aktualny `apiUrl` mozesz odczytac wczesniej, klucz musi podac uzytkownik,
bo stary nie wraca):

```php
$current = $client->integrations()->tillioCalls();
$client->integrations()->registerTillioCalls($current->apiUrl ?? $apiUrl, $newApiKey);
```

## Pulapki

- **Klucz API nigdy nie wraca.** Odczyt daje tylko `hasApiKey` (bool). Nie
  probuj go odczytac ani pokazac; jesli uzytkownik go zgubil - rejestracja od
  nowa z nowym kluczem.
- **`registerTillioCalls()` to `PUT` (upsert).** Wywolane przy istniejacej
  integracji nadpisuje `apiUrl` i `apiKey`. Nie ma osobnego "create" i "update".
- **Oba argumenty rejestracji sa wymagane.** Pusty `apiUrl` albo `apiKey` to
  `ValidationException` (400/422). Nie wysylaj polowicznie.
- **`deleteTillioCalls()` zwraca `void`.** Potwierdzeniem jest brak wyjatku,
  nie wartosc zwrotna.
- **Modul moze byc wylaczony.** `ServiceUnavailableException` (503) oznacza, ze
  telefonia jest wylaczona w tej instancji - nie ponawiaj, sprawdz
  `$client->modules()`.
- **Wymaga API >= 2.11.0.** Sam zasob telefonii (phone-calls, text-messages,
  lookup) dziala od 2.10.0, ale rejestracja integracji dopiero od 2.11.0. Na
  starszej instancji trasa nie istnieje (404).
- **Potwierdzaj ze zwroconego stanu.** Po `registerTillioCalls()` sprawdz
  `registered` i `hasApiKey` w odpowiedzi, zamiast zakladac sukces w ciemno.
