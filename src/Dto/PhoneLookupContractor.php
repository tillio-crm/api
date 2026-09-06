<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Kontrahent znaleziony po numerze telefonu (element listy `contractors`
 * w wyniku wyszukania).
 */
final readonly class PhoneLookupContractor
{
    /**
     * @param array<string, mixed> $raw pelny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $phone,
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
            phone: Cast::string($row['phone'] ?? null),
            raw: $row,
        );
    }

    /**
     * Pelny, surowy rekord z API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->raw;
    }
}
