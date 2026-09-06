<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Znaleziony duplikat z `info.duplicate` odpowiedzi zapisu.
 *
 * `matchedBy` NIE jest domkniętym enumem: poza `taxId|phone|email|name|domain`
 * przychodzą klucze produktowe (`externalId`, `sku`, `ean`) i `custom:<klucz>`
 * dla pola niestandardowego - kod porównujący tę wartość musi przewidzieć
 * wariant z prefiksem `custom:`.
 */
final readonly class DuplicateMatch
{
    /**
     * @param string               $matchedBy pole, po którym znaleziono istniejący rekord
     * @param int|null             $id        id znalezionego rekordu (klucz zależny od encji)
     * @param array<string, mixed> $raw       pełny obiekt `info.duplicate`
     */
    public function __construct(
        public string $matchedBy,
        public ?int $id,
        public array $raw = [],
    ) {
    }

    /**
     * @param array<string, mixed> $duplicate obiekt `info.duplicate`
     *
     * @return self|null null, gdy odpowiedź nie niesie matchedBy (brak duplikatu)
     */
    public static function fromArray(array $duplicate): ?self
    {
        $matchedBy = Cast::nonEmptyString($duplicate['matchedBy'] ?? null);
        if ($matchedBy === null) {
            return null;
        }

        // Klucz id zależy od encji (contractorId, contactId, productId...) -
        // bierzemy pierwszą wartość liczbową spoza matchedBy.
        $id = null;
        foreach ($duplicate as $key => $value) {
            if ($key === 'matchedBy') {
                continue;
            }
            $id = Cast::int($value);
            if ($id !== null) {
                break;
            }
        }

        return new self($matchedBy, $id, $duplicate);
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
