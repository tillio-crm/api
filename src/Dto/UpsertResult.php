<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

use TillioCrm\Api\ApiResponse;

/**
 * Wynik batcha `POST /v2/<encja>/upsert`.
 *
 * PUŁAPKA KONTRAKTU: przy poprawnej kopercie HTTP jest ZAWSZE 200, nawet jeśli
 * wszystkie pozycje poległy - multi-status siedzi w `data.results[]` (w kolejności
 * wejścia). Kto sprawdza tylko kod HTTP, ten po cichu gubi rekordy. Ta klasa
 * wymusza zajrzenie do środka (`hasFailures()`, `failed()`).
 *
 * ZBIÓR STATUSÓW ZALEŻY OD ENCJI: kontrahenci raportują `created|updated|failed`,
 * kontakty `created|attached|failed` (trafienie w duplikat = PODPIĘCIE danych do
 * istniejącej osoby). Dlatego liczniki są generyczne (`countOf()`), a nazwane
 * skróty to wygoda - wspólny dla wszystkich jest tylko `failed`.
 */
final readonly class UpsertResult
{
    /**
     * @param list<array<string, mixed>> $results wyniki per pozycja, w kolejności wejścia
     *                                            (`{index, status, <encja>Id, matchedBy,
     *                                            warnings, errors}`)
     * @param array<string, int>         $summary `info.summary` (total/created/updated/failed)
     */
    public function __construct(
        public array $results,
        public array $summary,
    ) {
    }

    public static function fromResponse(ApiResponse $response): self
    {
        /** @var array<string, mixed> $data */
        $data = $response->data();
        $results = Cast::rows($data['results'] ?? null);

        $info = $response->info();
        $summary = [];
        foreach (Cast::map($info['summary'] ?? null) as $status => $count) {
            if (is_numeric($count)) {
                $summary[$status] = Cast::requiredInt($count);
            }
        }

        return new self(results: $results, summary: $summary);
    }

    /**
     * Pozycje, których v2 nie zapisało - każda z listą `errors` `{field, code, message}`.
     *
     * @return list<array<string, mixed>>
     */
    public function failed(): array
    {
        return $this->withStatus('failed');
    }

    /** Czy jakakolwiek pozycja poległa - sprawdzaj ZAWSZE, kod HTTP tego nie powie. */
    public function hasFailures(): bool
    {
        return $this->failed() !== [];
    }

    /** Ile rekordów utworzono. */
    public function createdCount(): int
    {
        return $this->countOf('created');
    }

    /** Ile istniejących rekordów zaktualizowano (kontrahenci). */
    public function updatedCount(): int
    {
        return $this->countOf('updated');
    }

    /** Kontakty: istniejąca osoba, do której DOPIĘTO dane (odpowiednik `updated`). */
    public function attachedCount(): int
    {
        return $this->countOf('attached');
    }

    /** Ile pozycji poległo. */
    public function failedCount(): int
    {
        return $this->summary['failed'] ?? count($this->failed());
    }

    /**
     * Licznik dowolnego statusu z `info.summary`, z policzeniem po wynikach, gdy
     * v2 podsumowania nie przysłało. Fallback jest istotny przy scalaniu paczek:
     * jedna pusta odpowiedź nie może wyzerować statystyki całego przebiegu.
     */
    public function countOf(string $status): int
    {
        return $this->summary[$status] ?? count($this->withStatus($status));
    }

    /**
     * Pozycje o podanym statusie.
     *
     * @return list<array<string, mixed>>
     */
    public function withStatus(string $status): array
    {
        return array_values(array_filter(
            $this->results,
            static fn (array $row): bool => ($row['status'] ?? '') === $status,
        ));
    }

    /**
     * Wynik pozycji po jej indeksie w wysłanej paczce.
     *
     * @return array<string, mixed>|null
     */
    public function forIndex(int $index): ?array
    {
        foreach ($this->results as $row) {
            if (is_numeric($row['index'] ?? null) && (int) $row['index'] === $index) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Przesuwa `index` o podaną wartość. Potrzebne przy dzieleniu na paczki:
     * v2 numeruje pozycje od 0 W KAŻDEJ paczce - bez przesunięcia scalony wynik
     * wskazywałby na złe rekordy wejściowe.
     */
    public function withIndexOffset(int $offset): self
    {
        $results = [];
        foreach ($this->results as $row) {
            if (isset($row['index']) && is_numeric($row['index'])) {
                $row['index'] = (int) $row['index'] + $offset;
            }
            $results[] = $row;
        }

        return new self($results, $this->summary);
    }

    /** Scala wyniki kolejnych paczek w jeden (upsert dzielony na porcje). */
    public function merge(self $next): self
    {
        $summary = $this->summary;
        foreach ($next->summary as $key => $value) {
            $summary[$key] = ($summary[$key] ?? 0) + $value;
        }

        return new self([...$this->results, ...$next->results], $summary);
    }
}
