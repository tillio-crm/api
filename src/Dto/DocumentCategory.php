<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Kategoria typów dokumentów generatora - płaskie drzewo (`parentId` wskazuje
 * kategorię nadrzędną, null = najwyższy poziom). Kategorie z `system = true`
 * to katalogi wbudowane CRM - widoczne na liście, ale nie do edycji.
 *
 * Dostępne od wersji API 2.4.0.
 */
final readonly class DocumentCategory
{
    /**
     * @param int|null             $priority kolejność na listach (wyższy = wyżej)
     * @param bool|null            $system   kategoria wbudowana CRM - tylko do odczytu
     * @param array<string, mixed> $raw      pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $name,
        public ?int $parentId,
        public ?int $priority,
        public ?bool $system,
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
            system: Cast::bool($row['system'] ?? null),
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
