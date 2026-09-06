<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Magazyn (odczyt). Pełny payload w `$raw`.
 */
final readonly class Warehouse
{
    /**
     * @param array<string, mixed> $raw pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $symbol,
        public ?int $status,
        public ?string $createdAt,
        public array $raw = [],
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            id: Cast::requiredInt($row['id'] ?? null),
            name: Cast::string($row['name'] ?? null),
            symbol: Cast::string($row['symbol'] ?? null),
            status: Cast::int($row['status'] ?? null),
            createdAt: Cast::string($row['createdAt'] ?? null),
            raw: $row,
        );
    }

    /**
     * Pełny, surowy rekord z API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->raw;
    }
}
