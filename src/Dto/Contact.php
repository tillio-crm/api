<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Osoba kontaktowa (odczyt). `contractorIds` to WSZYSTKIE kartoteki, do których
 * osoba jest przypięta (`contractorId` - główna). Pełny payload w `$raw`.
 */
final readonly class Contact
{
    /**
     * @param list<int>            $contractorIds kartoteki, do których osoba jest przypięta
     * @param array<string, mixed> $customField   wartości pól niestandardowych
     * @param array<string, mixed> $raw           pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $name,
        public ?string $position,
        public ?string $email,
        public ?string $phone,
        public ?string $phoneAlternative,
        public ?string $note,
        public ?int $contactStatusId,
        public ?int $ownerUserId,
        public ?string $externalId,
        public ?int $contractorId,
        public array $contractorIds,
        public ?string $createdAt,
        public ?string $updatedAt,
        public ?string $lastActivityAt,
        public array $customField,
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
            name: Cast::string($row['name'] ?? null),
            position: Cast::string($row['position'] ?? null),
            email: Cast::string($row['email'] ?? null),
            phone: Cast::string($row['phone'] ?? null),
            phoneAlternative: Cast::string($row['phoneAlternative'] ?? null),
            note: Cast::string($row['note'] ?? null),
            contactStatusId: Cast::int($row['contactStatusId'] ?? null),
            ownerUserId: Cast::int($row['ownerUserId'] ?? null),
            externalId: Cast::string($row['externalId'] ?? null),
            contractorId: Cast::int($row['contractorId'] ?? null),
            contractorIds: Cast::intList($row['contractorIds'] ?? null),
            createdAt: Cast::string($row['createdAt'] ?? null),
            updatedAt: Cast::string($row['updatedAt'] ?? null),
            lastActivityAt: Cast::string($row['lastActivityAt'] ?? null),
            customField: Cast::map($row['customField'] ?? null),
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
