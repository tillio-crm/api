<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Adres do zapisu - element listy `address` kontrahenta albo payload
 * `POST /v2/contractors/{id}/addresses` / `PUT /v2/addresses/{id}`.
 *
 *     new AddressInput(addressTypeId: 1, street: 'Prosta 51', city: 'Warszawa')
 *
 * `addressTypeId` jest wymagane przy tworzeniu (typy z `GET /v2/address/types`).
 * Null = nie wysyłaj pola.
 */
final readonly class AddressInput implements Arrayable
{
    /**
     * @param int|null    $addressTypeId        typ adresu (1 = podstawowy)
     * @param string|null $addressLookup        adres do geokodowania (Google Places)
     * @param bool|null   $failOnInvalidAddress true = nieudane geokodowanie to błąd, nie ostrzeżenie
     * @param bool|null   $duplicateCheck       true = nie twórz drugiego identycznego adresu
     *                                          (tylko POST nowego adresu)
     */
    public function __construct(
        public ?int $addressTypeId = null,
        public ?string $street = null,
        public ?string $street2 = null,
        public ?string $postCode = null,
        public ?string $city = null,
        public ?string $region = null,
        public ?string $district = null,
        public ?string $country = null,
        public ?string $addressLookup = null,
        public ?bool $failOnInvalidAddress = null,
        public ?bool $duplicateCheck = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'addressTypeId' => $this->addressTypeId,
            'street' => $this->street,
            'street2' => $this->street2,
            'postCode' => $this->postCode,
            'city' => $this->city,
            'region' => $this->region,
            'district' => $this->district,
            'country' => $this->country,
            'addressLookup' => $this->addressLookup,
            'failOnInvalidAddress' => $this->failOnInvalidAddress,
            'duplicateCheck' => $this->duplicateCheck,
        ]);
    }
}
