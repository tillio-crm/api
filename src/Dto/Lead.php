<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Lead (odczyt). `leadStatusId`/`leadStageId` z procesów leadowych
 * (`GET /v2/lead/statuses`). Pełny payload w `$raw`.
 */
final readonly class Lead
{
    /**
     * @param array<string, mixed> $customField wartości pól niestandardowych
     * @param list<string>         $emails      adresy e-mail leada
     * @param array<string, mixed> $raw         pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $title,
        public ?string $note,
        public ?int $leadStatusId,
        public ?int $leadStageId,
        public ?int $statusChangeReasonId,
        public ?int $categoryId,
        public ?int $contractorSourceId,
        public ?int $priority,
        public ?int $ownerUserId,
        public ?int $creatorUserId,
        public ?int $contractorId,
        public ?int $contactId,
        public ?int $salesPipelineId,
        public ?string $companyName,
        public ?string $taxId,
        public ?string $regon,
        public ?string $domain,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $position,
        public ?string $phone,
        public ?string $phoneAlternative,
        public ?string $street,
        public ?string $street2,
        public ?string $postCode,
        public ?string $city,
        public ?string $region,
        public ?string $district,
        public ?string $country,
        public ?string $createdAt,
        public ?string $updatedAt,
        public ?string $closedAt,
        public ?string $lastActivityAt,
        public array $customField,
        public array $emails,
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
            title: Cast::string($row['title'] ?? null),
            note: Cast::string($row['note'] ?? null),
            leadStatusId: Cast::int($row['leadStatusId'] ?? null),
            leadStageId: Cast::int($row['leadStageId'] ?? null),
            statusChangeReasonId: Cast::int($row['statusChangeReasonId'] ?? null),
            categoryId: Cast::int($row['categoryId'] ?? null),
            contractorSourceId: Cast::int($row['contractorSourceId'] ?? null),
            priority: Cast::int($row['priority'] ?? null),
            ownerUserId: Cast::int($row['ownerUserId'] ?? null),
            creatorUserId: Cast::int($row['creatorUserId'] ?? null),
            contractorId: Cast::int($row['contractorId'] ?? null),
            contactId: Cast::int($row['contactId'] ?? null),
            salesPipelineId: Cast::int($row['salesPipelineId'] ?? null),
            companyName: Cast::string($row['companyName'] ?? null),
            taxId: Cast::string($row['taxId'] ?? null),
            regon: Cast::string($row['regon'] ?? null),
            domain: Cast::string($row['domain'] ?? null),
            firstName: Cast::string($row['firstName'] ?? null),
            lastName: Cast::string($row['lastName'] ?? null),
            position: Cast::string($row['position'] ?? null),
            phone: Cast::string($row['phone'] ?? null),
            phoneAlternative: Cast::string($row['phoneAlternative'] ?? null),
            street: Cast::string($row['street'] ?? null),
            street2: Cast::string($row['street2'] ?? null),
            postCode: Cast::string($row['postCode'] ?? null),
            city: Cast::string($row['city'] ?? null),
            region: Cast::string($row['region'] ?? null),
            district: Cast::string($row['district'] ?? null),
            country: Cast::string($row['country'] ?? null),
            createdAt: Cast::string($row['createdAt'] ?? null),
            updatedAt: Cast::string($row['updatedAt'] ?? null),
            closedAt: Cast::string($row['closedAt'] ?? null),
            lastActivityAt: Cast::string($row['lastActivityAt'] ?? null),
            customField: Cast::map($row['customField'] ?? null),
            emails: Cast::stringList($row['emails'] ?? null),
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
