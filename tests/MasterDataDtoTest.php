<?php

declare(strict_types=1);

namespace TillioCrm\Api\Tests;

use PHPUnit\Framework\TestCase;
use TillioCrm\Api\ApiResponse;
use TillioCrm\Api\Dto\AddressInput;
use TillioCrm\Api\Dto\CalendarEvent;
use TillioCrm\Api\Dto\CalendarEventInput;
use TillioCrm\Api\Dto\CategoryInput;
use TillioCrm\Api\Dto\Contact;
use TillioCrm\Api\Dto\Contractor;
use TillioCrm\Api\Dto\ContractorInput;
use TillioCrm\Api\Dto\MailTemplateInput;
use TillioCrm\Api\Dto\Note;
use TillioCrm\Api\Dto\NoteInput;
use TillioCrm\Api\Dto\OrderInput;
use TillioCrm\Api\Dto\PhoneCallInput;
use TillioCrm\Api\Dto\Task;
use TillioCrm\Api\Dto\TaskCommentInput;
use TillioCrm\Api\Dto\TaskInput;
use TillioCrm\Api\Dto\TaskTemplateInput;
use TillioCrm\Api\Dto\OrderProductInput;
use TillioCrm\Api\Dto\TextMessageInput;
use TillioCrm\Api\Dto\UpsertResult;
use TillioCrm\Api\Dto\WriteOptions;
use TillioCrm\Api\Dto\WriteResult;

/**
 * Konwencje DTO: odczyt z `raw` (nowe pola nie giną), input pomija null-e,
 * WriteResult rozróżnia 201-utworzono od 200-duplikat.
 */
final class MasterDataDtoTest extends TestCase
{
    public function testContractorKeepsUnknownFieldsInRaw(): void
    {
        $row = [
            'id' => 950201,
            'name' => 'Acme',
            'taxId' => '0000000000',
            'ownerUserId' => 7,
            'customField' => ['erp_id' => 'K-42'],
            'address' => [['id' => 7, 'addressTypeId' => 1, 'city' => 'Warszawa']],
            'revenue' => '123456789.99',
            'poleZNowegoWydaniaApi' => 'wartość',
        ];

        $dto = Contractor::fromArray($row);

        self::assertSame(950201, $dto->id);
        self::assertSame('Acme', $dto->name);
        self::assertSame(7, $dto->ownerUserId);
        self::assertSame(['erp_id' => 'K-42'], $dto->customField);
        self::assertSame('Warszawa', $dto->address[0]->city);
        // Kwota zostaje stringiem - float zgubiłby grosze.
        self::assertSame('123456789.99', $dto->revenue);
        // Nowe pole API nie ginie i toArray() oddaje pełny oryginał.
        self::assertSame('wartość', $dto->raw['poleZNowegoWydaniaApi']);
        self::assertSame($row, $dto->toArray());
    }

    public function testContractorInputOmitsNulls(): void
    {
        $input = new ContractorInput(
            name: 'Acme',
            taxId: '0000000000',
            contractorTypeId: 1,
            address: [new AddressInput(addressTypeId: 1, city: 'Warszawa')],
        );

        self::assertSame([
            'name' => 'Acme',
            'taxId' => '0000000000',
            'contractorTypeId' => 1,
            'address' => [['addressTypeId' => 1, 'city' => 'Warszawa']],
        ], $input->toArray());
    }

    public function testCalendarEventInputOmitsNullsAndKeepsContractKeys(): void
    {
        $input = new CalendarEventInput(
            title: 'Spotkanie',
            startAt: '2026-09-10T10:00:00+02:00',
            endAt: '2026-09-10T11:00:00+02:00',
            allDay: false,
            attendees: [['email' => 'jan@acme.pl']],
            eventTypeId: 2,
            sendNotifications: false,
        );

        // Jawne false musi wyjść (wyłączenie zaproszeń), null-e nie.
        self::assertSame([
            'title' => 'Spotkanie',
            'startAt' => '2026-09-10T10:00:00+02:00',
            'endAt' => '2026-09-10T11:00:00+02:00',
            'allDay' => false,
            'attendees' => [['email' => 'jan@acme.pl']],
            'eventTypeId' => 2,
            'sendNotifications' => false,
        ], $input->toArray());
        self::assertSame([], (new CalendarEventInput())->toArray());
    }

    public function testTaskTemplateInputOmitsNulls(): void
    {
        $input = new TaskTemplateInput(
            categoryId: 3,
            name: 'Wdrożenie',
            alias: 'wdrozenie',
            assignedUserIds: [7, 8],
            tagIds: [],
            taskPriority: 0,
            active: false,
        );

        // Pusta lista i zera to wartości jawne - wycinamy wyłącznie null-e.
        self::assertSame([
            'categoryId' => 3,
            'name' => 'Wdrożenie',
            'alias' => 'wdrozenie',
            'assignedUserIds' => [7, 8],
            'tagIds' => [],
            'taskPriority' => 0,
            'active' => false,
        ], $input->toArray());
    }

