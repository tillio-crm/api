<?php

declare(strict_types=1);

// Minimalny, dzialajacy przyklad: sprawdzenie konfiguracji, odczyt listy
// i jeden zapis. Uruchom z katalogu glownego projektu:
//
//     php examples/quickstart.php

use TillioCrm\Api\Dto\TaskInput;
use TillioCrm\Api\Exception\TillioApiException;

/** @var TillioCrm\Api\TillioClient $client */
$client = require __DIR__ . '/bootstrap.php';

try {
    // 1. Najtanszy test konfiguracji - kim jestesmy wg API (bez zadnego zapisu).
    $who = $client->whoami();
    printf("Polaczono. Klucz: %s, tenant: %s\n", $who['keyName'] ?? '?', $who['tenantId'] ?? '?');

    // 2. Odczyt: pierwsza strona kontrahentow. Page niesie tez metadane (total).
    $page = $client->contractors()->list(['limit' => 5]);
    printf("\nKontrahentow w bazie: %d. Pierwsza strona:\n", $page->total);
    foreach ($page as $contractor) {
        printf("  #%d %s (NIP %s)\n", $contractor->id, $contractor->name ?? '-', $contractor->taxId ?? '-');
    }

    // 3. Zapis: zadanie dla pierwszego uzytkownika z listy.
    //    W realnej integracji wykonawce rozwiazujesz z imienia i nazwiska -
    //    patrz docs/.ai/tasks/README.md (helper resolveUserId).
    $user = $client->users()->list(['limit' => 1])->first();
    if ($user === null) {
        echo "\nBrak uzytkownikow - pomijam przyklad zapisu.\n";
    } else {
        $result = $client->tasks()->create(new TaskInput(
            title: 'Zadanie testowe z przykladu SDK',
            assignedUserIds: [$user->id],   // wymagane i niepuste
        ));
        printf("\nUtworzono zadanie #%d dla %s %s\n", $result->id, $user->firstName ?? '', $user->lastName ?? '');
    }
} catch (TillioApiException $e) {
    // Wszystkie bledy SDK dziedzicza po TillioApiException - jeden catch lapie komplet.
    fwrite(STDERR, "\nBlad API: " . $e->getMessage() . "\n");
    exit(1);
}
