<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Rola użytkownika (odczyt) - z listą kluczy uprawnień
 * (słownik: `Dictionaries::userPermissions()`).
 */
final readonly class UserRole
{
    /**
     * @param list<string>         $permissions klucze uprawnień (np. `contractor.canEdit`)
     * @param array<string, mixed> $raw         pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $description,
        public array $permissions,
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
            description: Cast::string($row['description'] ?? null),
            permissions: Cast::stringList($row['permissions'] ?? null),
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
