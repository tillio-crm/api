# Playbook: Uzytkownicy (users)

Realizacja polecen uzytkownika dotyczacych kont systemowych CRM: wyszukanie
pracownika, zalozenie nowego konta z rola i dzialem, przekazanie hasla
startowego. Pisane dla asystenta AI - zaklada wspolne wzorce z
[ai_integration.md](../ai_integration.md) (zwlaszcza `resolveUserId()`,
`findByName()`, `WriteResult` i "Zlota zasade: nie zgaduj id").

Ten zasob pelni dwie role. Po pierwsze jest zrodlem `userId` dla wszystkich
innych playbookow (wykonawcy zadan, prowadzacy, wlasciciele rekordow) - stad
`resolveUserId()` z ai_integration.md opiera sie wlasnie na `users()->list()`.
Po drugie pozwala zalozyc nowe konto, co jest operacja wrazliwa: jednorazowe
haslo i limit licencji (patrz Pulapki).

## Pola

Zapis konta: `UserInput` (`src/Dto/UserInput.php`). Wymagane przy tworzeniu:
`firstName`, `email`, `userStatusId`, `roleId`. Reszta opcjonalna (`null` =
nie wysylaj pola).

| pole | typ | po co (skad wziac id/referencje) |
|---|---|---|
| `firstName` | `?string` | imie pracownika; WYMAGANE przy tworzeniu |
| `lastName` | `?string` | nazwisko; opcjonalne, ale bez niego `resolveUserId()` innych playbookow nie rozpozna osoby |
| `email` | `?string` | adres logowania, unikalny w instancji; WYMAGANE przy tworzeniu; sluzy tez jako pewny klucz wyszukiwania |
| `position` | `?string` | stanowisko (opis tekstowy, nie slownik) |
| `phone` | `?string` | telefon sluzbowy |
| `gender` | `?string` | plec (wartosc tekstowa wg kontraktu instancji) |
| `userStatusId` | `?int` | status konta; WYMAGANE; id z `dictionaries()->userStatuses()`; status NIEAKTYWNY nie liczy sie do limitu licencji |
| `departmentId` | `?int` | dzial; id z `dictionaries()->userDepartments()` (dopasuj nazwe albo zaloz dzial) |
| `roleId` | `?int` | rola i uprawnienia; WYMAGANE; id z `dictionaries()->userRoles()` |

Odczyt listy i wyszukiwanie: `SystemUser` (`src/Dto/SystemUser.php`). Pola
istotne przy rozwiazywaniu osoby na id:

| pole | typ | po co |
|---|---|---|
| `id` | `int` | to jest szukany `userId` do innych playbookow |
| `firstName` | `?string` | dopasowanie imienia przy wielu trafieniach po nazwisku |
| `lastName` | `?string` | filtr `lastName` listy |
| `email` | `?string` | najpewniejszy klucz - unikalny, jednoznaczny |
| `userStatusId` | `?int` | pozwala odsiac konta nieaktywne (np. tylko czynni wykonawcy) |
| `raw` | `array` | pelny surowy rekord z API, gdy potrzebne pole spoza mapowania DTO |

Wynik tworzenia: `CreatedUser` (`src/Dto/CreatedUser.php`).

| pole | typ | po co |
|---|---|---|
| `user` | `SystemUser` | zalozone konto (`->user->id` to nowy `userId`) |
| `temporaryPassword` | `?string` | JEDNORAZOWE haslo startowe; wystepuje TYLKO w tej odpowiedzi, nie da sie go odczytac pozniej; przekaz od razu, nie loguj |
| `warnings` | `array` | ciche korekty i ostrzezenia (np. o kalendarzu) - pokaz uzytkownikowi, jesli niepuste |

## Model danych w skrocie

Konto tworzy sie przez `UserInput`, a `users()->create()` zwraca `CreatedUser`
(NIE `WriteResult` jak wiekszosc zapisow - patrz "Co zwracaja zapisy" w
ai_integration.md). Cztery pola sa obowiazkowe: `firstName`, `email`,
`userStatusId`, `roleId`. Trzy z nich to referencje, ktore trzeba najpierw
rozwiazac ze slownikow:

- `userStatusId` -> `dictionaries()->userStatuses()`,
- `roleId` -> `dictionaries()->userRoles()`,
- `departmentId` (opcjonalne) -> `dictionaries()->userDepartments()`.

Konsekwencja dla Ciebie: uzytkownik poda "zaloz konto Adamowi Nowakowi, rola
handlowiec, dzial sprzedaz", a Ty musisz zamienic "handlowiec" i "sprzedaz" na
id ze slownikow, zanim cokolwiek wyslesz. To ta sama zlota zasada co wszedzie:
nie zgaduj id.

