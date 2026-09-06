<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Kalendarz użytkownika albo zespołu (odczyt). Rodzaj (Tillio, Microsoft,
 * Google) mapuje słownik `Dictionaries::calendarTypes()`.
 *
 * Lista `users` mówi, kto ma dostęp i czyj to kalendarz GŁÓWNY
 * ({@see CalendarUser}). Kalendarze spięte ze skrzynką niosą `mailAccountId`
 * (konto z `Mail::accounts()`), a synchronizowane z kontem zewnętrznym -
 * `oauthEmail` z flagą `oauthAuthorized` (false = kalendarz Tillio albo
 * wygasła zgoda).
 *
 * Dostępne od wersji API 2.2.0.
 */
final readonly class Calendar
{
    /**
     * @param list<CalendarUser>   $users użytkownicy z dostępem do kalendarza
     * @param array<string, mixed> $raw   pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $name,
        public ?int $calendarTypeId,
        public ?int $ownerUserId,
        public ?int $mailAccountId,
        public ?string $email,
        public ?string $oauthEmail,
        public ?bool $oauthAuthorized,
        public ?string $color,
        public ?bool $allowExternalEvents,
        public ?bool $active,
        public array $users,
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
            calendarTypeId: Cast::int($row['calendarTypeId'] ?? null),
            ownerUserId: Cast::int($row['ownerUserId'] ?? null),
            mailAccountId: Cast::int($row['mailAccountId'] ?? null),
            email: Cast::string($row['email'] ?? null),
            oauthEmail: Cast::string($row['oauthEmail'] ?? null),
            oauthAuthorized: Cast::bool($row['oauthAuthorized'] ?? null),
            color: Cast::string($row['color'] ?? null),
            allowExternalEvents: Cast::bool($row['allowExternalEvents'] ?? null),
            active: Cast::bool($row['active'] ?? null),
            users: array_map(CalendarUser::fromArray(...), Cast::rows($row['users'] ?? null)),
            raw: $row,
        );
    }

    /**
     * Główny kalendarz użytkownika o tym id? (flaga `main` na liście dostępów).
     */
    public function isMainFor(int $userId): bool
    {
        foreach ($this->users as $user) {
            if ($user->userId === $userId && $user->main === true) {
                return true;
            }
        }

        return false;
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
