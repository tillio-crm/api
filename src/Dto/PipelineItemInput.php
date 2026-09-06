<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Szansa sprzedaży do zapisu - named arguments, null = nie wysyłaj pola.
 * Przy tworzeniu API wymaga `name`, `pipelineStageId` i `contractorId`.
 * `amount` jako string dziesiętny.
 */
final readonly class PipelineItemInput implements Arrayable
{
    /**
     * @param int|null                  $pipelineStageId  tylko przy tworzeniu (dalej: proces lejka)
     * @param int|null                  $contractorId     tylko przy tworzeniu
     * @param int|null                  $pipelineStatusId tylko przy tworzeniu
     * @param array<string, mixed>|null $customField      wartości pól niestandardowych
     * @param string|null               $createdAt        data utworzenia przy imporcie historycznym
     * @param int|null                  $creatorUserId    tylko przy tworzeniu
     */
    public function __construct(
        public ?string $name = null,
        public ?int $pipelineStageId = null,
        public ?int $contractorId = null,
        public ?string $note = null,
        public ?string $amount = null,
        public ?string $currency = null,
        public ?string $closeDate = null,
        public ?int $probability = null,
        public ?int $ownerUserId = null,
        public ?int $pipelineStatusId = null,
        public ?array $customField = null,
        public ?string $createdAt = null,
        public ?int $creatorUserId = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'name' => $this->name,
            'pipelineStageId' => $this->pipelineStageId,
            'contractorId' => $this->contractorId,
            'note' => $this->note,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'closeDate' => $this->closeDate,
            'probability' => $this->probability,
            'ownerUserId' => $this->ownerUserId,
            'pipelineStatusId' => $this->pipelineStatusId,
            'customField' => $this->customField,
            'createdAt' => $this->createdAt,
            'creatorUserId' => $this->creatorUserId,
        ]);
    }
}