## Mapowanie intencji uzytkownika na dane API

| Uzytkownik mowi | Potrzebujesz | Skad wziac |
|---|---|---|
| "znajdz konto Jana Kowalskiego" | `SystemUser` (`->id`) | `users()->list(['lastName' => 'Kowalski'])`, dopasuj imie; albo `email` gdy podany |
| "zaloz konto Adamowi Nowakowi, adam@przyklad.example" | `firstName`, `lastName`, `email` | wprost z polecenia |
| "rola handlowiec" | `roleId` | `findByName($client->dictionaries()->userRoles(), 'Handlowiec')` |
| "status aktywny" / "konto czynne" | `userStatusId` | `findByName($client->dictionaries()->userStatuses(), 'Aktywny')` |
| "dzial sprzedaz" | `departmentId` | `findByName($client->dictionaries()->userDepartments(), 'Sprzedaz')` |
| "tylko czynni pracownicy" | filtr `userStatusId` na liscie | najpierw id statusu "Aktywny", potem `users()->list(['userStatusId' => ...])` |
| "zaloz konto, ale jeszcze nieaktywne" | `userStatusId` statusu nieaktywnego | konto nieaktywne nie zajmuje licencji - patrz Pulapki |

## Scenariusz flagowy: zalozenie konta z rola i dzialem

Polecenie uzytkownika: *"Zaloz konto dla Adama Nowaka, e-mail
adam.nowak@przyklad.example, rola Handlowiec, dzial Sprzedaz, status Aktywny."*

Tok postepowania:

1. Wyluskaj dane osobowe (imie, nazwisko, e-mail) wprost z polecenia.
2. Rozwiaz KAZDA referencje slownikowa (rola, status, dzial) na id; brak
   dopasowania to powod do przerwania, nie do zgadywania.
3. Utworz konto JEDNYM wywolaniem (POST bez retry - patrz Pulapki).
4. Przekaz haslo startowe uzytkownikowi OD RAZU i pokaz ewentualne ostrzezenia.

```php
use TillioCrm\Api\Dto\UserInput;
use TillioCrm\Api\Exception\UserLimitReachedException;

// Krok 1: dane osobowe z polecenia.
$firstName = 'Adam';
$lastName  = 'Nowak';
$email     = 'adam.nowak@przyklad.example';

// Krok 2: rozwiaz referencje slownikowe. findByName() z ai_integration.md
// zwraca null przy braku dopasowania - wtedy dopytaj, nie wstawiaj losowego id.
$roleId       = findByName($client->dictionaries()->userRoles(), 'Handlowiec');
$statusId     = findByName($client->dictionaries()->userStatuses(), 'Aktywny');
$departmentId = findByName($client->dictionaries()->userDepartments(), 'Sprzedaz');

$missing = [];
if ($roleId === null) {
    $missing[] = 'rola "Handlowiec"';
}
if ($statusId === null) {
    $missing[] = 'status "Aktywny"';
}
if ($departmentId === null) {
    $missing[] = 'dzial "Sprzedaz"';
}
if ($missing !== []) {
    // Brakuje ktoregos slownika w tej instancji - oddaj liste do wyjasnienia.
    throw new RuntimeException('Nie rozwiazano: ' . implode(', ', $missing) . '. Dopytaj albo zaloz brakujacy wpis slownika.');
}

// Krok 3: utworzenie konta. POST bez retry - wywolujemy DOKLADNIE raz.
try {
    $created = $client->users()->create(new UserInput(
        firstName: $firstName,
        lastName: $lastName,
        email: $email,
        userStatusId: $statusId,
        departmentId: $departmentId,
        roleId: $roleId,
    ));
} catch (UserLimitReachedException $e) {
    // 409: brak wolnej licencji. Konto NIE powstalo. Nie ponawiaj - poinformuj
    // uzytkownika, ze trzeba zwolnic licencje albo zalozyc konto jako nieaktywne.
    throw new RuntimeException('Brak wolnej licencji w instancji - konto nie zostalo zalozone. ' . $e->getMessage());
}

// Krok 4: haslo startowe jest JEDNORAZOWE - przekaz je od razu, nie zapisuj w logach.
$password = $created->temporaryPassword;
echo "Zalozono konto #{$created->user->id} ({$email}). Haslo startowe: {$password}\n";
echo "System wymusi zmiane hasla przy pierwszym logowaniu.\n";

// Ostrzezenia (np. o kalendarzu) - pokaz, jesli sa.
foreach ($created->warnings as $key => $warning) {
    echo "Ostrzezenie ({$key}): " . (is_string($warning) ? $warning : json_encode($warning)) . "\n";
}
```

