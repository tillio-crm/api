<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Zgłoszenie (odczyt). `ticketStageId` wskazuje etap w procesie obsługi
 * (procesy: `GET /v2/ticket/processes`). Pełny payload w `$raw`.
 */
final readonly class Ticket
{
    /**
     * @param string|null          $uuId        nazwa pola wg kontraktu API (uuId)
     * @param array<string, mixed> $customField wartości pól niestandardowych
     * @param array<string, mixed> $raw         pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $title,
        public ?string $description,
        public ?int $ticketStatusId,
        public ?int $ticketSourceId,
        public ?int $ticketStageId,
        public ?int $priority,
        public ?int $contractorId,
        public ?int $contactId,
        public ?int $serviceId,
        public ?int $ownerUserId,
        public ?int $creatorUserId,
        public ?int $lastResponseUserId,
        public ?string $email,
        public ?bool $open,
        public ?bool $archived,
        public ?int $estimatedTime,
        public ?int $relatedTicketId,
        public ?string $uuId,
        public ?string $clientPanelUrl,
        public ?string $resolutionAt,
        public ?string $endedAt,
        public ?string $lastResponseAt,
        public ?string $createdAt,
        public ?string $updatedAt,
        public array $customField,
        public ?string $url,
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
            title: Cast::string($row['title'] ?? null),
            description: Cast::string($row['description'] ?? null),
            ticketStatusId: Cast::int($row['ticketStatusId'] ?? null),
            ticketSourceId: Cast::int($row['ticketSourceId'] ?? null),
            ticketStageId: Cast::int($row['ticketStageId'] ?? null),
            priority: Cast::int($row['priority'] ?? null),
            contractorId: Cast::int($row['contractorId'] ?? null),
            contactId: Cast::int($row['contactId'] ?? null),
            serviceId: Cast::int($row['serviceId'] ?? null),
            ownerUserId: Cast::int($row['ownerUserId'] ?? null),
            creatorUserId: Cast::int($row['creatorUserId'] ?? null),
            lastResponseUserId: Cast::int($row['lastResponseUserId'] ?? null),
            email: Cast::string($row['email'] ?? null),
            // API >= 2.0.4 zwraca tu prawdziwe boole; instancje 2.0.0-2.0.3
            // oddawały stringi "0"/"1" - Cast::bool obsługuje oba warianty.
            open: Cast::bool($row['open'] ?? null),
            archived: Cast::bool($row['archived'] ?? null),
            estimatedTime: Cast::int($row['estimatedTime'] ?? null),
            relatedTicketId: Cast::int($row['relatedTicketId'] ?? null),
            uuId: Cast::string($row['uuId'] ?? null),
            clientPanelUrl: Cast::string($row['clientPanelUrl'] ?? null),
            resolutionAt: Cast::string($row['resolutionAt'] ?? null),
            endedAt: Cast::string($row['endedAt'] ?? null),
            lastResponseAt: Cast::string($row['lastResponseAt'] ?? null),
            createdAt: Cast::string($row['createdAt'] ?? null),
            updatedAt: Cast::string($row['updatedAt'] ?? null),
            customField: Cast::map($row['customField'] ?? null),
            url: Cast::string($row['url'] ?? null),
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
