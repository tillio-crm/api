<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Użytkownik systemowy (odczyt).
 */
final readonly class SystemUser
{
    /**
     * @param array<string, mixed> $raw pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $email,
        public ?int $userStatusId,
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
            email: Cast::string($row['email'] ?? null),
            userStatusId: Cast::int($row['userStatusId'] ?? null),
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
