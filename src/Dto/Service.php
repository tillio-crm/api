<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Usługa (odczyt) - instancja pozycji katalogu (`catalogId`) u kontrahenta.
 * Kanon 2.0.0: `salesUserId` (handlowiec) i `ownerUserId` (opiekun) to osobne
 * pola. Kwoty jako stringi dziesiętne. Pełny payload w `$raw`.
 */
final readonly class Service
{
    /**
     * @param array<string, mixed> $customField wartości pól niestandardowych
     * @param array<string, mixed> $raw         pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?int $contractorId,
        public ?int $catalogId,
        public ?string $catalogName,
        public ?string $customName,
        public ?int $serviceStatusId,
        public ?int $billingPeriodId,
        public ?int $paymentTermId,
        public ?int $invoiceTypeId,
        public ?int $salesUserId,
        public ?int $ownerUserId,
        public ?string $place,
        public ?string $note,
        public ?string $currency,
        public ?string $payValue,
        public ?string $costValue,
        public ?string $salesDate,
        public ?string $agreementDate,
        public ?string $agreementFrom,
        public ?string $agreementTo,
        public ?string $agreementEnd,
        public ?string $agreementTermination,
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
            contractorId: Cast::int($row['contractorId'] ?? null),
            catalogId: Cast::int($row['catalogId'] ?? null),
            catalogName: Cast::string($row['catalogName'] ?? null),
            customName: Cast::string($row['customName'] ?? null),
            serviceStatusId: Cast::int($row['serviceStatusId'] ?? null),
            billingPeriodId: Cast::int($row['billingPeriodId'] ?? null),
            paymentTermId: Cast::int($row['paymentTermId'] ?? null),
            invoiceTypeId: Cast::int($row['invoiceTypeId'] ?? null),
            salesUserId: Cast::int($row['salesUserId'] ?? null),
            ownerUserId: Cast::int($row['ownerUserId'] ?? null),
            place: Cast::string($row['place'] ?? null),
            note: Cast::string($row['note'] ?? null),
            currency: Cast::string($row['currency'] ?? null),
            payValue: Cast::string($row['payValue'] ?? null),
            costValue: Cast::string($row['costValue'] ?? null),
            salesDate: Cast::string($row['salesDate'] ?? null),
            agreementDate: Cast::string($row['agreementDate'] ?? null),
            agreementFrom: Cast::string($row['agreementFrom'] ?? null),
            agreementTo: Cast::string($row['agreementTo'] ?? null),
            agreementEnd: Cast::string($row['agreementEnd'] ?? null),
            agreementTermination: Cast::string($row['agreementTermination'] ?? null),
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
