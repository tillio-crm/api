<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Uprawnienie systemowe (odczyt, słownik `GET /v2/user/permissions`).
 */
final readonly class Permission
{
    /**
     * @param array<string, mixed> $raw pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $key,
        public ?string $name,
        public ?string $description,
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
            key: Cast::string($row['key'] ?? null),
            name: Cast::string($row['name'] ?? null),
            description: Cast::string($row['description'] ?? null),
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
