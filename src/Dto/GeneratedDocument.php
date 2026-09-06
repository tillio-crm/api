<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Dokument z generatora (odczyt).
 *
 * `downloadUrl` - podpisany link (TTL ~1 minuta, świeży po `get()`);
 * `publishUrl` - publiczny adres opublikowanego dokumentu (ważny do
 * `publishValidTo`); `error` - szczegóły nieudanego generowania.
 */
final readonly class GeneratedDocument
{
    /**
     * @param array<string, mixed>|null $error szczegóły błędu generowania (null = OK)
     * @param array<string, mixed>      $raw   pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?int $contractorId,
        public ?int $salesPipelineId,
        public ?int $documentTypeId,
        public ?string $typeName,
        public ?int $templateId,
        public ?int $documentStatusId,
        public ?string $statusName,
        public ?string $number,
        public ?int $creatorUserId,
        public ?string $value,
        public ?string $currency,
        public ?string $createdAt,
        public ?string $updatedAt,
        public ?string $publishUrl,
        public ?string $publishValidTo,
        public ?string $downloadUrl,
        public ?array $error,
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
            salesPipelineId: Cast::int($row['salesPipelineId'] ?? null),
            documentTypeId: Cast::int($row['documentTypeId'] ?? null),
            typeName: Cast::string($row['typeName'] ?? null),
            templateId: Cast::int($row['templateId'] ?? null),
            documentStatusId: Cast::int($row['documentStatusId'] ?? null),
            statusName: Cast::string($row['statusName'] ?? null),
            number: Cast::string($row['number'] ?? null),
            creatorUserId: Cast::int($row['creatorUserId'] ?? null),
            value: Cast::string($row['value'] ?? null),
            currency: Cast::string($row['currency'] ?? null),
            createdAt: Cast::string($row['createdAt'] ?? null),
            updatedAt: Cast::string($row['updatedAt'] ?? null),
            publishUrl: Cast::string($row['publishUrl'] ?? null),
            publishValidTo: Cast::string($row['publishValidTo'] ?? null),
            downloadUrl: Cast::string($row['downloadUrl'] ?? null),
            error: Cast::mapOrNull($row['error'] ?? null),
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
