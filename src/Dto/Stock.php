<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Stan magazynowy (odczyt) - read-only odzwierciedlenie ilości. NIE ma własnego
 * `id`: rekord identyfikuje para magazyn/produkt (dlatego pełne przebiegi stanów
 * NIE wymuszają `sort=id` - patrz `Stocks::iterate()`). `quantity` jako string
 * dziesiętny.
 */
final readonly class Stock
{
    /**
     * @param array<string, mixed> $raw pełny rekord z API
     */
    public function __construct(
        public int $warehouseId,
        public int $productId,
        public ?string $quantity,
        public array $raw = [],
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            warehouseId: Cast::requiredInt($row['warehouseId'] ?? null),
            productId: Cast::requiredInt($row['productId'] ?? null),
            quantity: Cast::string($row['quantity'] ?? null),
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
