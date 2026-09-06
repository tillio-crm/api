<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Adres kontrahenta (odczyt). Kontrahent ma LISTĘ adresów - każdy z własnym
 * `addressTypeId` (typy z `GET /v2/address/types`; 1 = podstawowy). Pojedynczy
 * obiekt adresu - jak w API v1 - jest odrzucany, adres zawsze jedzie w tablicy.
 */
final readonly class Address
{
    /**
     * @param array<string, mixed> $raw pełny rekord z API (nowe pola nie giną)
     */
    public function __construct(
        public int $id,
        public ?int $addressTypeId,
        public ?string $street,
        public ?string $street2,
        public ?string $postCode,
        public ?string $city,
        public ?string $region,
        public ?string $district,
        public ?string $country,
        public ?string $placeId,
        public ?float $latitude,
        public ?float $longitude,
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
            addressTypeId: Cast::int($row['addressTypeId'] ?? null),
            street: Cast::string($row['street'] ?? null),
            street2: Cast::string($row['street2'] ?? null),
            postCode: Cast::string($row['postCode'] ?? null),
            city: Cast::string($row['city'] ?? null),
            region: Cast::string($row['region'] ?? null),
            district: Cast::string($row['district'] ?? null),
            country: Cast::string($row['country'] ?? null),
            placeId: Cast::string($row['placeId'] ?? null),
            latitude: Cast::float($row['latitude'] ?? null),
            longitude: Cast::float($row['longitude'] ?? null),
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
