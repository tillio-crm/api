<?php

declare(strict_types=1);

namespace TillioCrm\Api\Tests;

use PHPUnit\Framework\TestCase;
use TillioCrm\Api\Dto\CalendarAttendee;
use TillioCrm\Api\Dto\CalendarEventInput;
use TillioCrm\Api\Dto\CalendarUser;
use TillioCrm\Api\Tests\Support\FakeClock;
use TillioCrm\Api\Tests\Support\MockTransport;
use TillioCrm\Api\TillioClient;

/**
 * Kalendarze (API 2.2.0+) i wydarzenia (2.3.0+): ścieżki/query, mapowanie
 * dostępów użytkowników z flagą kalendarza głównego, STRINGOWE id wydarzeń
 * i payload tworzenia wydarzenia bez null-i.
 */
final class CalendarResourcesTest extends TestCase
{
    private MockTransport $transport;

    protected function setUp(): void
    {
        $this->transport = new MockTransport();
    }

    private function client(): TillioClient
    {
        return new TillioClient([
            'apiKey' => 'Test:klucz',
            'tenantDomain' => 'firma.tillio.app',
            'tenantId' => 'firma-abc123',
            'rateLimits' => [],
            'transport' => $this->transport,
            'clock' => new FakeClock(),
        ]);
    }

    public function testListBuildsQueryAndMapsUsersWithMainFlag(): void
    {
        $this->transport->queueJson(200, '{"data":[{"id":3,"name":"Kalendarz Jana","calendarTypeId":1,"ownerUserId":7,"oauthAuthorized":false,"active":true,"users":[{"userId":7,"main":true,"admin":1,"accessTo":"full"},{"userId":8,"main":false}]}],"pagination":{"page":1,"limit":25,"total":1,"pages":1}}');

        $page = $this->client()->calendars()->list(['ownerUserId' => 7, 'active' => true]);

        $request = $this->transport->lastRequest();
        self::assertSame('GET', $request->method);
        self::assertSame('v2/calendars', $request->path);
        // QueryBuilder: int jako string, bool jako 1/0.
        self::assertSame(['ownerUserId' => '7', 'active' => '1'], $request->query);

        $calendar = $page->first();
        self::assertNotNull($calendar);
        self::assertSame(3, $calendar->id);
        self::assertSame('Kalendarz Jana', $calendar->name);
        self::assertCount(2, $calendar->users);
        self::assertInstanceOf(CalendarUser::class, $calendar->users[0]);
        self::assertSame(7, $calendar->users[0]->userId);

        // Kalendarz główny: tylko dostęp z main=true, nie sama obecność na liście.
        self::assertTrue($calendar->isMainFor(7));
        self::assertFalse($calendar->isMainFor(8));
        self::assertFalse($calendar->isMainFor(999));
    }

    public function testGetSingleCalendarWithoutQuery(): void
    {
        $this->transport->queueJson(200, '{"data":{"id":7,"name":"Zespół handlowy","users":[]}}');

        $calendar = $this->client()->calendars()->get(7);

        $request = $this->transport->lastRequest();
        self::assertSame('GET', $request->method);
        self::assertSame('v2/calendars/7', $request->path);
        // Trasa nie przyjmuje parametrów - query musi być puste.
        self::assertSame([], $request->query);
        self::assertSame(7, $calendar->id);
        self::assertSame([], $calendar->users);
    }

    public function testEventsSerializesDateFromAndSkipsNullDateTo(): void
    {
        $this->transport->queueJson(200, '{"data":[{"id":"evt-uid-123","title":"Spotkanie","startAt":"2026-09-10T10:00:00+02:00","endAt":"2026-09-10T11:00:00+02:00","recurrenceRule":{"FREQ":"WEEKLY","INTERVAL":1},"organizer":{"name":"Jan Kowalski","email":"jan@firma.pl"},"attendees":[{"name":"Anna Nowak","email":"anna@acme.pl","status":"ACCEPTED"}],"contractorId":42,"completed":false}]}');

        $dateFrom = new \DateTimeImmutable('2026-09-01 08:00:00', new \DateTimeZone('Europe/Warsaw'));
        $events = $this->client()->calendars()->events(7, $dateFrom, null);

        $request = $this->transport->lastRequest();
        self::assertSame('v2/calendars/7/events', $request->path);
        // Data przez QueryBuilder w ISO 8601 ze strefą; null-owe dateTo wycięte.
        self::assertSame(['dateFrom' => '2026-09-01T08:00:00+02:00'], $request->query);

        self::assertCount(1, $events);
        $event = $events[0];
        // id wydarzenia to STRING (UID usługi kalendarzowej), nie liczba CRM.
        self::assertSame('evt-uid-123', $event->id);
        self::assertInstanceOf(CalendarAttendee::class, $event->organizer);
        self::assertSame('jan@firma.pl', $event->organizer->email);
        self::assertInstanceOf(CalendarAttendee::class, $event->attendees[0]);
        self::assertSame('ACCEPTED', $event->attendees[0]->status);
        self::assertSame(['FREQ' => 'WEEKLY', 'INTERVAL' => 1], $event->recurrenceRule);
        self::assertSame(42, $event->contractorId);
        self::assertFalse($event->completed);
    }

    public function testCreateEventPostsBodyWithoutNulls(): void
    {
        $this->transport->queueJson(201, '{"data":{"id":"evt-new-9","title":"Spotkanie wdrożeniowe","startAt":"2026-09-10T10:00:00+02:00","endAt":"2026-09-10T11:00:00+02:00","attendees":[{"email":"jan@przyklad.example","status":"NEEDS-ACTION"}]}}');

        $event = $this->client()->calendars()->createEvent(7, new CalendarEventInput(
            title: 'Spotkanie wdrożeniowe',
            startAt: '2026-09-10T10:00:00+02:00',
            endAt: '2026-09-10T11:00:00+02:00',
            attendees: [['name' => 'Jan Kowalski', 'email' => 'jan@przyklad.example']],
            contractorId: 42,
        ));

        $request = $this->transport->lastRequest();
        self::assertSame('POST', $request->method);
        self::assertSame('v2/calendars/7/events', $request->path);
        // Pola pominięte w inpucie (description, allDay, sendNotifications...)
        // nie mogą wyjść jako null-e.
        self::assertSame([
            'title' => 'Spotkanie wdrożeniowe',
            'startAt' => '2026-09-10T10:00:00+02:00',
            'endAt' => '2026-09-10T11:00:00+02:00',
            'attendees' => [['name' => 'Jan Kowalski', 'email' => 'jan@przyklad.example']],
            'contractorId' => 42,
        ], $request->body);

        self::assertSame('evt-new-9', $event->id);
        self::assertSame('NEEDS-ACTION', $event->attendees[0]->status);
    }
}
