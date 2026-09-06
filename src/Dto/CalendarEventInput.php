<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Nowe wydarzenie kalendarza (`POST /v2/calendars/{id}/events`). Wymagane
 * `title`, `startAt` i `endAt` (ISO 8601 ze strefą). Wydarzenie zakłada się
 * w kontekście WŁAŚCICIELA kalendarza - to on figuruje jako organizator
 * zaproszenia.
 *
 * Uczestnicy z `attendees` dostają zaproszenia na podane adresy;
 * `sendNotifications = false` wyłącza wysyłkę zaproszeń i powiadomień
 * (domyślnie true po stronie API).
 *
 * Dostępne od wersji API 2.3.0.
 */
final readonly class CalendarEventInput implements Arrayable
{
    /**
     * @param string|null                                       $startAt           początek, ISO 8601 ze strefą
     *                                                                             (np. `2026-09-01T10:00:00+02:00`)
     * @param string|null                                       $endAt             koniec, ISO 8601 ze strefą
     * @param list<array{name?: string, email: string}>|null    $attendees         uczestnicy (dostają zaproszenia)
     * @param int|null                                          $contractorId      powiązany kontrahent
     * @param int|null                                          $contactId         powiązana osoba kontaktowa (od API 2.8.0)
     * @param int|null                                          $taskId            powiązane zadanie
     * @param int|null                                          $ticketId          powiązane zgłoszenie
     * @param int|null                                          $eventTypeId       typ wydarzenia ze słownika CRM
     * @param bool|null                                         $sendNotifications czy rozesłać zaproszenia (domyślnie true)
     */
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?string $location = null,
        public ?string $startAt = null,
        public ?string $endAt = null,
        public ?bool $allDay = null,
        public ?array $attendees = null,
        public ?int $contractorId = null,
        public ?int $contactId = null,
        public ?int $taskId = null,
        public ?int $ticketId = null,
        public ?int $eventTypeId = null,
        public ?bool $sendNotifications = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'title' => $this->title,
            'description' => $this->description,
            'location' => $this->location,
            'startAt' => $this->startAt,
            'endAt' => $this->endAt,
            'allDay' => $this->allDay,
            'attendees' => $this->attendees,
            'contractorId' => $this->contractorId,
            'contactId' => $this->contactId,
            'taskId' => $this->taskId,
            'ticketId' => $this->ticketId,
            'eventTypeId' => $this->eventTypeId,
            'sendNotifications' => $this->sendNotifications,
        ]);
    }
}