Co zwrocic uzytkownikowi: numer konta (`$created->user->id`), adres e-mail
(login) oraz haslo startowe - z zaznaczeniem, ze jest jednorazowe i wymusi
zmiane przy pierwszym logowaniu. Ostrzezenia z `->warnings` przekaz, jesli
niepuste.

## Warianty

### Znalezienie konta po nazwisku (na potrzeby innych playbookow)

To najczestsze uzycie tego zasobu - zamiana osoby na `userId`. Uzyj gotowego
`resolveUserId()` z ai_integration.md; tu sama koncowka listy:

```php
// Pewny wariant: uzytkownik podal e-mail (unikalny, jednoznaczny).
$user = $client->users()->list(['email' => 'jan.kowalski@przyklad.example', 'limit' => 1])->first();
if ($user === null) {
    throw new RuntimeException('Nie znaleziono konta o tym e-mailu - dopytaj albo zaloz konto.');
}
$userId = $user->id;
```

### Lista tylko czynnych pracownikow

```php
// Najpierw id statusu "Aktywny", potem filtr listy po nim.
$activeId = findByName($client->dictionaries()->userStatuses(), 'Aktywny');
if ($activeId === null) {
    throw new RuntimeException('Brak statusu "Aktywny" w slowniku - sprawdz nazwe w tej instancji.');
}

// iterate() przechodzi wszystkie strony (wymuszone sort=id), nie gubi rekordow.
foreach ($client->users()->iterate(['userStatusId' => $activeId]) as $user) {
    echo "{$user->id}: {$user->firstName} {$user->lastName} <{$user->email}>\n";
}
```

### Konto zakladane jako nieaktywne (bez zajmowania licencji)

```php
// Konta NIEAKTYWNE nie licza sie do limitu licencji - przydatne, gdy limit jest
// wyczerpany, a konto ma poczekac na aktywacje. Uzyj id statusu nieaktywnego.
$inactiveId = findByName($client->dictionaries()->userStatuses(), 'Nieaktywny');
$created = $client->users()->create(new UserInput(
    firstName: 'Adam',
    email: 'adam.nowak@przyklad.example',
    userStatusId: $inactiveId,
    roleId: 7,
));
```

### Podejrzenie slownikow rol, statusow i dzialow

```php
// Zanim zalozysz konto, mozesz pokazac uzytkownikowi dostepne role/statusy/dzialy.
foreach ($client->dictionaries()->userRoles() as $role) {
    echo "rola {$role->id}: {$role->name}\n";
}
foreach ($client->dictionaries()->userStatuses() as $status) {
    echo "status {$status->id}: {$status->name}\n";
}
```

## Pulapki

- **`create()` zwraca `CreatedUser`, nie `WriteResult`.** To wyjatek od reguly
  zapisow (patrz ai_integration.md). Haslo jest w `->temporaryPassword`, a nowy
  `userId` w `->user->id`.
- **Haslo startowe jest JEDNORAZOWE.** Wystepuje wylacznie w odpowiedzi
  `create()`. Nie da sie go odczytac zadna pozniejsza trasa. Jesli je zgubisz,
  jedyne wyjscie to reset przez administratora. Przekaz je uzytkownikowi od razu
  i nie zapisuj w logach.
- **POST bez retry - wywolaj dokladnie raz.** Transport celowo nie ponawia
  `POST /v2/users`, bo powtorka po timeoutcie, ktory w rzeczywistosci doszedl,
  to "login zajety" - a haslo z pierwszej (udanej) proby juz przepadlo. Po
  `TransportException` NIE ponawiaj na slepo: sprawdz listem
  (`users()->list(['email' => ...])`), czy konto jednak powstalo.
- **Limit licencji (`UserLimitReachedException`, 409).** Instancja bez wolnej
  licencji odrzuci zalozenie AKTYWNEGO konta. Konta zakladane jako NIEAKTYWNE
  nie licza sie do limitu - to obejscie, gdy trzeba zalozyc konto "na zapas".
  Nie ponawiaj po 409; poinformuj uzytkownika.
- **`email` jest unikalny.** Proba zalozenia konta na zajety adres to blad
  walidacji - najpierw sprawdz listem, czy konto juz nie istnieje.
- **Nie zgaduj `roleId`, `userStatusId`, `departmentId`.** Wszystkie trzy to id
  ze slownikow `dictionaries()`. `findByName()` zwraca null przy braku
  dopasowania - wtedy dopytaj albo zaloz brakujacy wpis, nie wstawiaj liczby
  z glowy.
- **Odczyt vs zapis.** `SystemUser` (odczyt) niesie okrojony zestaw pol; pelny
  rekord jest w `->raw`. `UserInput` (zapis) nie przyjmuje `id` ani pol
  ustawianych po stronie CRM.
