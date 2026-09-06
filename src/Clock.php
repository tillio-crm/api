<?php

declare(strict_types=1);

namespace TillioCrm\Api;

/**
 * Czas i czekanie za jednym interfejsem - po to, żeby testy limitera i backoffu
 * mogły sprawdzić ILE klient by odczekał, nie odczekując tego naprawdę.
 * (Sekundy jako float: okna limitera bywają sub-sekundowe.)
 */
interface Clock
{
    /**
     * Bieżący czas w sekundach (odpowiednik `microtime(true)`).
     */
    public function now(): float;

    /**
     * Blokuje wykonanie na podaną liczbę sekund.
     */
    public function sleep(float $seconds): void;
}
