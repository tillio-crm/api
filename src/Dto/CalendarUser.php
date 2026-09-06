<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Dostęp użytkownika do kalendarza (pozycja listy `users` w {@see Calendar}).
 *
 * `main = true` oznacza GŁÓWNY kalendarz tego użytkownika - czyli ten,
 * w którym wydarzenia zakłada mu CRM. To odpowiedź na pytanie "gdzie wpisać
 * spotkanie tego handlowca".
 */
final readonly class CalendarUser
{
    /**
     * @param array<string, mixed> $raw pełny rekord z API
     */
    public function __construct(
        public int $userId,
        public ?bool $main,
        public ?int $admin,
        public ?string $accessTo,
        public array $raw = [],
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            userId: Cast::requiredInt($row['userId'] ?? null),
            main: Cast::bool($row['main'] ?? null),
            admin: Cast::int($row['admin'] ?? null),
            accessTo: Cast::string($row['accessTo'] ?? null),
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
