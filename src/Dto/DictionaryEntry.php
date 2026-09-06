<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Wpis słownika systemowego (odczyt) - wspólny kształt dla wszystkich prostych
 * słowników (statusy, źródła, typy, priorytety, branże, działy...). Pola spoza
 * danego słownika przychodzą jako null/false i zostają w `$raw`.
 */
final readonly class DictionaryEntry
{
    /**
     * @param array<string, mixed> $raw pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $name,
        public ?bool $active,
        public ?bool $isFinal,
        public ?string $color,
        public ?int $days,
        public ?bool $isDefault,
        public ?bool $isUnique,
        public ?int $order,
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
            active: Cast::bool($row['active'] ?? null),
            isFinal: Cast::bool($row['isFinal'] ?? null),
            color: Cast::string($row['color'] ?? null),
            days: Cast::int($row['days'] ?? null),
            isDefault: Cast::bool($row['isDefault'] ?? null),
            isUnique: Cast::bool($row['isUnique'] ?? null),
            order: Cast::int($row['order'] ?? null),
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
