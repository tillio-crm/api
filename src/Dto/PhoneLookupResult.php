<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Wynik wyszukania po numerze (kto dzwoni): kontakty i kontrahenci z tym
 * numerem. Numer sprowadzony do kanonu miedzynarodowego. Dostepne od wersji
 * API 2.10.0.
 */
final readonly class PhoneLookupResult
{
    /**
     * @param list<PhoneLookupContact>    $contacts    kontakty z tym numerem
     * @param list<PhoneLookupContractor> $contractors kontrahenci z tym numerem
     * @param array<string, mixed>        $raw         pelny rekord z API
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
                PhoneLookupContact::fromArray(...),
                Cast::rows($row['contacts'] ?? null),
            ),
            contractors: array_map(
                PhoneLookupContractor::fromArray(...),
                Cast::rows($row['contractors'] ?? null),
            ),
            raw: $row,
        );
    }

    /**
     * Pelny, surowy rekord z API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->raw;
    }
}
