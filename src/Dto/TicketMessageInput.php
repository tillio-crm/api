<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Wiadomość do zgłoszenia (`POST /v2/tickets/{id}/messages`). Wymagane `text`.
 * `visibility`: `public`/`internal` - UWAGA: `internal` wymaga wersji CRM
 * z komentarzami wewnętrznymi zgłoszeń; starsza instancja odpowie 422
 * z podpowiedzią, żeby użyć `public`.
 */
final readonly class TicketMessageInput implements Arrayable
{
    /**
     * @param 'public'|'internal'|null $visibility    widoczność wiadomości
     * @param string|null              $date          data wiadomości przy imporcie historycznym
     * @param int|null                 $creatorUserId autor (domyślnie użytkownik klucza API)
     */
    public function __construct(
        public ?string $text = null,
        public ?string $visibility = null,
        public ?string $date = null,
        public ?int $creatorUserId = null,
        public ?string $subject = null,
        public ?string $fromName = null,
        public ?string $fromEmail = null,
        public ?int $contactId = null,
        public ?int $replyToMessageId = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'text' => $this->text,
            'visibility' => $this->visibility,
            'date' => $this->date,
            'creatorUserId' => $this->creatorUserId,
            'subject' => $this->subject,
            'fromName' => $this->fromName,
            'fromEmail' => $this->fromEmail,
            'contactId' => $this->contactId,
            'replyToMessageId' => $this->replyToMessageId,
        ]);
    }
}
