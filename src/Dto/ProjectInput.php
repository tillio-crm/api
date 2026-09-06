<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Projekt do zapisu - named arguments, null = nie wysyłaj pola.
 * Przy tworzeniu API wymaga `name`.
 */
final readonly class ProjectInput implements Arrayable
{
    /**
     * @param array<string, mixed>|null $customField   wartości pól niestandardowych
     * @param string|null               $createdAt     data utworzenia przy imporcie historycznym
     * @param int|null                  $creatorUserId tylko przy tworzeniu
     */
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?int $contractorId = null,
        public ?int $ownerUserId = null,
        public ?int $projectStatusId = null,
        public ?string $color = null,
        public ?string $startDate = null,
        public ?string $dueDate = null,
        public ?array $customField = null,
        public ?string $createdAt = null,
        public ?int $creatorUserId = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'name' => $this->name,
            'description' => $this->description,
            'contractorId' => $this->contractorId,
            'ownerUserId' => $this->ownerUserId,
            'projectStatusId' => $this->projectStatusId,
            'color' => $this->color,
            'startDate' => $this->startDate,
            'dueDate' => $this->dueDate,
            'customField' => $this->customField,
            'createdAt' => $this->createdAt,
            'creatorUserId' => $this->creatorUserId,
        ]);
    }
}
