<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Wiadomość SMS do zapisu. Przy tworzeniu API wymaga source, sourceId,
 * direction, status, remoteNumber, body, sentAt. Dostępne od wersji API 2.10.0.
 */
final readonly class TextMessageInput implements Arrayable
{
    /**
     * @param 'inbound'|'outbound'|string|null                   $direction     kierunek wiadomości
     * @param 'received'|'sent'|'delivered'|'failed'|string|null $status        status wiadomości
     * @param string|null                                        $body          treść, do 1024 znaków
     * @param int|null                                           $creatorUserId tylko przy tworzeniu
     */
    public function __construct(
        public ?string $source = null,
        public ?string $sourceId = null,
        public ?string $direction = null,
        public ?string $status = null,
        public ?string $remoteNumber = null,
        public ?string $ownNumber = null,
        public ?string $body = null,
        public ?string $sentAt = null,
        public ?string $deliveredAt = null,
        public ?int $userId = null,
        public ?int $contactId = null,
        public ?int $contractorId = null,
        public ?string $callsUrl = null,
        public ?int $creatorUserId = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'source' => $this->source,
            'sourceId' => $this->sourceId,
            'direction' => $this->direction,
            'status' => $this->status,
            'remoteNumber' => $this->remoteNumber,
            'ownNumber' => $this->ownNumber,
            'body' => $this->body,
            'sentAt' => $this->sentAt,
            'deliveredAt' => $this->deliveredAt,
            'userId' => $this->userId,
            'contactId' => $this->contactId,
            'contractorId' => $this->contractorId,
            'callsUrl' => $this->callsUrl,
            'creatorUserId' => $this->creatorUserId,
        ]);
    }
}
