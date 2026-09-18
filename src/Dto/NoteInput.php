<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Notatka do zapisu - named arguments, null = nie wysyłaj pola.
 * Przy tworzeniu (`POST /v2/contractors/{contractorId}/notes`,
 * `POST /v2/contacts/{contactId}/notes` albo `POST /v2/leads/{leadId}/notes`)
 * API wymaga `noteTypeId` i `title`. Notatka pod leadem nie przyjmuje
 * `contactIds`, `serviceId` ani `pipelineItemId` (422).
 */
final readonly class NoteInput implements Arrayable
{
    /**
     * @param int|null                  $noteTypeId     tylko przy tworzeniu
     * @param string|null               $body           treść HTML; API czyści ją do bezpiecznego podzbioru jak
     *                                                  edytor CRM (od API 2.15.0) - pusta po wycięciu znaczy pole
     *                                                  pominięte z ostrzeżeniem w `WriteResult::$warnings`
     * @param list<int>|null            $contactIds     osoby kontaktowe do przypięcia (od API 2.8.0);
     *                                                  przy notatce kontrahenta tylko kontakty tego kontrahenta
     * @param int|null                  $contractorId   kontrahent notatki - używane w `Contacts::createNote()`
     *                                                   (od API 2.10.0); przy notatce kontrahenta id jest w ścieżce
     * @param int|null                  $serviceId      powiązanie z usługą kontrahenta notatki; usługa INNEGO
     *                                                  kontrahenta to 422 na `serviceId` (od API 2.15.0;
     *                                                  wcześniej CRM po cichu zerował powiązanie)
     * @param int|null                  $pipelineItemId powiązanie z szansą sprzedaży kontrahenta notatki; szansa
     *                                                  INNEGO kontrahenta to 422 na `pipelineItemId` (od API 2.15.0).
     *                                                  W ODCZYCIE notatki to samo powiązanie nazywa się `pipelineId`
     * @param array<string, mixed>|null $customField    wartości pól niestandardowych
     * @param string|null               $createdAt      data utworzenia przy imporcie historycznym
     * @param int|null                  $creatorUserId  tylko przy tworzeniu
     */
    public function __construct(
        public ?int $noteTypeId = null,
        public ?string $title = null,
        public ?string $body = null,
        public ?bool $pinned = null,
        public ?string $noteDate = null,
        public ?array $contactIds = null,
        public ?int $contractorId = null,
        public ?int $serviceId = null,
        public ?int $pipelineItemId = null,
        public ?array $customField = null,
        public ?string $createdAt = null,
        public ?int $creatorUserId = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'noteTypeId' => $this->noteTypeId,
            'title' => $this->title,
            'body' => $this->body,
            'pinned' => $this->pinned,
            'noteDate' => $this->noteDate,
            'contactIds' => $this->contactIds,
            'contractorId' => $this->contractorId,
            'serviceId' => $this->serviceId,
            'pipelineItemId' => $this->pipelineItemId,
            'customField' => $this->customField,
            'createdAt' => $this->createdAt,
            'creatorUserId' => $this->creatorUserId,
        ]);
    }
}
