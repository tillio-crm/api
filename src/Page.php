<?php

declare(strict_types=1);

namespace TillioCrm\Api;

/**
 * Jedna strona listy + metadane stronicowania z koperty v2
 * (`{data, pagination: {page, limit, total, pages}}`).
 *
 * `hasNextPage()` jest tu po to, żeby żaden konsument nie musiał sam liczyć
 * `ceil(total/limit)` - to klasyczne miejsce na off-by-one kończące się urwanym
 * albo zapętlonym przebiegiem.
 *
 * Generyczna po typie rekordu: zasoby oddają `Page<Contractor>` z typowanymi DTO,
 * a surowe listy (np. `TillioClient::listPage()`) - `Page<array>`.
 *
 * @template T
 *
 * @implements \IteratorAggregate<int, T>
 */
final readonly class Page implements \IteratorAggregate, \Countable
{
    /**
     * @param list<T> $rows  rekordy tej strony
     * @param int     $page  numer strony (od 1)
     * @param int     $limit rozmiar strony
     * @param int     $total łączna liczba rekordów zapytania
     * @param int     $pages łączna liczba stron
     */
    public function __construct(
        public array $rows,
        public int $page,
        public int $limit,
        public int $total,
        public int $pages,
    ) {
    }

    /**
     * Buduje stronę surowych rekordów z odpowiedzi listującej v2.
     *
     * @return self<array<string, mixed>>
     */
    public static function fromResponse(ApiResponse $response): self
    {
        $pagination = $response->pagination();
        $rows = [];
        foreach ($response->data() as $row) {
            if (is_array($row)) {
                /** @var array<string, mixed> $row */
                $rows[] = $row;
            }
        }

        return new self(
            $rows,
            self::intValue($pagination['page'] ?? null, 1),
            self::intValue($pagination['limit'] ?? null, count($rows)),
            self::intValue($pagination['total'] ?? null, count($rows)),
            self::intValue($pagination['pages'] ?? null, 1),
        );
    }

    /**
     * Czy za tą stroną jest następna.
     */
    public function hasNextPage(): bool
    {
        return $this->page < $this->pages;
    }

    /**
     * Czy strona nie ma ani jednego rekordu.
     */
    public function isEmpty(): bool
    {
        return $this->rows === [];
    }

    /**
     * Pierwszy rekord strony (typowo przy szukaniu po kluczu z `limit=1`) albo null.
     *
     * @return T|null
     */
    public function first(): mixed
    {
        return $this->rows[0] ?? null;
    }

    public function count(): int
    {
        return count($this->rows);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->rows);
    }

    private static function intValue(mixed $value, int $default): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }
}
