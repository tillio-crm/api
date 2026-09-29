<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Opcja pola SELECT/MULTISELECT (odczyt) - element `CustomFieldUpdateResult::$options`.
 *
 * `value` to id opcji: TĘ wartość zapisujesz w `customField[<klucz>]` rekordu i podajesz
 * w filtrze listy `customField[<klucz>]=`, nie nazwę. Nazwa jest dla ludzi.
 */
final readonly class CustomFieldOption
{
    /**
     * @param int|null             $value id opcji - wartość do zapisu i filtra `customField`
     * @param string|null          $color kolor opcji w CRM; null = brak koloru
     * @param array<string, mixed> $raw   pełny rekord z API
     */
    public function __construct(
        public ?int $value,
        public string $name,
        public ?string $color,
        public array $raw = [],
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            value: Cast::int($row['value'] ?? null),
            name: Cast::requiredString($row['name'] ?? null),
            color: Cast::nonEmptyString($row['color'] ?? null),
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
