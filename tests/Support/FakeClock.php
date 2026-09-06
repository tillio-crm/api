<?php

declare(strict_types=1);

namespace TillioCrm\Api\Tests\Support;

use TillioCrm\Api\Clock;

/**
 * Zegar do testów: `sleep()` nie śpi, tylko przesuwa czas i nagrywa, ILE klient
 * by odczekał - testy limitera i backoffu sprawdzają wartości, nie realny czas.
 */
final class FakeClock implements Clock
{
    /** @var list<float> kolejne wywołania sleep() */
    public array $sleeps = [];

    public function __construct(private float $now = 1_000_000.0)
    {
    }

    public function now(): float
    {
        return $this->now;
    }

    public function sleep(float $seconds): void
    {
        $this->sleeps[] = $seconds;
        if ($seconds > 0) {
            $this->now += $seconds;
        }
    }

    /** Ręczne przesunięcie czasu (upływ między żądaniami). */
    public function advance(float $seconds): void
    {
        $this->now += $seconds;
    }
}
