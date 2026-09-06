<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Komentarz do zadania (odczyt) - wątki przez `parentCommentId`.
 */
final readonly class TaskComment
{
    /**
     * @param array<string, mixed> $raw pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $body,
        public ?int $creatorUserId,
        public ?int $parentCommentId,
        public ?string $createdAt,
        public ?string $updatedAt,
        public ?int $editorUserId,
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
            body: Cast::string($row['body'] ?? null),
            creatorUserId: Cast::int($row['creatorUserId'] ?? null),
            parentCommentId: Cast::int($row['parentCommentId'] ?? null),
            createdAt: Cast::string($row['createdAt'] ?? null),
            updatedAt: Cast::string($row['updatedAt'] ?? null),
            editorUserId: Cast::int($row['editorUserId'] ?? null),
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
