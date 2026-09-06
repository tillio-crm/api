<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Projekt (odczyt) - z licznikami zadań. Pełny payload w `$raw`.
 */
final readonly class Project
{
    /**
     * @param array<string, mixed> $customField wartości pól niestandardowych
     * @param array<string, mixed> $raw         pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $description,
        public ?int $contractorId,
        public ?int $projectStatusId,
        public ?int $ownerUserId,
        public ?int $creatorUserId,
        public ?bool $archived,
        public ?string $startDate,
        public ?string $dueDate,
        public ?string $createdAt,
        public ?string $updatedAt,
        public ?int $tasksCount,
        public ?int $tasksDoneCount,
        public ?int $tasksUndoneCount,
        public ?int $tasksOverdueCount,
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
            description: Cast::string($row['description'] ?? null),
            contractorId: Cast::int($row['contractorId'] ?? null),
            projectStatusId: Cast::int($row['projectStatusId'] ?? null),
            ownerUserId: Cast::int($row['ownerUserId'] ?? null),
            creatorUserId: Cast::int($row['creatorUserId'] ?? null),
            archived: Cast::bool($row['archived'] ?? null),
            startDate: Cast::string($row['startDate'] ?? null),
            dueDate: Cast::string($row['dueDate'] ?? null),
            createdAt: Cast::string($row['createdAt'] ?? null),
            updatedAt: Cast::string($row['updatedAt'] ?? null),
            tasksCount: Cast::int($row['tasksCount'] ?? null),
            tasksDoneCount: Cast::int($row['tasksDoneCount'] ?? null),
            tasksUndoneCount: Cast::int($row['tasksUndoneCount'] ?? null),
            tasksOverdueCount: Cast::int($row['tasksOverdueCount'] ?? null),
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
