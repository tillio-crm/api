<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Zgłoszenie do zapisu - named arguments, null = nie wysyłaj pola.
 * Przy tworzeniu API wymaga `title`.
 *
 * Pola oznaczone "tylko przy tworzeniu" PUT odrzuca - status/etap/źródło
 * zgłoszenia po utworzeniu zmienia proces obsługi, nie goły zapis.
 */
final readonly class TicketInput implements Arrayable
{
    /**
     * @param int|null                  $contractorId      tylko przy tworzeniu
     * @param int|null                  $ticketStatusId    tylko przy tworzeniu
     * @param int|null                  $ticketStageId     tylko przy tworzeniu
     * @param int|null                  $ticketSourceId    tylko przy tworzeniu
     * @param bool|null                 $createClientPanel tylko przy tworzeniu: załóż panel klienta
     * @param array<string, mixed>|null $customField       wartości pól niestandardowych
     * @param string|null               $createdAt         data utworzenia przy imporcie historycznym
     * @param int|null                  $creatorUserId     tylko przy tworzeniu
     */
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?int $priority = null,
        public ?int $ownerUserId = null,
        public ?string $email = null,
        public ?int $contractorId = null,
        public ?int $contactId = null,
        public ?int $serviceId = null,
        public ?string $resolutionAt = null,
        public ?string $endedAt = null,
        public ?bool $open = null,
        public ?int $ticketStatusId = null,
        public ?int $ticketStageId = null,
        public ?int $ticketSourceId = null,
        public ?bool $createClientPanel = null,
        public ?array $customField = null,
        public ?string $createdAt = null,
        public ?int $creatorUserId = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'ownerUserId' => $this->ownerUserId,
            'email' => $this->email,
            'contractorId' => $this->contractorId,
            'contactId' => $this->contactId,
            'serviceId' => $this->serviceId,
            'resolutionAt' => $this->resolutionAt,
            'endedAt' => $this->endedAt,
            'open' => $this->open,
            'ticketStatusId' => $this->ticketStatusId,
            'ticketStageId' => $this->ticketStageId,
            'ticketSourceId' => $this->ticketSourceId,
            'createClientPanel' => $this->createClientPanel,
            'customField' => $this->customField,
            'createdAt' => $this->createdAt,
            'creatorUserId' => $this->creatorUserId,
        ]);
    }
}
