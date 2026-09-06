<?php

declare(strict_types=1);

namespace TillioCrm\Api;

/**
 * Produkcyjny zegar: realny czas i realny sen.
 */
final class SystemClock implements Clock
{
    public function now(): float
    {
        return microtime(true);
    }

    public function sleep(float $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        usleep((int) round($seconds * 1_000_000));
    }
}
