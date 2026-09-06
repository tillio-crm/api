<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Wiadomość w zgłoszeniu (odczyt). `visibility`: `public` (widoczna dla klienta)
 * albo `internal` (notatka wewnętrzna zespołu).
 */
final readonly class TicketMessage
{
    /**
     * @param 'public'|'internal'|null $visibility widoczność wiadomości
     * @param list<string>             $toEmails   adresy, na które wiadomość wyszła
     * @param array<string, mixed>     $raw        pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $visibility,
        public ?string $text,
        public ?string $subject,
        public ?int $creatorUserId,
        public ?int $contactId,
        public ?string $fromName,
        public ?string $fromEmail,
        public array $toEmails,
        public ?int $replyToMessageId,
        public ?string $date,
        public array $raw = [],
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        /** @var 'public'|'internal'|null $visibility wartość spoza zbioru traktujemy jak kontraktową */
        $visibility = Cast::string($row['visibility'] ?? null);

        return new self(
            id: Cast::requiredInt($row['id'] ?? null),
            visibility: $visibility,
            text: Cast::string($row['text'] ?? null),
            subject: Cast::string($row['subject'] ?? null),
            creatorUserId: Cast::int($row['creatorUserId'] ?? null),
            contactId: Cast::int($row['contactId'] ?? null),
            fromName: Cast::string($row['fromName'] ?? null),
            fromEmail: Cast::string($row['fromEmail'] ?? null),
            toEmails: Cast::stringList($row['toEmails'] ?? null),
            replyToMessageId: Cast::int($row['replyToMessageId'] ?? null),
            date: Cast::string($row['date'] ?? null),
            raw: $row,
        );
    }

    /**
     * Pełny, surowy rekord z API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->raw;
    }
}