    public function testMailTemplateInputKeepsDefaultKey(): void
    {
        $input = new MailTemplateInput(
            name: 'Follow up',
            subject: 'Oferta',
            cc: ['handel@firma.pl'],
            default: true,
        );

        // Klucz kontraktu to `default` - słowo kluczowe PHP nie może go zniekształcić.
        self::assertSame([
            'name' => 'Follow up',
            'subject' => 'Oferta',
            'cc' => ['handel@firma.pl'],
            'default' => true,
        ], $input->toArray());
    }

    public function testCategoryInputOmitsNulls(): void
    {
        self::assertSame(
            ['name' => 'Serwis', 'parentId' => 1],
            (new CategoryInput(name: 'Serwis', parentId: 1))->toArray(),
        );
        self::assertSame(
            ['priority' => 5],
            (new CategoryInput(priority: 5))->toArray(),
        );
    }

    public function testWriteOptionsOmitNullsButSendExplicitFalse(): void
    {
        self::assertSame(
            ['duplicateCheck' => ['taxId'], 'failOnInvalidTaxId' => false],
            (new WriteOptions(duplicateCheck: ['taxId'], failOnInvalidTaxId: false))->toArray(),
        );
        self::assertSame([], (new WriteOptions())->toArray());
    }

    public function testWriteResultDistinguishesDuplicateFromCreated(): void
    {
        $created = WriteResult::fromResponse(new ApiResponse(201, [
            'data' => ['id' => 5],
            'info' => ['created' => true, 'ids' => ['contractorId' => 5, 'noteId' => 9]],
        ]), 'contractorId');

        self::assertTrue($created->created);
        self::assertFalse($created->isDuplicate());
        self::assertSame(5, $created->id);
        self::assertSame(9, $created->ids['noteId']);

        $duplicate = WriteResult::fromResponse(new ApiResponse(200, [
            'data' => ['id' => 121],
            'info' => [
                'created' => false,
                'ids' => ['contractorId' => 121],
                'duplicate' => ['matchedBy' => 'custom:erp_id', 'contractorId' => 121],
                'warnings' => ['duplicateCheck' => ['pominięto warunek phone']],
            ],
        ]), 'contractorId');

        self::assertFalse($duplicate->created);
        self::assertTrue($duplicate->isDuplicate());
        self::assertSame('custom:erp_id', $duplicate->matchedBy());
        self::assertSame(121, $duplicate->duplicate?->id);
        self::assertArrayHasKey('duplicateCheck', $duplicate->warnings);
    }

    public function testWriteResultWithoutInfoTakesIdFromData(): void
    {
        $result = WriteResult::fromResponse(new ApiResponse(201, ['data' => ['id' => 44]]), 'addressId');

        self::assertSame(44, $result->id);
        self::assertTrue($result->created); // 201 bez info.created = utworzono
    }

    public function testUpsertResultAggregatesMultiStatus(): void
    {
        $result = UpsertResult::fromResponse(new ApiResponse(200, [
            'data' => ['results' => [
                ['index' => 0, 'status' => 'created', 'contractorId' => 1],
                ['index' => 1, 'status' => 'updated', 'contractorId' => 2, 'matchedBy' => 'taxId'],
                ['index' => 2, 'status' => 'failed', 'errors' => [['field' => 'name', 'code' => 'body.required', 'message' => 'Wymagane.']]],
            ]],
            'info' => ['summary' => ['total' => 3, 'created' => 1, 'updated' => 1, 'failed' => 1]],
        ]));

        self::assertTrue($result->hasFailures());
        self::assertSame(1, $result->createdCount());
        self::assertSame(1, $result->updatedCount());
        self::assertSame(1, $result->failedCount());
        self::assertSame('updated', $result->forIndex(1)['status'] ?? null);

        // Scalanie paczek z przesunięciem indeksów.
        $merged = $result->merge($result->withIndexOffset(3));
        self::assertSame(6, count($merged->results));
        self::assertSame(2, $merged->countOf('created'));
        self::assertNotNull($merged->forIndex(4));
    }

    public function testOrderInputMergesProductLines(): void
    {
        $input = new OrderInput(products: [
            new OrderProductInput(productSku: 'LIC-PRO', quantity: '2'),
            ['customName' => 'Usługa wdrożenia', 'price' => '1500.00'],
        ]);

        self::assertSame([
            'products' => [
                ['productSku' => 'LIC-PRO', 'quantity' => '2'],
                ['customName' => 'Usługa wdrożenia', 'price' => '1500.00'],
            ],
        ], $input->toArray());
    }

    public function testNoteInputCarriesRelationKeys(): void
    {
        $input = new NoteInput(
            noteTypeId: 1,
            title: 'Ustalenia',
            contactIds: [7, 8],
            contractorId: 42,
            serviceId: 5,
            pipelineItemId: 9,
        );

        // Wszystkie klucze relacji wychodzą; null-e (body, pinned, ...) nie.
        self::assertSame([
            'noteTypeId' => 1,
            'title' => 'Ustalenia',
            'contactIds' => [7, 8],
            'contractorId' => 42,
            'serviceId' => 5,
            'pipelineItemId' => 9,
        ], $input->toArray());
    }

