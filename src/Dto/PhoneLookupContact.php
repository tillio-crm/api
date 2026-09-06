<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Kontakt znaleziony po numerze telefonu (element listy `contacts` w wyniku
 * wyszukania). Numer moze pasowac do pola glownego albo alternatywnego.
 */
final readonly class PhoneLookupContact
{
    /**
     * @param list<int>            $contractorIds identyfikatory kontrahentow powiazanych z kontaktem
     * @param array<string, mixed> $raw           pelny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $phone,
        public ?string $phoneAlternative,
        public ?int $contractorId,
        public array $contractorIds,
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
            firstName: Cast::string($row['firstName'] ?? null),
            lastName: Cast::string($row['lastName'] ?? null),
            phone: Cast::string($row['phone'] ?? null),
            phoneAlternative: Cast::string($row['phoneAlternative'] ?? null),
            contractorId: Cast::int($row['contractorId'] ?? null),
            contractorIds: Cast::intList($row['contractorIds'] ?? null),
            active: Cast::bool($row['active'] ?? null),
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
