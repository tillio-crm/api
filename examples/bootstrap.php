<?php

declare(strict_types=1);

// Wspolny bootstrap przykladow: autoloader + konfiguracja + gotowy klient.
// Kazdy przyklad zaczyna sie od: $client = require __DIR__ . '/bootstrap.php';

// Autoloader: pierwsza istniejaca sciezka wygrywa.
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',   // klon repozytorium SDK
    __DIR__ . '/../../../autoload.php',    // paczka z Composera: vendor/tillio-crm/api/examples/
];
$autoload = null;
foreach ($autoloadPaths as $path) {
    if (is_file($path)) {
        $autoload = $path;
        break;
    }
}
if ($autoload === null) {
    fwrite(STDERR, "Nie znaleziono vendor/autoload.php. W klonie repozytorium SDK uruchom: composer install,"
        . " we wlasnym projekcie: composer require tillio-crm/api\n");
    exit(1);
}
require $autoload;

// Konfiguracja: examples/config.php obok tego pliku albo wlasny plik wskazany
// w zmiennej srodowiskowej TILLIO_EXAMPLES_CONFIG. Po instalacji Composerem
// trzymaj config we wlasnym projekcie, nie w vendor/ - composer update go usunie.
$configFile = getenv('TILLIO_EXAMPLES_CONFIG') ?: __DIR__ . '/config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "Brak pliku konfiguracji: {$configFile} - skopiuj config.example.php do config.php"
        . " (albo do wlasnego projektu i wskaz go w TILLIO_EXAMPLES_CONFIG) i uzupelnij dane.\n");
    exit(1);
}

return new TillioCrm\Api\TillioClient(require $configFile);
