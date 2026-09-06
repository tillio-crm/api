<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Etap procesu/lejka do zapisu. Wymagane `name`; `probability` tylko w lejkach
 * sprzedaży, `type` (`default|qualified|disqualified`) tylko w procesach
 * leadowych.
 */
final readonly class ProcessStageInput implements Arrayable
{
    /**
     * @param 'default'|'qualified'|'disqualified'|null $type tylko procesy leadowe
     */
    public function __construct(
        public ?string $name = null,
        public ?string $color = null,
        public ?int $order = null,
        public ?int $probability = null,
        public ?string $type = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'name' => $this->name,
            'color' => $this->color,
            'order' => $this->order,
            'probability' => $this->probability,
            'type' => $this->type,
        ]);
    }
}
