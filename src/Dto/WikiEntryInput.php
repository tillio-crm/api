<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Wpis wiki do zapisu. Przy tworzeniu wymagane `categoryId` i `title`.
 */
final readonly class WikiEntryInput implements Arrayable
{
    /**
     * @param bool|null   $archived      tylko przy aktualizacji
     * @param string|null $createdAt     data utworzenia przy imporcie historycznym
     * @param int|null    $creatorUserId tylko przy tworzeniu
     */
    public function __construct(
        public ?int $categoryId = null,
        public ?string $title = null,
        public ?string $content = null,
        public ?bool $published = null,
        public ?string $alias = null,
        public ?bool $archived = null,
        public ?string $createdAt = null,
        public ?int $creatorUserId = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'categoryId' => $this->categoryId,
            'title' => $this->title,
            'content' => $this->content,
            'published' => $this->published,
            'alias' => $this->alias,
            'archived' => $this->archived,
            'createdAt' => $this->createdAt,
            'creatorUserId' => $this->creatorUserId,
        ]);
    }
}
