<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Kategoria szablonów (maili, notatek albo zadań) - wspólny kształt trzech
 * słowników kategorii. Lista jest PŁASKA: drzewo składa się po `parentId`
 * (null = poziom główny). Stąd bierze się `categoryId` do tworzenia szablonu.
 *
 * Dostępne od wersji API 2.4.0.
 */
final readonly class TemplateCategory
{
    /**
     * @param int|null             $priority kolejność na listach (wyższy = wyżej)
     * @param array<string, mixed> $raw      pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $name,
        public ?int $parentId,
        public ?int $priority,
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
            parentId: Cast::int($row['parentId'] ?? null),
            priority: Cast::int($row['priority'] ?? null),
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
