<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Wydarzenie w kalendarzu (odczyt). Trzymane w usłudze kalendarzowej,
 * powiązania (kontrahent, typ wydarzenia) po stronie CRM.
 *
 * UWAGA: `id` jest STRINGIEM (UID w usłudze kalendarzowej), nie liczbą jak
 * w encjach CRM. Daty `startAt`/`endAt` w ISO 8601 ze strefą instancji
 * (jednolicie od API 2.3.1).
 *
 * Dostępne od wersji API 2.3.0.
 */
final readonly class CalendarEvent
{
    /**
     * @param array<string, mixed>|null $recurrenceRule reguła powtarzania (FREQ, INTERVAL,
     *                                                   BYDAY, UNTIL); null = jednorazowe
     * @param list<CalendarAttendee>    $attendees      uczestnicy z odpowiedziami na zaproszenie
     * @param array<string, mixed>      $raw            pełny rekord z API
     */
    public function __construct(
        public string $id,
        public ?string $title,
        public ?string $description,
        public ?string $location,
        public ?string $url,
        public ?string $startAt,
        public ?string $endAt,
        public ?array $recurrenceRule,
        public ?CalendarAttendee $organizer,
        public array $attendees,
        public ?int $contractorId,
        public ?int $contactId,
        public ?int $eventTypeId,
        public ?string $eventTypeName,
        public ?bool $completed,
        public ?string $participationStatus,
        public array $raw = [],
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $organizer = Cast::mapOrNull($row['organizer'] ?? null);

        return new self(
            id: Cast::requiredString($row['id'] ?? null),
            title: Cast::string($row['title'] ?? null),
            description: Cast::string($row['description'] ?? null),
            location: Cast::string($row['location'] ?? null),
            url: Cast::string($row['url'] ?? null),
            startAt: Cast::string($row['startAt'] ?? null),
            endAt: Cast::string($row['endAt'] ?? null),
            recurrenceRule: Cast::mapOrNull($row['recurrenceRule'] ?? null),
            organizer: $organizer === null ? null : CalendarAttendee::fromArray($organizer),
            attendees: array_map(CalendarAttendee::fromArray(...), Cast::rows($row['attendees'] ?? null)),
            contractorId: Cast::int($row['contractorId'] ?? null),
            contactId: Cast::int($row['contactId'] ?? null),
            eventTypeId: Cast::int($row['eventTypeId'] ?? null),
            eventTypeName: Cast::string($row['eventTypeName'] ?? null),
            completed: Cast::bool($row['completed'] ?? null),
            participationStatus: Cast::string($row['participationStatus'] ?? null),
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
