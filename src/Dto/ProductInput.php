<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Produkt do zapisu - named arguments, null = nie wysyłaj pola.
 * Przy tworzeniu API wymaga `name`. Kwoty (`price`, `taxRate`) jako stringi
 * dziesiętne (`"1999.90"`), nie floaty.
 *
 *     new ProductInput(name: 'Licencja PRO', sku: 'LIC-PRO', price: '499.00')
 */
final readonly class ProductInput implements Arrayable
{
    /**
     * @param string|null $createdAt     data utworzenia przy imporcie historycznym
     * @param int|null    $creatorUserId tylko przy tworzeniu (import historii)
     */
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?string $sku = null,
        public ?string $ean = null,
        public ?string $externalId = null,
        public ?int $groupId = null,
        public ?int $measureId = null,
        public ?string $price = null,
        public ?string $currency = null,
        public ?string $taxRate = null,
        public ?int $status = null,
        public ?string $createdAt = null,
        public ?int $creatorUserId = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'name' => $this->name,
            'description' => $this->description,
            'sku' => $this->sku,
            'ean' => $this->ean,
            'externalId' => $this->externalId,
            'groupId' => $this->groupId,
            'measureId' => $this->measureId,
            'price' => $this->price,
            'currency' => $this->currency,
            'taxRate' => $this->taxRate,
            'status' => $this->status,
            'createdAt' => $this->createdAt,
            'creatorUserId' => $this->creatorUserId,
        ]);
    }
}
