<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Baza wiedzy wiki (odczyt). `type`: `article` albo `procedure`.
 */
final readonly class WikiBase
{
    /**
     * @param 'article'|'procedure'|null $type rodzaj bazy
     * @param array<string, mixed>       $raw  pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $subtitle,
        public ?string $type,
        public ?string $icon,
        public ?string $color,
        public ?bool $archived,
        public ?int $order,
        public ?int $creatorUserId,
        public ?string $createdAt,
        public array $raw = [],
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        /** @var 'article'|'procedure'|null $type wartość spoza zbioru traktujemy jak kontraktową */
        $type = Cast::string($row['type'] ?? null);

        return new self(
            id: Cast::requiredInt($row['id'] ?? null),
            name: Cast::string($row['name'] ?? null),
            subtitle: Cast::string($row['subtitle'] ?? null),
            type: $type,
            icon: Cast::string($row['icon'] ?? null),
            color: Cast::string($row['color'] ?? null),
            archived: Cast::bool($row['archived'] ?? null),
            order: Cast::int($row['order'] ?? null),
            creatorUserId: Cast::int($row['creatorUserId'] ?? null),
            createdAt: Cast::string($row['createdAt'] ?? null),
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
