<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Katalog DMS kontrahenta (odczyt).
 */
final readonly class DmsDirectory
{
    /**
     * @param array<string, mixed> $raw pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $name,
        public ?int $parentId,
        public ?int $sizeBytes,
        public ?int $creatorUserId,
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
            sizeBytes: Cast::int($row['sizeBytes'] ?? null),
            creatorUserId: Cast::int($row['creatorUserId'] ?? null),
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
