<?php

declare(strict_types=1);

namespace TillioCrm\Api\Tests;

use PHPUnit\Framework\TestCase;
use TillioCrm\Api\RateLimiter;
use TillioCrm\Api\Tests\Support\FakeClock;

final class RateLimiterTest extends TestCase
{
    public function testBelowLimitDoesNotWait(): void
    {
        $clock = new FakeClock();
        $limiter = new RateLimiter([2 => 3], $clock);

        $limiter->await();
        $limiter->await();
        $limiter->await();

        self::assertSame([], $clock->sleeps);
    }

    public function testWaitsExactlyUntilWindowFreesUp(): void
    {
        $clock = new FakeClock(1000.0);
        $limiter = new RateLimiter([2 => 2], $clock);

        $limiter->await();          // t=1000.0
        $clock->advance(0.5);
        $limiter->await();          // t=1000.5 - okno pełne

        // Najstarsze żądanie wypada z okna o 1002.0, a jest 1000.5 → czekamy 1.5 s.
        $limiter->await();

        self::assertCount(1, $clock->sleeps);
        self::assertEqualsWithDelta(1.5, $clock->sleeps[0], 0.001);
    }

    public function testTightestOfMultipleWindowsWins(): void
    {
        $clock = new FakeClock(1000.0);
        // 2 żądania / 1 s ORAZ 3 żądania / 10 s - po trzech żądaniach blokuje szersze okno.
        $limiter = new RateLimiter([1 => 2, 10 => 3], $clock);

        $limiter->await();
        $limiter->await();
        $limiter->await(); // 1-sekundowe okno wymusiło czekanie
        $limiter->await(); // 10-sekundowe okno pełne - czekanie do wypadnięcia najstarszego

        // Ostatnie czekanie musiało sięgnąć szerokiego okna (rzędu sekund, nie milisekund).
        self::assertGreaterThan(5.0, max([0.0, ...$clock->sleeps]));
    }

    public function testNoWindowsMeansNoWaiting(): void
    {
        $clock = new FakeClock();
        $limiter = new RateLimiter([], $clock);

        for ($i = 0; $i < 100; $i++) {
            $limiter->await();
        }

        self::assertSame([], $clock->sleeps);
    }

    public function testSentInWindowCountsOnlyFreshRequests(): void
    {
        $clock = new FakeClock(1000.0);
        $limiter = new RateLimiter([3600 => 100], $clock);

        $limiter->await();
        $clock->advance(30.0);
        $limiter->await();

        self::assertSame(2, $limiter->sentInWindow(60));
        self::assertSame(1, $limiter->sentInWindow(10));
    }
}
