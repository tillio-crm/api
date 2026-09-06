<?php

declare(strict_types=1);

// Wspolny bootstrap przykladow: autoloader + konfiguracja + gotowy klient.
// Kazdy przyklad zaczyna sie od: $client = require __DIR__ . '/bootstrap.php';

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Najpierw zainstaluj zaleznosci: composer install\n");
    exit(1);
}
require $autoload;

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "Brak examples/config.php - skopiuj config.example.php do config.php i uzupelnij dane.\n");
    exit(1);
}

return new TillioCrm\Api\TillioClient(require $configFile);
