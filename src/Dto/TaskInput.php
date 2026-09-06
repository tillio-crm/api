<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Zadanie do zapisu - named arguments, null = nie wysyłaj pola.
 * Przy tworzeniu API wymaga `title` i niepustej listy `assignedUserIds`.
 */
final readonly class TaskInput implements Arrayable
{
    /**
     * @param list<int>|null            $assignedUserIds wykonawcy - tylko przy tworzeniu
     * @param int|null                  $contactId       osoba kontaktowa powiązana z zadaniem (od API 2.8.0)
     * @param int|null                  $taskStatusId    tylko przy tworzeniu
     * @param array<string, mixed>|null $customField     tylko przy tworzeniu
     * @param string|null               $createdAt       data utworzenia przy imporcie historycznym
     * @param int|null                  $creatorUserId   tylko przy tworzeniu
     */
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?int $priority = null,
        public ?int $ownerUserId = null,
        public ?array $assignedUserIds = null,
        public ?int $contractorId = null,
        public ?int $contactId = null,
        public ?int $pipelineItemId = null,
        public ?int $leadId = null,
        public ?string $startDate = null,
        public ?string $dueDate = null,
        public ?int $taskStatusId = null,
        public ?array $customField = null,
        public ?string $createdAt = null,
        public ?int $creatorUserId = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'ownerUserId' => $this->ownerUserId,
            'assignedUserIds' => $this->assignedUserIds,
            'contractorId' => $this->contractorId,
            'contactId' => $this->contactId,
            'pipelineItemId' => $this->pipelineItemId,
            'leadId' => $this->leadId,
            'startDate' => $this->startDate,
            'dueDate' => $this->dueDate,
            'taskStatusId' => $this->taskStatusId,
            'customField' => $this->customField,
            'createdAt' => $this->createdAt,
            'creatorUserId' => $this->creatorUserId,
        ]);
    }
}
