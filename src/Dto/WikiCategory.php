<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Kategoria bazy wiki (odczyt).
 */
final readonly class WikiCategory
{
    /**
     * @param array<string, mixed> $raw pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?int $baseId,
        public ?string $name,
        public ?bool $archived,
        public ?int $order,
        public ?int $creatorUserId,
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
            baseId: Cast::int($row['baseId'] ?? null),
            name: Cast::string($row['name'] ?? null),
            archived: Cast::bool($row['archived'] ?? null),
            order: Cast::int($row['order'] ?? null),
            creatorUserId: Cast::int($row['creatorUserId'] ?? null),
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
