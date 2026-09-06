<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Wpis wiki (odczyt) - artykuł albo procedura, z licznikami odsłon
 * i głosów "pomocne".
 */
final readonly class WikiEntry
{
    /**
     * @param array<string, mixed> $raw pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?int $baseId,
        public ?int $categoryId,
        public ?string $title,
        public ?string $content,
        public ?string $alias,
        public ?bool $published,
        public ?bool $archived,
        public ?int $views,
        public ?int $helpful,
        public ?int $order,
        public ?int $creatorUserId,
        public ?string $createdAt,
        public ?string $updatedAt,
        public ?string $publishedAt,
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
            baseId: Cast::int($row['baseId'] ?? null),
            categoryId: Cast::int($row['categoryId'] ?? null),
            title: Cast::string($row['title'] ?? null),
            content: Cast::string($row['content'] ?? null),
            alias: Cast::string($row['alias'] ?? null),
            published: Cast::bool($row['published'] ?? null),
            archived: Cast::bool($row['archived'] ?? null),
            views: Cast::int($row['views'] ?? null),
            helpful: Cast::int($row['helpful'] ?? null),
            order: Cast::int($row['order'] ?? null),
            creatorUserId: Cast::int($row['creatorUserId'] ?? null),
            createdAt: Cast::string($row['createdAt'] ?? null),
            updatedAt: Cast::string($row['updatedAt'] ?? null),
            publishedAt: Cast::string($row['publishedAt'] ?? null),
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
