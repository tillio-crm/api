<?php

declare(strict_types=1);

namespace TillioCrm\Api;

/**
 * Własny limiter na oknach przesuwnych - pilnuje, żeby w ogóle nie dojść do 429.
 *
 * DLACZEGO nie "poczekamy na 429 i powtórzymy": 429 kosztuje pełny round-trip
 * i psuje statystyki po stronie v2, a przy pełnym skanie katalogu takich odbić
 * byłyby tysiące. Taniej jest przytrzymać wątek u siebie.
 *
 * Liczymy realne znaczniki czasu żądań (float) i czekamy DOKŁADNIE tyle, ile
 * trzeba, żeby najstarsze żądanie wypadło z okna - bez spania całymi sekundami
 * tam, gdzie wystarczy 200 ms.
 *
 * Limiter jest per instancja klienta (proces) - nie ma współdzielonego licznika
 * między workerami. To świadome: paczka ma zero zależności (bez bazy i Redisa),
 * a twardy limit i tak trzyma v2 po stronie serwera; nasze okna są od niego
 * ciaśniejsze właśnie dlatego, że tym samym kluczem potrafi jechać kilka
 * procesów naraz.
 */
final class RateLimiter
{
    /** @var list<float> znaczniki czasu wysłanych żądań, rosnąco */
    private array $timestamps = [];

    /**
     * @param array<int, int> $limits okno w sekundach => maksymalna liczba żądań w tym oknie
     */
    public function __construct(
        private readonly array $limits,
        private readonly Clock $clock,
    ) {
    }

    /**
     * Blokuje, dopóki wysłanie kolejnego żądania nie zmieści się we WSZYSTKICH
     * oknach, po czym rejestruje je jako wysłane.
     */
    public function await(): void
    {
        if ($this->limits === []) {
            return;
        }

        // Pętla, a nie jedno wyliczenie: po odczekaniu na najciaśniejsze okno może
        // się okazać, że blokuje jeszcze szersze. Warunek stopu jest naturalny -
        // każde przejście zwalnia co najmniej jeden slot.
        while (true) {
            $now = $this->clock->now();
            $this->prune($now);

            $wait = $this->waitFor($now);
            if ($wait <= 0.0) {
                break;
            }

            $this->clock->sleep($wait);
        }

        $this->timestamps[] = $this->clock->now();
    }

    /**
     * Ile żądań klient wysłał w ostatnich `$window` sekundach (diagnostyka do logu).
     */
    public function sentInWindow(int $window): int
    {
        $threshold = $this->clock->now() - $window;

        return count(array_filter($this->timestamps, static fn (float $ts): bool => $ts > $threshold));
    }

    /** Ile trzeba odczekać, żeby zmieścić kolejne żądanie (0 = można wysyłać). */
    private function waitFor(float $now): float
    {
        $wait = 0.0;

        foreach ($this->limits as $window => $limit) {
            if ($limit <= 0) {
                continue;
            }

            $inWindow = [];
            foreach ($this->timestamps as $timestamp) {
                if ($timestamp > $now - $window) {
                    $inWindow[] = $timestamp;
                }
            }

            if (count($inWindow) < $limit) {
                continue;
            }

            // Żeby zwolnić slot, z okna musi wypaść tyle najstarszych żądań, o ile
            // przekraczamy limit - czekamy, aż wypadnie ostatnie z nich.
            $index = count($inWindow) - $limit;
            $freeAt = $inWindow[$index] + $window;
            $wait = max($wait, $freeAt - $now);
        }

        return $wait;
    }

    /** Znaczniki starsze niż najszersze okno nikogo już nie blokują. */
    private function prune(float $now): void
    {
        if ($this->limits === []) {
            return;
        }

        $widest = max(array_keys($this->limits));
        $threshold = $now - $widest;

        $kept = [];
        foreach ($this->timestamps as $timestamp) {
            if ($timestamp > $threshold) {
                $kept[] = $timestamp;
            }
        }

        $this->timestamps = $kept;
    }
}
