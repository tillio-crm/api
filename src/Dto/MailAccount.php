<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Konto pocztowe dostępne do wysyłki (odczyt, `GET /v2/mail/accounts`).
 */
final readonly class MailAccount
{
    /**
     * @param array<string, mixed> $raw pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $email,
        public ?string $name,
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
            email: Cast::string($row['email'] ?? null),
            name: Cast::string($row['name'] ?? null),
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
