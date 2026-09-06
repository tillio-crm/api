<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Szansa sprzedaży (odczyt) - pozycja w lejku (`pipelineStageId` z lejków
 * `GET /v2/pipeline/funnels`). Kwoty jako stringi dziesiętne. Pełny payload w `$raw`.
 *
 * Inne encje wskazują szansę różnymi nazwami pól: `pipelineId` (Note),
 * `pipelineItemId` (TaskInput), `salesPipelineId` (Lead, GeneratedDocument) -
 * wszystkie znaczą id pozycji lejka.
 */
final readonly class PipelineItem
{
    /**
     * @param array<string, mixed> $customField wartości pól niestandardowych
     * @param array<string, mixed> $raw         pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $name,
        public ?int $contractorId,
        public ?int $pipelineStageId,
        public ?int $pipelineStatusId,
        public ?int $ownerUserId,
        public ?int $creatorUserId,
        public ?int $closerUserId,
        public ?string $amount,
        public ?string $currency,
        public ?int $probability,
        public ?string $closeDate,
        public ?string $realCloseDate,
        public ?string $lostReason,
        public ?string $note,
        public ?string $externalId,
        public ?int $leadId,
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
            name: Cast::string($row['name'] ?? null),
            contractorId: Cast::int($row['contractorId'] ?? null),
            pipelineStageId: Cast::int($row['pipelineStageId'] ?? null),
            pipelineStatusId: Cast::int($row['pipelineStatusId'] ?? null),
            ownerUserId: Cast::int($row['ownerUserId'] ?? null),
            creatorUserId: Cast::int($row['creatorUserId'] ?? null),
            closerUserId: Cast::int($row['closerUserId'] ?? null),
            amount: Cast::string($row['amount'] ?? null),
            currency: Cast::string($row['currency'] ?? null),
            probability: Cast::int($row['probability'] ?? null),
            closeDate: Cast::string($row['closeDate'] ?? null),
            realCloseDate: Cast::string($row['realCloseDate'] ?? null),
            lostReason: Cast::string($row['lostReason'] ?? null),
            note: Cast::string($row['note'] ?? null),
            externalId: Cast::string($row['externalId'] ?? null),
            leadId: Cast::int($row['leadId'] ?? null),
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