    public function testTaskInputCarriesContactId(): void
    {
        $input = new TaskInput(title: 'Telefon', assignedUserIds: [7], contactId: 3);

        self::assertSame([
            'title' => 'Telefon',
            'assignedUserIds' => [7],
            'contactId' => 3,
        ], $input->toArray());
    }

    public function testCalendarEventInputCarriesContactId(): void
    {
        $input = new CalendarEventInput(
            title: 'Spotkanie',
            startAt: '2026-09-10T10:00:00+02:00',
            endAt: '2026-09-10T11:00:00+02:00',
            contactId: 3,
        );

        self::assertSame([
            'title' => 'Spotkanie',
            'startAt' => '2026-09-10T10:00:00+02:00',
            'endAt' => '2026-09-10T11:00:00+02:00',
            'contactId' => 3,
        ], $input->toArray());
    }

    public function testPhoneCallInputOmitsNullsKeepsExplicitValues(): void
    {
        $input = new PhoneCallInput(
            source: 'tillio',
            sourceId: 'CALL-1',
            direction: 'inbound',
            status: 'answered',
            remoteNumber: '+48601234567',
            startedAt: '2026-09-01T10:00:00+02:00',
            duration: 0,
        );

        // duration 0 to wartość jawna - wycinamy wyłącznie null-e.
        self::assertSame([
            'source' => 'tillio',
            'sourceId' => 'CALL-1',
            'direction' => 'inbound',
            'status' => 'answered',
            'remoteNumber' => '+48601234567',
            'startedAt' => '2026-09-01T10:00:00+02:00',
            'duration' => 0,
        ], $input->toArray());
        self::assertSame([], (new PhoneCallInput())->toArray());
    }

    public function testTextMessageInputOmitsNullsKeepsExplicitValues(): void
    {
        $input = new TextMessageInput(
            source: 'smsapi',
            sourceId: 'SMS-1',
            direction: 'outbound',
            status: 'sent',
            remoteNumber: '+48601234567',
            body: 'Treść',
            sentAt: '2026-09-01T10:00:00+02:00',
        );

        self::assertSame([
            'source' => 'smsapi',
            'sourceId' => 'SMS-1',
            'direction' => 'outbound',
            'status' => 'sent',
            'remoteNumber' => '+48601234567',
            'body' => 'Treść',
            'sentAt' => '2026-09-01T10:00:00+02:00',
        ], $input->toArray());
        self::assertSame([], (new TextMessageInput())->toArray());
    }

    public function testTaskCommentInputOmitsNulls(): void
    {
        self::assertSame(
            ['body' => '<p>x</p>'],
            (new TaskCommentInput(body: '<p>x</p>'))->toArray(),
        );
        self::assertSame(
            ['body' => '<p>x</p>', 'parentCommentId' => 3],
            (new TaskCommentInput(body: '<p>x</p>', parentCommentId: 3))->toArray(),
        );
        self::assertSame([], (new TaskCommentInput())->toArray());
    }

    public function testNoteReadsContactIds(): void
    {
        // Listy id znoszą stringi z API (kanon list<int>).
        $note = Note::fromArray(['id' => 9, 'noteTypeId' => 1, 'title' => 'x', 'contactIds' => ['7', '8']]);

        self::assertSame([7, 8], $note->contactIds);
        // Brak pola = pusta lista, nie null.
        self::assertSame([], Note::fromArray(['id' => 10])->contactIds);
    }

    /**
     * `url` przyszło z API 2.12.0 - link "otwórz w CRM". Na starszych instancjach
     * pola nie ma i musi wyjść null, a nie pusty string.
     */
    public function testNoteAndContactReadCrmUrl(): void
    {
        $note = Note::fromArray(['id' => 9, 'url' => 'https://firma.tillio.app/crm/contractors/121/#/tab=company/activities&noteId=904']);
        self::assertSame('https://firma.tillio.app/crm/contractors/121/#/tab=company/activities&noteId=904', $note->url);
        self::assertNull(Note::fromArray(['id' => 10])->url);

        $contact = Contact::fromArray(['id' => 50, 'url' => 'https://firma.tillio.app/crm/contractors/121/#/modal=contact-read/contactId:50']);
        self::assertSame('https://firma.tillio.app/crm/contractors/121/#/modal=contact-read/contactId:50', $contact->url);
        // Kontakt bez powiązanej firmy nie ma gdzie się otworzyć - API oddaje null.
        self::assertNull(Contact::fromArray(['id' => 51, 'url' => null])->url);
    }

    public function testTaskReadsContactId(): void
    {
        $task = Task::fromArray(['id' => 5, 'title' => 'x', 'contactId' => 3]);
        self::assertSame(3, $task->contactId);
        self::assertNull(Task::fromArray(['id' => 6])->contactId);
    }

    public function testCalendarEventReadsContactId(): void
    {
        $event = CalendarEvent::fromArray(['id' => 'uid-1', 'title' => 'Spotkanie', 'contactId' => 3]);
        self::assertSame(3, $event->contactId);
        self::assertNull(CalendarEvent::fromArray(['id' => 'uid-2'])->contactId);
    }
}
