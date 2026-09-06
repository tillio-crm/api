<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Proces obsługi zgłoszeń do zapisu. Wymagane `name`; etapy można podać od razu
 * (`stages`) albo dokładać później (`Dictionaries::createTicketStage()`).
 */
final readonly class TicketProcessInput implements Arrayable
{
    /**
     * @param list<ProcessStageInput|array<string, mixed>>|null $stages etapy zakładane razem z procesem
     */
    public function __construct(
        public ?string $name = null,
        public ?int $resolutionTimeMinutes = null,
        public ?int $autoCloseTimeMinutes = null,
        public ?bool $autoTicket = null,
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
            'resolutionTimeMinutes' => $this->resolutionTimeMinutes,
            'autoCloseTimeMinutes' => $this->autoCloseTimeMinutes,
            'autoTicket' => $this->autoTicket,
            'order' => $this->order,
            'active' => $this->active,
            'stages' => $stages,
        ]);
    }
}
