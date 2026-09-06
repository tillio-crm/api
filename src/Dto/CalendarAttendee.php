<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Uczestnik albo organizator wydarzenia kalendarza.
 *
 * @see CalendarEvent
 */
final readonly class CalendarAttendee
{
    /**
     * @param string|null          $status odpowiedź na zaproszenie
     *                                     (ACCEPTED, DECLINED, TENTATIVE, NEEDS-ACTION)
     * @param array<string, mixed> $raw    pełny rekord z API
     */
    public function __construct(
        public ?string $name,
        public ?string $email,
        public ?string $status,
        public array $raw = [],
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            name: Cast::string($row['name'] ?? null),
            email: Cast::string($row['email'] ?? null),
            status: Cast::string($row['status'] ?? null),
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
