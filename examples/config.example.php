<?php

declare(strict_types=1);

// Skopiuj ten plik do examples/config.php i uzupelnij danymi swojej instancji.
// config.php jest w .gitignore, bo zawiera sekret - nie trafia do repo.
//
// Tryb bezposredni (wlasny klucz API tenanta). Klucz w formacie "nazwa:klucz"
// to ten sam klucz co w API v1, wygenerowany w panelu Tillio.

return [
    'apiKey'       => 'MojaIntegracja:sekret',
    'tenantDomain' => 'firma.tillio.app',
    'tenantId'     => 'firma-abc123',

    // PHP CLI na Windowsie czesto nie ma skonfigurowanego curl.cainfo i kazde
    // zadanie HTTPS pada na weryfikacji TLS. Odkomentuj i wskaz plik CA bundle
    // (np. z Git for Windows). Weryfikacji nie da sie tym wylaczyc - tylko
    // wskazac wlasciwy zestaw zaufanych CA.
    // 'caFile' => 'C:/Program Files/Git/mingw64/etc/ssl/certs/ca-bundle.crt',
];
