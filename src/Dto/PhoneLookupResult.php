<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Wynik wyszukania po numerze (kto dzwoni): kontakty i kontrahenci z tym
 * numerem. Numer sprowadzony do kanonu międzynarodowego. Dostępne od wersji
 * API 2.10.0.
 *
 * Od API 2.12.0 endpoint oddaje PEŁNE rekordy - dokładnie tego samego kształtu,
 * co `GET /v2/contacts/{id}` i `GET /v2/contractors/{id}` - więc mapujemy je na
 * te same DTO co reszta SDK. Przy identyfikacji dzwoniącego masz od razu e-mail,
 * stanowisko, opiekuna i pola niestandardowe, bez dopytywania o kartotekę.
 */
final readonly class PhoneLookupResult
{
    /**
     * @param list<Contact>        $contacts    kontakty z tym numerem w polu głównym albo
     *                                          alternatywnym, aktywne pierwsze (max 50)
     * @param list<Contractor>     $contractors kontrahenci z tym numerem na kartotece (max 50)
     * @param array<string, mixed> $raw         pełny rekord z API
     */
    public function __construct(
        public ?string $number,
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
            number: Cast::string($row['number'] ?? null),
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
