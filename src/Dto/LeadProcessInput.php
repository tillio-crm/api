<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Proces leadowy do zapisu. Wymagane `name`; statusy razem z procesem (`stages`)
 * albo później (`Dictionaries::createLeadStatus()`).
 */
final readonly class LeadProcessInput implements Arrayable
{
    /**
     * @param list<ProcessStageInput|array<string, mixed>>|null $stages statusy zakładane razem z procesem
     */
    public function __construct(
        public ?string $name = null,
        public ?int $order = null,
        public ?bool $active = null,
        public ?array $stages = null,
    ) {
    }

    public function toArray(): array
    {
        $stages = null;
        if ($this->stages !== null) {
            $stages = array_map(
                static fn (ProcessStageInput|array $stage): array => $stage instanceof ProcessStageInput ? $stage->toArray() : $stage,
                $this->stages,
            );
        }

        return Cast::withoutNulls([
            'name' => $this->name,
            'order' => $this->order,
            'active' => $this->active,
            'stages' => $stages,
        ]);
    }
}
