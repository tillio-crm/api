<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Grupa produktów do zapisu - named arguments, null = nie wysyłaj pola.
 * Przy tworzeniu API wymaga `name`.
 */
final readonly class ProductGroupInput implements Arrayable
{
    /**
     * @param string|null $createdAt     data utworzenia przy imporcie historycznym
     * @param int|null    $creatorUserId tylko przy tworzeniu (import historii)
     */
    public function __construct(
        public ?string $name = null,
        public ?int $parentId = null,
        public ?string $color = null,
        public ?int $priority = null,
        public ?int $status = null,
        public ?string $createdAt = null,
        public ?int $creatorUserId = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'name' => $this->name,
            'parentId' => $this->parentId,
            'color' => $this->color,
            'priority' => $this->priority,
            'status' => $this->status,
            'createdAt' => $this->createdAt,
            'creatorUserId' => $this->creatorUserId,
        ]);
    }
}
