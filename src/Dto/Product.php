<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Produkt (odczyt). `price`/`taxRate` to STRINGI dziesiętne - patrz {@see Cast}.
 * UWAGA: produkty NIE mają pól niestandardowych w API (filtr `customField`
 * zwraca 400) - `customField` bywa pustą mapą; do integracji służy `externalId`.
 */
final readonly class Product
{
    /**
     * @param array<string, mixed> $customField wartości pól niestandardowych (zwykle puste)
     * @param array<string, mixed> $raw         pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $description,
        public ?string $sku,
        public ?string $ean,
        public ?string $externalId,
        public ?int $groupId,
        public ?string $groupName,
        public ?int $measureId,
        public ?string $measure,
        public ?string $price,
        public ?string $currency,
        public ?string $taxRate,
        public ?int $status,
        public ?string $createdAt,
        public ?string $updatedAt,
        public array $customField,
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
            description: Cast::string($row['description'] ?? null),
            sku: Cast::string($row['sku'] ?? null),
            ean: Cast::string($row['ean'] ?? null),
            externalId: Cast::string($row['externalId'] ?? null),
            groupId: Cast::int($row['groupId'] ?? null),
            groupName: Cast::string($row['groupName'] ?? null),
            measureId: Cast::int($row['measureId'] ?? null),
            measure: Cast::string($row['measure'] ?? null),
            price: Cast::string($row['price'] ?? null),
            currency: Cast::string($row['currency'] ?? null),
            taxRate: Cast::string($row['taxRate'] ?? null),
            status: Cast::int($row['status'] ?? null),
            createdAt: Cast::string($row['createdAt'] ?? null),
            updatedAt: Cast::string($row['updatedAt'] ?? null),
            customField: Cast::map($row['customField'] ?? null),
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
