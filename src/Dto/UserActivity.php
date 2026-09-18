<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Aktywność użytkownika systemu (odczyt): ostatnie logowanie, ostatnia czynność
 * i licznik logowań, policzone ze WSZYSTKICH jego sesji w dzienniku CRM.
 * Dostępne od wersji API 2.16.0.
 *
 * Agregaty żyją na osobnej trasie (`GET /v2/users/activity`), żeby lista
 * użytkowników została lekka - nie szukaj tych pól w {@see SystemUser}.
 */
final readonly class UserActivity
{
    /**
     * @param string|null          $lastLoginAt    ostatnie logowanie (ISO 8601, strefa instancji);
     *                                             null = użytkownik nigdy się nie logował
     * @param string|null          $lastActivityAt ostatnia czynność w którejkolwiek sesji;
     *                                             null = jak wyżej
     * @param int|null             $loginCount     liczba logowań od początku dziennika (0 = nigdy)
     * @param array<string, mixed> $raw            pełny rekord z API
     */
    public function __construct(
        public int $userId,
        public ?string $lastLoginAt,
        public ?string $lastActivityAt,
        public ?int $loginCount,
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
            lastLoginAt: Cast::string($row['lastLoginAt'] ?? null),
            lastActivityAt: Cast::string($row['lastActivityAt'] ?? null),
            loginCount: Cast::int($row['loginCount'] ?? null),
            raw: $row,
        );
    }

    /** Czy użytkownik kiedykolwiek się zalogował. */
    public function hasEverLoggedIn(): bool
    {
        return $this->lastLoginAt !== null;
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
