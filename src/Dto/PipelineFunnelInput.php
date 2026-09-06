<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Lejek sprzedaży do zapisu. Wymagane `name`; etapy razem z lejkiem (`stages`)
 * albo później (`Dictionaries::createPipelineStage()`).
 */
final readonly class PipelineFunnelInput implements Arrayable
{
    /**
     * @param list<ProcessStageInput|array<string, mixed>>|null $stages etapy zakładane razem z lejkiem
     */
    public function __construct(
        public ?string $name = null,
        public ?int $order = null,
        public ?bool $requireChangeReason = null,
        public ?bool $automaticAmountUpdate = null,
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
            'requireChangeReason' => $this->requireChangeReason,
            'automaticAmountUpdate' => $this->automaticAmountUpdate,
            'active' => $this->active,
            'stages' => $stages,
        ]);
    }
}
