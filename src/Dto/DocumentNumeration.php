<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Schemat numeracji dokumentów generatora (tylko odczyt) - do wyboru
 * `numerationId` przy zakładaniu typu dokumentu.
 *
 * Dostępne od wersji API 2.4.0.
 */
final readonly class DocumentNumeration
{
    /**
     * @param string|null          $schema      wzór numeru (np. `OF/[NR]/[MM]/[RRRR]`)
     * @param string|null          $resetPeriod okres resetu licznika
     * @param array<string, mixed> $raw         pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $schema,
        public ?int $startNumber,
        public ?string $resetPeriod,
        public ?bool $active,
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
            schema: Cast::string($row['schema'] ?? null),
            startNumber: Cast::int($row['startNumber'] ?? null),
            resetPeriod: Cast::string($row['resetPeriod'] ?? null),
            active: Cast::bool($row['active'] ?? null),
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
