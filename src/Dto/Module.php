<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Moduł instancji CRM (odczyt, `GET /v2/modules`). `id` jest STRINGIEM
 * (np. `tickets`); `accessUntil` - do kiedy instancja ma dostęp.
 */
final readonly class Module
{
    /**
     * @param array<string, mixed> $raw pełny rekord z API
     */
    public function __construct(
        public string $id,
        public ?string $name,
        public ?bool $active,
        public ?string $accessUntil,
        public array $raw = [],
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            id: Cast::requiredString($row['id'] ?? null),
            name: Cast::string($row['name'] ?? null),
            active: Cast::bool($row['active'] ?? null),
            accessUntil: Cast::string($row['accessUntil'] ?? null),
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
