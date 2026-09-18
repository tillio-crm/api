<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Użytkownik systemowy (odczyt): dane identyfikacyjne i SŁUŻBOWE kontaktowe.
 * Prywatny telefon i e-mail, hasła, tokeny i ustawienia 2FA nigdy nie wychodzą
 * przez API; konta techniczne (ghost) też nie.
 *
 * Ostatnie logowanie i aktywność liczy osobna trasa - {@see UserActivity}.
 */
final readonly class SystemUser
{
    /**
     * @param string|null          $email        LOGIN systemowy (unikalny w instancji) - to nie musi
     *                                           być ten sam adres co `contactEmail`
     * @param string|null          $jobTitle     stanowisko (od API 2.16.0; do 2.15.x pole zapisu
     *                                           nazywało się `position`)
     * @param string|null          $contactPhone służbowy telefon do kontaktu w formacie
     *                                           międzynarodowym (od API 2.16.0; do 2.15.x pole
     *                                           zapisu nazywało się `phone`)
     * @param string|null          $contactEmail służbowy adres e-mail do kontaktu (od API 2.16.0)
     * @param string|null          $gender       `male`, `female` albo `unspecified` (od API 2.16.0)
     * @param array<string, mixed> $raw          pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $email,
        public ?int $userStatusId,
        public ?string $jobTitle,
        public ?string $contactPhone,
        public ?string $contactEmail,
        public ?string $gender,
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
            jobTitle: Cast::string($row['jobTitle'] ?? null),
            contactPhone: Cast::string($row['contactPhone'] ?? null),
            contactEmail: Cast::string($row['contactEmail'] ?? null),
            gender: Cast::string($row['gender'] ?? null),
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
