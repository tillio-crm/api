<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Pozycja zamówienia (odczyt). `price`/`quantity`/`taxRate`/`discount` to
 * STRINGI dziesiętne. `productId` null = pozycja z nazwą własną, bez powiązania
 * z katalogiem.
 */
final readonly class OrderProduct
{
    /**
     * @param array<string, mixed> $raw pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?int $productId,
        public ?string $name,
        public ?string $price,
        public ?string $quantity,
        public ?string $taxRate,
        public ?string $discount,
        public ?string $measure,
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
            productId: Cast::int($row['productId'] ?? null),
            name: Cast::string($row['name'] ?? null),
            price: Cast::string($row['price'] ?? null),
            quantity: Cast::string($row['quantity'] ?? null),
            taxRate: Cast::string($row['taxRate'] ?? null),
            discount: Cast::string($row['discount'] ?? null),
            measure: Cast::string($row['measure'] ?? null),
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
