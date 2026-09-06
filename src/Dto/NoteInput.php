<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Notatka do zapisu - named arguments, null = nie wysyłaj pola.
 * Przy tworzeniu (`POST /v2/contractors/{contractorId}/notes` albo
 * `POST /v2/contacts/{contactId}/notes`) API wymaga `noteTypeId` i `title`.
 */
final readonly class NoteInput implements Arrayable
{
    /**
     * @param int|null                  $noteTypeId     tylko przy tworzeniu
     * @param list<int>|null            $contactIds     osoby kontaktowe do przypięcia (od API 2.8.0);
     *                                                  przy notatce kontrahenta tylko kontakty tego kontrahenta
     * @param int|null                  $contractorId   kontrahent notatki - używane w `Contacts::createNote()`
     *                                                   (od API 2.10.0); przy notatce kontrahenta id jest w ścieżce
     * @param int|null                  $serviceId      powiązanie z usługą (notatka pod kontaktem)
     * @param int|null                  $pipelineItemId powiązanie z szansą sprzedaży (notatka pod kontaktem)
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
