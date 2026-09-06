<?php

declare(strict_types=1);

namespace TillioCrm\Api\Resources;

use TillioCrm\Api\Dto\Calendar;
use TillioCrm\Api\Dto\CalendarEvent;
use TillioCrm\Api\Dto\CalendarEventInput;
use TillioCrm\Api\Page;

/**
 * Kalendarze i ich wydarzenia. Odpowiedź na pytanie "gdzie wpisać spotkanie
 * tego handlowca": lista kalendarzy niesie dostępy użytkowników z flagą
 * głównego kalendarza ({@see Calendar::isMainFor()}).
 *
 * Wymaga API >= 2.2.0 (wydarzenia: >= 2.3.0). Instalacja bez modułu
 * kalendarza odpowiada na trasy wydarzeń wyjątkiem
 * {@see \TillioCrm\Api\Exception\ServiceUnavailableException}
 * (503 `calendar.serviceUnavailable`, bez retry - deterministyczne);
 * lista samych kalendarzy działa niezależnie od modułu.
 */
final readonly class Calendars extends Resource
{
    /**
     * `GET /v2/calendars` - strona listy kalendarzy z dostępami użytkowników.
     *
     * Filtry (komplet wg kontraktu): `id`, `name`, `calendarTypeId`
     * (słownik `Dictionaries::calendarTypes()`), `ownerUserId`,
     * `mailAccountId`, `email`, `oauthEmail`, `oauthAuthorized`,
     * `allowExternalEvents`, `active`, `sort`/`sortDir`, `page`/`limit`.
     *
     * @param array<string, mixed> $filters
     *
     * @return Page<Calendar>
     */
    public function list(array $filters = []): Page
    {
        return self::mapPage($this->client->get('v2/calendars', $filters), Calendar::fromArray(...));
    }

    /**
     * Pełny przebieg wszystkich stron (generator, wymuszone `sort=id`).
     *
     * @param array<string, mixed> $filters
     *
     * @return \Generator<int, Calendar>
     */
    public function iterate(array $filters = [], int $pageSize = 1000): \Generator
    {
        return $this->iterateMapped('v2/calendars', Calendar::fromArray(...), $filters, $pageSize);
    }

    /**
     * `GET /v2/calendars/{id}` - pojedynczy kalendarz (trasa nie przyjmuje
     * parametrów).
     */
    public function get(int $id): Calendar
    {
        return Calendar::fromArray(self::single($this->client->get('v2/calendars/' . $id)));
    }

    /**
     * `GET /v2/calendars/{id}/events` - wydarzenia kalendarza w zakresie dat
     * (bez zakresu API zwraca najbliższe 30 dni). Lista bez stronicowania.
     *
     * @param \DateTimeInterface|string|null $dateFrom początek zakresu (ISO 8601)
     * @param \DateTimeInterface|string|null $dateTo   koniec zakresu (ISO 8601)
     *
     * @return list<CalendarEvent>
     */
    public function events(int $calendarId, \DateTimeInterface|string|null $dateFrom = null, \DateTimeInterface|string|null $dateTo = null): array
    {
        return self::mapList(
            $this->client->get(
                sprintf('v2/calendars/%d/events', $calendarId),
                ['dateFrom' => $dateFrom, 'dateTo' => $dateTo],
            ),
            CalendarEvent::fromArray(...),
        );
    }

    /**
     * `POST /v2/calendars/{id}/events` - nowe wydarzenie w kalendarzu
     * (wymagane `title`, `startAt`, `endAt`). Wydarzenie zakłada się
     * w kontekście WŁAŚCICIELA kalendarza - to on jest organizatorem
     * zaproszenia. Zwraca założone wydarzenie (z `id` będącym STRINGIEM -
     * UID usługi kalendarzowej).
     *
     *     $event = $client->calendars()->createEvent(7, new CalendarEventInput(
     *         title: 'Spotkanie wdrożeniowe',
     *         startAt: '2026-09-10T10:00:00+02:00',
     *         endAt: '2026-09-10T11:00:00+02:00',
     *         attendees: [['name' => 'Jan Kowalski', 'email' => 'jan@przyklad.example']],
     *         contractorId: 42,
     *     ));
     *
     * @param CalendarEventInput|array<string, mixed> $input
     */
    public function createEvent(int $calendarId, CalendarEventInput|array $input): CalendarEvent
    {
        return CalendarEvent::fromArray(self::single(
            $this->client->post(sprintf('v2/calendars/%d/events', $calendarId), self::payload($input)),
        ));
    }
}
