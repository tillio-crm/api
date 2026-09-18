<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Wynik wyszukania po adresie e-mail (kto pisze): kontakty i kontrahenci z tym
 * adresem. Adres sprowadzony do kanonu (małe litery, sam adres - forma
 * `Imię <adres>` jest rozpakowywana). Dostępne od wersji API 2.15.0.
 *
 * Oba zestawy to PEŁNE rekordy - dokładnie tego samego kształtu, co
 * `GET /v2/contacts/{id}` i `GET /v2/contractors/{id}` - więc mapujemy je na te
 * same DTO co reszta SDK; kształt odpowiedzi jest bliźniaczy z
 * {@see PhoneLookupResult}. Obcięcie listy do limitu 50 zostaje w `$raw`
 * (`truncated.contacts`, `truncated.contractors`).
 */
final readonly class EmailLookupResult
{
    /**
     * @param list<Contact>        $contacts    kontakty z tym adresem wśród swoich e-maili,
     *                                          aktywne pierwsze (max 50)
     * @param list<Contractor>     $contractors kontrahenci z tym adresem na kartotece (max 50)
     * @param array<string, mixed> $raw         pełny rekord z API
     */
    public function __construct(
        public ?string $email,
        public array $contacts,
        public array $contractors,
        public array $raw = [],
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            email: Cast::string($row['email'] ?? null),
            contacts: array_map(
                Contact::fromArray(...),
                Cast::rows($row['contacts'] ?? null),
            ),
            contractors: array_map(
                Contractor::fromArray(...),
                Cast::rows($row['contractors'] ?? null),
            ),
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
