<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Zadanie (odczyt). `done` to flaga wykonania; `assignedUserIds` - kanon 2.0.0
 * dla listy wykonawców. Pełny payload w `$raw`.
 */
final readonly class Task
{
    /**
     * @param list<int>            $assignedUserIds wykonawcy zadania
     * @param int|null             $contactId       osoba kontaktowa powiązana z zadaniem (od API 2.8.0)
     * @param array<string, mixed> $customField     wartości pól niestandardowych
     * @param array<string, mixed> $raw             pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $title,
        public ?string $description,
        public ?bool $done,
        public ?int $taskTypeId,
        public ?int $priority,
        public ?int $estimatedTime,
        public ?int $contractorId,
        public ?int $contactId,
        public ?int $leadId,
        public ?int $projectId,
        public ?int $ownerUserId,
        public ?int $creatorUserId,
        public ?bool $archived,
        public ?string $startDate,
        public ?string $dueDate,
        public ?string $endDate,
        public ?string $createdAt,
        public ?string $updatedAt,
        public array $assignedUserIds,
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
            title: Cast::string($row['title'] ?? null),
            description: Cast::string($row['description'] ?? null),
            done: Cast::bool($row['done'] ?? null),
            taskTypeId: Cast::int($row['taskTypeId'] ?? null),
            priority: Cast::int($row['priority'] ?? null),
            estimatedTime: Cast::int($row['estimatedTime'] ?? null),
            contractorId: Cast::int($row['contractorId'] ?? null),
            contactId: Cast::int($row['contactId'] ?? null),
            leadId: Cast::int($row['leadId'] ?? null),
            projectId: Cast::int($row['projectId'] ?? null),
            ownerUserId: Cast::int($row['ownerUserId'] ?? null),
            creatorUserId: Cast::int($row['creatorUserId'] ?? null),
            archived: Cast::bool($row['archived'] ?? null),
            startDate: Cast::string($row['startDate'] ?? null),
            dueDate: Cast::string($row['dueDate'] ?? null),
            endDate: Cast::string($row['endDate'] ?? null),
            createdAt: Cast::string($row['createdAt'] ?? null),
            updatedAt: Cast::string($row['updatedAt'] ?? null),
            assignedUserIds: Cast::intList($row['assignedUserIds'] ?? null),
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
