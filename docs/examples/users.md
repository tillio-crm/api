# Użytkownicy systemowi

```php
use TillioCrm\Api\Dto\UserInput;
use TillioCrm\Api\Exception\TransportException;
use TillioCrm\Api\Exception\UserLimitReachedException;

// Odczyt. email to LOGIN; contactEmail to osobny służbowy adres kontaktowy.
$page = $client->users()->list(['limit' => 100]);
foreach ($page as $user) {
    $user->firstName;
    $user->email;
    $user->userStatusId;
    $user->jobTitle;        // stanowisko (API >= 2.16.0)
    $user->contactPhone;    // służbowy telefon (API >= 2.16.0)
    $user->contactEmail;    // służbowy e-mail kontaktowy (API >= 2.16.0)
    $user->gender;          // male | female | unspecified (API >= 2.16.0)
}

// Filtry: email i contactEmail dokładnie, jobTitle i contactPhone częściowo
$client->users()->list(['jobTitle' => 'Handlowiec']);

// Pełny przebieg wszystkich stron (generator)
foreach ($client->users()->iterate() as $user) {
    $user->email;
}

// Aktywność: ostatnie logowanie i ostatnia czynność (API >= 2.16.0).
// Osobna trasa - tych pól NIE ma w list(). Sortowanie rosnąco po lastActivityAt
// stawia na początku konta, które nigdy się nie logowały (null).
$page = $client->users()->activity(['sort' => 'lastActivityAt', 'sortDir' => 'asc']);
foreach ($client->users()->iterateActivity() as $activity) {
    $activity->userId;
    $activity->lastLoginAt;      // null = nigdy
    $activity->lastActivityAt;
    $activity->loginCount;
    $activity->hasEverLoggedIn();
}

// Jedno konto (konto techniczne i nieistniejące id = 404)
$activity = $client->users()->getActivity(12);

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
        email: 'jan@przyklad.example',  // LOGIN systemowy
        jobTitle: 'Opiekun klienta',    // do API 2.15.x pole nazywało się position
        contactPhone: '+48601234567',   // do API 2.15.x pole nazywało się phone
        contactEmail: 'kontakt@firma.example',
        gender: 'male',                 // male | female | unspecified
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
