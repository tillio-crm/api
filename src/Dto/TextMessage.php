<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Wiadomość SMS (odczyt). Ten sam rekord co zakładają integracje bramek SMS;
 * z każdą wiadomością powstaje notatka na kartotece (noteIds). Dostępne od
 * wersji API 2.10.0.
 */
final readonly class TextMessage
{
    /**
     * @param 'inbound'|'outbound'|string|null                       $direction     kierunek wiadomości
     * @param 'received'|'sent'|'delivered'|'failed'|string|null     $status        status wiadomości
     * @param list<int>            $contractorIds powiązane kartoteki kontrahentów
     * @param list<int>            $noteIds       notatki powstałe z wiadomości
     * @param array<string, mixed> $raw           pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $provider,
        public ?string $source,
        public ?string $sourceId,
        public ?string $direction,
        public ?string $status,
        public ?string $ownNumber,
        public ?string $remoteNumber,
        public ?string $body,
        public ?string $sentAt,
        public ?string $deliveredAt,
        public ?int $contactId,
        public array $contractorIds,
        public ?int $userId,
        public ?string $title,
        public ?string $callsUrl,
        public array $noteIds,
        public array $raw = [],
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            id: Cast::requiredInt($row['id'] ?? null),
            provider: Cast::string($row['provider'] ?? null),
            source: Cast::string($row['source'] ?? null),
            sourceId: Cast::string($row['sourceId'] ?? null),
            direction: Cast::string($row['direction'] ?? null),
            status: Cast::string($row['status'] ?? null),
            ownNumber: Cast::string($row['ownNumber'] ?? null),
            remoteNumber: Cast::string($row['remoteNumber'] ?? null),
            body: Cast::string($row['body'] ?? null),
            sentAt: Cast::string($row['sentAt'] ?? null),
            deliveredAt: Cast::string($row['deliveredAt'] ?? null),
            contactId: Cast::int($row['contactId'] ?? null),
            contractorIds: Cast::intList($row['contractorIds'] ?? null),
            userId: Cast::int($row['userId'] ?? null),
            title: Cast::string($row['title'] ?? null),
            callsUrl: Cast::string($row['callsUrl'] ?? null),
            noteIds: Cast::intList($row['noteIds'] ?? null),
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
