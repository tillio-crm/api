<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Zamówienie (odczyt). Kwoty jako stringi dziesiętne. UWAGA: zamówienia NIE mają
 * pól niestandardowych w API - do wyszukiwania służy `number`. Pełny payload
 * w `$raw`.
 */
final readonly class Order
{
    /**
     * @param array<string, mixed> $customField wartości pól niestandardowych (zwykle puste)
     * @param list<OrderProduct>   $products    pozycje zamówienia
     * @param array<string, mixed> $raw         pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $number,
        public ?int $foreignNumber,
        public ?int $contractorId,
        public ?int $ownerUserId,
        public ?int $orderStatusId,
        public ?string $note,
        public ?string $totalAmount,
        public ?string $currency,
        public ?string $place,
        public ?string $orderDate,
        public ?string $validUntil,
        public ?string $deliveryDate,
        public ?string $sentDate,
        public ?string $createdAt,
        public ?string $updatedAt,
        public array $customField,
        public array $products,
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
            number: Cast::string($row['number'] ?? null),
            foreignNumber: Cast::int($row['foreignNumber'] ?? null),
            contractorId: Cast::int($row['contractorId'] ?? null),
            ownerUserId: Cast::int($row['ownerUserId'] ?? null),
            orderStatusId: Cast::int($row['orderStatusId'] ?? null),
            note: Cast::string($row['note'] ?? null),
            totalAmount: Cast::string($row['totalAmount'] ?? null),
            currency: Cast::string($row['currency'] ?? null),
            place: Cast::string($row['place'] ?? null),
            orderDate: Cast::string($row['orderDate'] ?? null),
            validUntil: Cast::string($row['validUntil'] ?? null),
            deliveryDate: Cast::string($row['deliveryDate'] ?? null),
            sentDate: Cast::string($row['sentDate'] ?? null),
            createdAt: Cast::string($row['createdAt'] ?? null),
            updatedAt: Cast::string($row['updatedAt'] ?? null),
            customField: Cast::map($row['customField'] ?? null),
            products: array_map(OrderProduct::fromArray(...), Cast::rows($row['products'] ?? null)),
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
