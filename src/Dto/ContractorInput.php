<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Kontrahent do zapisu (POST/PUT/upsert) - named arguments, null = nie wysyłaj pola.
 *
 *     new ContractorInput(
 *         name: 'Acme Sp. z o.o.',
 *         contractorTypeId: 1,
 *         taxId: '0000000000',
 *         address: [new AddressInput(addressTypeId: 1, city: 'Warszawa')],
 *     )
 *
 * Przy TWORZENIU API wymaga `name` i `contractorTypeId` oraz niepustego `alias`
 * (bez aliasu 422 - CRM generuje go z nazwy tylko przy niektórych ścieżkach).
 * Opcje zapisu (`duplicateCheck`, `taxIdLookup`...) NIE są polami encji - jadą
 * przez {@see WriteOptions}. Wyczyszczenie pola wartością null wymaga fallbacku
 * tablicowego metod zapisu (patrz {@see Arrayable}).
 */
final readonly class ContractorInput implements Arrayable
{
    /**
     * @param array<string, mixed>|null                    $customField   wartości pól niestandardowych
     * @param list<AddressInput|array<string, mixed>>|null $address       LISTA adresów - pojedynczy
     *                                                                    obiekt jest odrzucany przez API
     * @param string|null                                  $createdAt     data utworzenia przy imporcie
     *                                                                    danych historycznych
     * @param int|null                                     $creatorUserId tylko przy tworzeniu (import historii)
     */
    public function __construct(
        public ?string $name = null,
        public ?string $alias = null,
        public ?string $fullName = null,
        public ?string $taxId = null,
        public ?string $regon = null,
        public ?string $pesel = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $domain = null,
        public ?string $country = null,
        public ?string $note = null,
        public ?string $externalId = null,
        public ?int $contractorTypeId = null,
        public ?int $contractorStatusId = null,
        public ?int $industryId = null,
        public ?int $contractorSourceId = null,
        public ?int $contractorPriorityId = null,
        public ?int $paymentTypeId = null,
        public ?int $legalFormId = null,
        public ?int $ownerUserId = null,
        public ?int $parentId = null,
        public ?int $employeesCount = null,
        public ?string $revenue = null,
        public ?string $revenueCurrency = null,
        public ?array $customField = null,
        public ?array $address = null,
        public ?string $createdAt = null,
        public ?int $creatorUserId = null,
    ) {
    }

    public function toArray(): array
    {
        $address = null;
        if ($this->address !== null) {
            $address = array_map(
                static fn (AddressInput|array $item): array => $item instanceof AddressInput ? $item->toArray() : $item,
                $this->address,
            );
        }

        return Cast::withoutNulls([
            'name' => $this->name,
            'alias' => $this->alias,
            'fullName' => $this->fullName,
            'taxId' => $this->taxId,
            'regon' => $this->regon,
            'pesel' => $this->pesel,
            'email' => $this->email,
            'phone' => $this->phone,
            'domain' => $this->domain,
            'country' => $this->country,
            'note' => $this->note,
            'externalId' => $this->externalId,
            'contractorTypeId' => $this->contractorTypeId,
            'contractorStatusId' => $this->contractorStatusId,
            'industryId' => $this->industryId,
            'contractorSourceId' => $this->contractorSourceId,
            'contractorPriorityId' => $this->contractorPriorityId,
            'paymentTypeId' => $this->paymentTypeId,
            'legalFormId' => $this->legalFormId,
            'ownerUserId' => $this->ownerUserId,
            'parentId' => $this->parentId,
            'employeesCount' => $this->employeesCount,
            'revenue' => $this->revenue,
            'revenueCurrency' => $this->revenueCurrency,
            'customField' => $this->customField,
            'address' => $address,
            'createdAt' => $this->createdAt,
            'creatorUserId' => $this->creatorUserId,
        ]);
    }
}
