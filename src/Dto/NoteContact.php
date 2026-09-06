<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Kontakt przypięty do notatki (relacja wiele-do-wielu). Dostępne od wersji API 2.8.0.
 */
final readonly class NoteContact
{
    /**
     * @param array<string, mixed> $raw pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $fullName,
        public ?string $position,
        public ?string $email,
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
            firstName: Cast::string($row['firstName'] ?? null),
            lastName: Cast::string($row['lastName'] ?? null),
            fullName: Cast::string($row['fullName'] ?? null),
            position: Cast::string($row['position'] ?? null),
            email: Cast::string($row['email'] ?? null),
            phone: Cast::string($row['phone'] ?? null),
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
