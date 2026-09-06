<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Pozycja zamówienia do zapisu. WYMAGANE `productId` ALBO `productSku` -
 * pozycja zawsze wskazuje istniejący produkt z katalogu (nieznane SKU = 422;
 * produkt najpierw przez `POST /v2/products`). `customName` tylko NADPISUJE
 * nazwę z katalogu - nie tworzy pozycji wolnej. Ilości i kwoty jako stringi
 * dziesiętne.
 */
final readonly class OrderProductInput implements Arrayable
{
    public function __construct(
        public ?int $productId = null,
        public ?string $productSku = null,
        public ?string $customName = null,
        public ?string $quantity = null,
        public ?string $price = null,
        public ?string $discount = null,
        public ?string $taxRate = null,
        public ?int $measureId = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'productId' => $this->productId,
            'productSku' => $this->productSku,
            'customName' => $this->customName,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'discount' => $this->discount,
            'taxRate' => $this->taxRate,
            'measureId' => $this->measureId,
        ]);
    }
}
