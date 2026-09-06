<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Zamówienie do zapisu - named arguments, null = nie wysyłaj pola.
 * Przy tworzeniu (`POST /v2/contractors/{contractorId}/orders`) API wymaga
 * niepustej listy `products`.
 *
 *     new OrderInput(products: [new OrderProductInput(productSku: 'LIC-PRO', quantity: '2')])
 */
final readonly class OrderInput implements Arrayable
{
    /**
     * @param list<OrderProductInput|array<string, mixed>>|null $products      pozycje zamówienia
     * @param string|null                                       $createdAt     data utworzenia przy
     *                                                                         imporcie historycznym
     * @param int|null                                          $creatorUserId tylko przy tworzeniu
     *                                                                         (import historii)
     */
    public function __construct(
        public ?array $products = null,
        public ?int $orderStatusId = null,
        public ?string $note = null,
        public ?string $currency = null,
        public ?string $place = null,
        public ?string $orderDate = null,
        public ?string $validUntil = null,
        public ?string $deliveryDate = null,
        public ?string $number = null,
        public ?int $foreignNumber = null,
        public ?string $sentDate = null,
        public ?string $createdAt = null,
        public ?int $creatorUserId = null,
    ) {
    }

    public function toArray(): array
    {
        $products = null;
        if ($this->products !== null) {
            $products = array_map(
                static fn (OrderProductInput|array $item): array => $item instanceof OrderProductInput ? $item->toArray() : $item,
                $this->products,
            );
        }

        return Cast::withoutNulls([
            'products' => $products,
            'orderStatusId' => $this->orderStatusId,
            'note' => $this->note,
            'currency' => $this->currency,
            'place' => $this->place,
            'orderDate' => $this->orderDate,
            'validUntil' => $this->validUntil,
            'deliveryDate' => $this->deliveryDate,
            'number' => $this->number,
            'foreignNumber' => $this->foreignNumber,
            'sentDate' => $this->sentDate,
            'createdAt' => $this->createdAt,
            'creatorUserId' => $this->creatorUserId,
        ]);
    }
}
