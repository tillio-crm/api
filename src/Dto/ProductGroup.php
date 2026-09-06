<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Grupa produktów (odczyt) - drzewo przez `parentId`. Pełny payload w `$raw`.
 */
final readonly class ProductGroup
{
    /**
     * @param array<string, mixed> $raw pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?int $parentId,
        public ?string $name,
        public ?string $color,
        public ?int $priority,
        public ?int $status,
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
            parentId: Cast::int($row['parentId'] ?? null),
            name: Cast::string($row['name'] ?? null),
            color: Cast::string($row['color'] ?? null),
            priority: Cast::int($row['priority'] ?? null),
            status: Cast::int($row['status'] ?? null),
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
