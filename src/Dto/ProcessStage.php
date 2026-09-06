<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Etap procesu/lejka (odczyt) - wspólny kształt etapów procesów zgłoszeń,
 * lejków sprzedaży i statusów procesów leadowych. `probability` tylko w lejkach,
 * `type` (`default|qualified|disqualified`) tylko w procesach leadowych.
 */
final readonly class ProcessStage
{
    /**
     * @param 'default'|'qualified'|'disqualified'|null $type tylko procesy leadowe
     * @param array<string, mixed>                      $raw  pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $color,
        public ?int $order,
        public ?int $probability,
        public ?string $type,
        public array $raw = [],
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        /** @var 'default'|'qualified'|'disqualified'|null $type wartość spoza zbioru traktujemy jak kontraktową */
        $type = Cast::string($row['type'] ?? null);

        return new self(
            id: Cast::requiredInt($row['id'] ?? null),
            name: Cast::string($row['name'] ?? null),
            color: Cast::string($row['color'] ?? null),
            order: Cast::int($row['order'] ?? null),
            probability: Cast::int($row['probability'] ?? null),
            type: $type,
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
