<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Usługa do zapisu - named arguments, null = nie wysyłaj pola.
 * Przy tworzeniu API wymaga `catalogId` i `contractorId`.
 * Kwoty (`payValue`, `costValue`) jako stringi dziesiętne.
 */
final readonly class ServiceInput implements Arrayable
{
    /**
     * @param int|null                  $catalogId     pozycja katalogu - tylko przy tworzeniu
     * @param array<string, mixed>|null $customField   wartości pól niestandardowych
     * @param string|null               $createdAt     data utworzenia przy imporcie historycznym
     * @param int|null                  $creatorUserId tylko przy tworzeniu
     */
    public function __construct(
        public ?int $catalogId = null,
        public ?int $contractorId = null,
        public ?string $customName = null,
        public ?string $note = null,
        public ?string $place = null,
        public ?string $salesDate = null,
        public ?string $payValue = null,
        public ?string $currency = null,
        public ?string $costValue = null,
        public ?int $payDay = null,
        public ?int $salesUserId = null,
        public ?int $ownerUserId = null,
        public ?string $agreementDate = null,
        public ?string $agreementFrom = null,
        public ?string $agreementTo = null,
        public ?string $agreementEnd = null,
        public ?string $agreementTermination = null,
        public ?array $customField = null,
        public ?string $createdAt = null,
        public ?int $creatorUserId = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'catalogId' => $this->catalogId,
            'contractorId' => $this->contractorId,
            'customName' => $this->customName,
            'note' => $this->note,
            'place' => $this->place,
            'salesDate' => $this->salesDate,
            'payValue' => $this->payValue,
            'currency' => $this->currency,
            'costValue' => $this->costValue,
            'payDay' => $this->payDay,
            'salesUserId' => $this->salesUserId,
            'ownerUserId' => $this->ownerUserId,
            'agreementDate' => $this->agreementDate,
            'agreementFrom' => $this->agreementFrom,
            'agreementTo' => $this->agreementTo,
            'agreementEnd' => $this->agreementEnd,
            'agreementTermination' => $this->agreementTermination,
            'customField' => $this->customField,
            'createdAt' => $this->createdAt,
            'creatorUserId' => $this->creatorUserId,
        ]);
    }
}
