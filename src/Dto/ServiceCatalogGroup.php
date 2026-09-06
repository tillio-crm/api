<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Grupa katalogu usług (odczyt) - drzewo przez `parentId`.
 */
final readonly class ServiceCatalogGroup
{
    /**
     * @param array<string, mixed> $raw pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $name,
        public ?int $parentId,
        public ?string $color,
        public ?string $icon,
        public ?int $order,
        public ?bool $active,
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
            parentId: Cast::int($row['parentId'] ?? null),
            color: Cast::string($row['color'] ?? null),
            icon: Cast::string($row['icon'] ?? null),
            order: Cast::int($row['order'] ?? null),
            active: Cast::bool($row['active'] ?? null),
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
