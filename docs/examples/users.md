# Użytkownicy systemowi

```php
use TillioCrm\Api\Dto\UserInput;
use TillioCrm\Api\Exception\TransportException;
use TillioCrm\Api\Exception\UserLimitReachedException;

// Odczyt
$page = $client->users()->list(['limit' => 100]);
foreach ($page as $user) {
    $user->firstName;
    $user->email;
    $user->userStatusId;
}

// Pełny przebieg wszystkich stron (generator)
foreach ($client->users()->iterate() as $user) {
    $user->email;
}

// Tworzenie konta - PRZECZYTAJ ZANIM UŻYJESZ:
//  - odpowiedź niesie JEDNORAZOWE hasło startowe: nie da się go odczytać później,
//    przekaż użytkownikowi od razu, nie zapisuj w logach,
//  - żądanie NIE jest ponawiane (nawet po błędzie transportu) - powtórka po
//    timeoutcie, który w rzeczywistości doszedł, to "login zajęty", a hasło przepada,
//  - konta AKTYWNE liczą się do limitu licencji (409 user.limitReached);
//    przy imporcie historycznym zakładaj konta NIEAKTYWNE (userStatusId
//    ze słownika dictionaries()->userStatuses()).
try {
    $created = $client->users()->create(new UserInput(
        firstName: 'Jan',
        lastName: 'Przykładowy',
        email: 'jan@przyklad.example',
        userStatusId: 1,               // dictionaries()->userStatuses()
        roleId: 2,                     // dictionaries()->userRoles()
        departmentId: 3,               // dictionaries()->userDepartments()
    ));

    $created->user->id;
    $created->temporaryPassword;       // przekaż OD RAZU; system wymusi zmianę
    $created->warnings;                // np. ostrzeżenie o kalendarzu
} catch (UserLimitReachedException) {
    // brak wolnej licencji - stan biznesowy: zatrzymaj i pokaż człowiekowi
} catch (TransportException) {
    // żądanie mogło dojechać! Sprawdź listą, czy konto istnieje, zanim ponowisz.
}
```
