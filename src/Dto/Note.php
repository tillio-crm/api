<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Notatka (odczyt) - przypięta do kontrahenta, leada, usługi albo szansy.
 * Pełny payload w `$raw`.
 */
final readonly class Note
{
    /**
     * @param list<int>            $contactIds  osoby kontaktowe przypięte do notatki (od API 2.8.0)
     * @param array<string, mixed> $customField wartości pól niestandardowych
     * @param array<string, mixed> $raw         pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?int $contractorId,
        public ?int $leadId,
        public ?int $serviceId,
        public ?int $pipelineId,
        public ?int $noteTypeId,
        public ?string $title,
        public ?string $body,
        public ?bool $pinned,
        public ?int $creatorUserId,
        public ?string $noteDate,
        public ?string $createdAt,
        public array $contactIds,
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
            leadId: Cast::int($row['leadId'] ?? null),
            serviceId: Cast::int($row['serviceId'] ?? null),
            pipelineId: Cast::int($row['pipelineId'] ?? null),
            noteTypeId: Cast::int($row['noteTypeId'] ?? null),
            title: Cast::string($row['title'] ?? null),
            body: Cast::string($row['body'] ?? null),
            pinned: Cast::bool($row['pinned'] ?? null),
            creatorUserId: Cast::int($row['creatorUserId'] ?? null),
            noteDate: Cast::string($row['noteDate'] ?? null),
            createdAt: Cast::string($row['createdAt'] ?? null),
            contactIds: Cast::intList($row['contactIds'] ?? null),
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
