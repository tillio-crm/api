<?php

declare(strict_types=1);

namespace TillioCrm\Api\Tests;

use PHPUnit\Framework\TestCase;
use TillioCrm\Api\Dto\CategoryInput;
use TillioCrm\Api\Dto\LeadInput;
use TillioCrm\Api\Dto\NoteContact;
use TillioCrm\Api\Dto\NoteInput;
use TillioCrm\Api\Dto\NoteTemplateInput;
use TillioCrm\Api\Dto\PipelineItemInput;
use TillioCrm\Api\Dto\ServiceInput;
use TillioCrm\Api\Dto\SystemUser;
use TillioCrm\Api\Dto\TaskComment;
use TillioCrm\Api\Dto\TaskCommentInput;
use TillioCrm\Api\Dto\TaskInput;
use TillioCrm\Api\Dto\TaskTemplateInput;
use TillioCrm\Api\Dto\TemplateCategory;
use TillioCrm\Api\Dto\UserActivity;
use TillioCrm\Api\Dto\TicketInput;
use TillioCrm\Api\Dto\TicketMessageInput;
use TillioCrm\Api\Dto\UserInput;
use TillioCrm\Api\Dto\WriteOptions;
use TillioCrm\Api\Exception\IncompleteDuplicateCheckException;
use TillioCrm\Api\Exception\TransportException;
use TillioCrm\Api\Exception\ValidationException;
use TillioCrm\Api\Tests\Support\FakeClock;
use TillioCrm\Api\Tests\Support\MockTransport;
use TillioCrm\Api\TillioClient;
use TillioCrm\Api\Transport\FileUpload;

/**
 * Zasoby CRM: ścieżki/payloady, DTO, upload załączników multipart
 * i nieponawialny POST /v2/users.
 */
final class CrmResourcesTest extends TestCase
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

    /** Koperta błędu walidacji z jednym polem - kontrakt `_error.errors`. */
    private static function validationJson(string $field, string $message): string
    {
        return (string) json_encode(['_error' => [
            'code' => 422,
            'message' => 'validation.error',
            'errors' => [['field' => $field, 'code' => 'body.invalidValue', 'message' => $message]],
        ]]);
    }

    public function testNoteCreatedUnderContractor(): void
    {
        $this->transport->queueJson(201, '{"data":{"id":11},"info":{"created":true,"ids":{"noteId":11}}}');

        $result = $this->client()->notes()->create(42, new NoteInput(noteTypeId: 1, title: 'Rozmowa'));

        self::assertSame('v2/contractors/42/notes', $this->transport->lastRequest()->path);
        self::assertSame(['noteTypeId' => 1, 'title' => 'Rozmowa'], $this->transport->lastRequest()->body);
        self::assertSame(11, $result->id);
    }

    public function testNoteAttachmentUsesMultipartWithoutRetry(): void
    {
        // Upload nie jest idempotentny - nawet błąd transportu nie może wywołać
        // drugiej próby (druga kopia pliku zostałaby w CRM na zawsze).
        $this->transport->queueThrowable(new TransportException('timeout'));

        try {
            $this->client()->notes()->addAttachment(11, FileUpload::fromString('%PDF', 'oferta.pdf', 'application/pdf'));
            self::fail('Oczekiwano TransportException.');
        } catch (TransportException) {
            self::assertCount(1, $this->transport->requests);
        }

        $request = $this->transport->lastRequest();
        self::assertSame('v2/notes/11/attachments', $request->path);
        self::assertArrayHasKey('file', $request->files);
        self::assertSame('oferta.pdf', $request->files['file']->fileName);
    }

    public function testTicketWithMessageThread(): void
    {
        $this->transport
            ->queueJson(201, '{"data":{"id":5},"info":{"created":true,"ids":{"ticketId":5}}}')
            ->queueJson(200, '{"data":[{"id":1,"visibility":"public","text":"Dzień dobry","toEmails":["jan@acme.pl"]}]}')
            ->queueJson(201, '{"data":{"id":2},"info":{"ids":{"messageId":2}}}');

        $client = $this->client();

        $created = $client->tickets()->create(new TicketInput(title: 'Awaria', email: 'jan@acme.pl'));
        self::assertSame('v2/tickets', $this->transport->lastRequest()->path);
        self::assertSame(5, $created->id);

        $messages = $client->tickets()->messages(5);
        self::assertSame('v2/tickets/5/messages', $this->transport->lastRequest()->path);
        self::assertSame(['jan@acme.pl'], $messages[0]->toEmails);

        $client->tickets()->addMessage(5, new TicketMessageInput(text: 'Przyjęliśmy', visibility: 'public'));
        self::assertSame('POST', $this->transport->lastRequest()->method);
        self::assertSame(['text' => 'Przyjęliśmy', 'visibility' => 'public'], $this->transport->lastRequest()->body);
    }

    public function testTaskWithCommentsAndAttachments(): void
    {
        $this->transport
            ->queueJson(201, '{"data":{"id":7},"info":{"created":true,"ids":{"taskId":7}}}')
            ->queueJson(200, '{"data":[{"id":1,"body":"Komentarz"}]}')
            ->queueJson(200, '{"data":[{"id":9,"fileName":"specyfikacja.pdf","commentId":1,"downloadUrl":"https://storage.example.test/x?sig=1"}]}');

        $client = $this->client();

        $created = $client->tasks()->create(new TaskInput(title: 'Wdrożenie', assignedUserIds: [7]));
        self::assertSame(['title' => 'Wdrożenie', 'assignedUserIds' => [7]], $this->transport->requests[0]->body);
        self::assertSame(7, $created->id);

        $comments = $client->tasks()->comments(7);
        self::assertSame('v2/tasks/7/comments', $this->transport->lastRequest()->path);
        self::assertSame('Komentarz', $comments[0]->body);

        $attachments = $client->tasks()->attachments(7);
        self::assertSame(1, $attachments[0]->commentId);
        self::assertNotNull($attachments[0]->downloadUrl);
    }

    public function testTaskUpdateSendsExplicitNullToUnpinPipelineItem(): void
    {
        // Odpięcie szansy to jawny null w PUT (kontrakt 2.14.1: pipelineItemId nullable).
        // TaskInput pomija null-e, więc jedyna droga to tablica - klucz z wartością
        // null musi dojechać w body, a nie zniknąć po drodze.
        $this->transport->queueJson(200, '{"data":{"id":7,"pipelineItemId":null},"info":{"ids":{"taskId":7}}}');

        $this->client()->tasks()->update(7, ['pipelineItemId' => null]);

        $request = $this->transport->lastRequest();
        self::assertSame('PUT', $request->method);
        self::assertSame('v2/tasks/7', $request->path);
        self::assertSame(['pipelineItemId' => null], $request->body);
    }

    public function testLeadProjectPipelineItemAndService(): void
    {
        $this->transport
            ->queueJson(200, '{"data":[{"id":1,"title":"Lead z formularza","emails":["a@b.pl"]}],"pagination":{"page":1,"limit":25,"total":1,"pages":1}}')
            ->queueJson(201, '{"data":{"id":2},"info":{"created":true,"ids":{"projectId":2}}}')
            ->queueJson(201, '{"data":{"id":3},"info":{"created":true,"ids":{"pipelineItemId":3}}}')
            ->queueJson(201, '{"data":{"id":4},"info":{"created":true,"ids":{"serviceId":4}}}');

        $client = $this->client();

        $lead = $client->leads()->list()->first();
        self::assertSame(['a@b.pl'], $lead?->emails);

        self::assertSame(2, $client->projects()->create(['name' => 'Projekt X'])->id);

        $pipeline = $client->pipelineItems()->create(new PipelineItemInput(
            name: 'Oferta',
            pipelineStageId: 9,
            contractorId: 42,
            amount: '15000.00',
        ));
        self::assertSame(3, $pipeline->id);
        self::assertSame('v2/pipeline/items', $this->transport->lastRequest()->path);

        $service = $client->services()->create(new ServiceInput(catalogId: 5, contractorId: 42, payValue: '99.00'));
        self::assertSame(4, $service->id);
    }

    public function testLeadCreateSendsWriteOptionsAndReadsAttach(): void
    {
        // Od API 2.13.0 POST leada to create-or-attach: trafienie = 200 z istniejącym
        // leadem. Duplikat skonwertowanego leada niesie też contractorId - id
        // znalezionego rekordu to nadal lead, nie kontrahent.
        $this->transport->queueJson(200, '{"data":{"id":1532,"title":"A"},"info":{"created":false,"duplicate":{"matchedBy":"email","contractorId":5577,"leadId":1532},"ids":{"leadId":1532}}}');

        $result = $this->client()->leads()->create(
            new LeadInput(title: 'Formularz', phone: '+48 601 234 567', emails: ['jan@acme.pl']),
            new WriteOptions(duplicateCheck: ['email', 'phone']),
        );

        $request = $this->transport->lastRequest();
        self::assertSame('v2/leads', $request->path);
        self::assertSame([
            'title' => 'Formularz',
            'phone' => '+48 601 234 567',
            'emails' => ['jan@acme.pl'],
            'duplicateCheck' => ['email', 'phone'],
        ], $request->body);
        self::assertFalse($result->created);
        self::assertTrue($result->isDuplicate());
        self::assertSame('email', $result->matchedBy());
        self::assertSame(1532, $result->id);
        self::assertSame(1532, $result->duplicate?->id);
        self::assertSame(5577, $result->duplicate?->raw['contractorId'] ?? null);
    }

    public function testLeadGuardReadsEmailFromEmailsList(): void
    {
        // Warunek `email` API sprawdza po liście `emails` - lead nie ma pola `email`.
        // Strażnik patrzy tam samo, a pusta lista to brak wartości.
        $this->transport->queueJson(201, '{"data":{"id":1},"info":{"created":true,"ids":{"leadId":1}}}');
        $this->client()->leads()->create(new LeadInput(title: 'A', emails: ['a@acme.pl']), new WriteOptions(duplicateCheck: ['email']));
        self::assertCount(1, $this->transport->requests);

        try {
            $this->client()->leads()->create(
                new LeadInput(title: 'B', phone: '601234567', emails: []),
                new WriteOptions(duplicateCheck: ['email', 'phone']),
            );
            self::fail('Oczekiwano IncompleteDuplicateCheckException.');
        } catch (IncompleteDuplicateCheckException $e) {
            self::assertSame(['email'], $e->missingFields);
            self::assertCount(1, $this->transport->requests);
        }
    }

    public function testLeadUpsertBuildsBatchAndCountsAttached(): void
    {
        $this->transport->queueJson(200, '{"data":{"results":[{"index":0,"status":"attached","leadId":1537,"matchedBy":"phone"},{"index":1,"status":"created","leadId":1538}]},"info":{"summary":{"total":2,"created":1,"attached":1,"failed":0}}}');

        $result = $this->client()->leads()->upsert([
            new LeadInput(title: 'A', phone: '+48601800001'),
            new LeadInput(title: 'B', emails: ['b@acme.pl']),
        ]);

        $request = $this->transport->lastRequest();
        self::assertSame('POST', $request->method);
        self::assertSame('v2/leads/upsert', $request->path);
        self::assertSame(['items' => [
            ['title' => 'A', 'phone' => '+48601800001'],
            ['title' => 'B', 'emails' => ['b@acme.pl']],
        ]], $request->body);
        self::assertSame(1, $result->attachedCount());
        self::assertSame(1, $result->createdCount());
        self::assertFalse($result->hasFailures());
    }

    public function testLeadInputSendsEmptyEmailsList(): void
    {
        // W PUT `emails: []` usuwa wszystkie adresy - pusta lista to wartość jawna
        // i musi wyjść w payloadzie; pomijamy wyłącznie null.
        self::assertSame(['emails' => []], (new LeadInput(emails: []))->toArray());
        self::assertSame([], (new LeadInput())->toArray());
    }

    public function testLeadCreateNotePostsUnderLead(): void
    {
        $this->transport->queueJson(201, '{"data":{"id":904,"leadId":659,"contractorId":null},"info":{"created":true,"ids":{"noteId":904}}}');

        $result = $this->client()->leads()->createNote(659, new NoteInput(noteTypeId: 1, title: 'Rozmowa kwalifikacyjna'));

        $request = $this->transport->lastRequest();
        self::assertSame('POST', $request->method);
        self::assertSame('v2/leads/659/notes', $request->path);
        self::assertSame(['noteTypeId' => 1, 'title' => 'Rozmowa kwalifikacyjna'], $request->body);
        self::assertSame(904, $result->id);
    }

    public function testLeadWriteCarriesCategoryRegionAndTags(): void
    {
        // Pola zapisu z kontraktu 2.15.0. `leadTagIds: []` to wartość jawna
        // (PUT zdejmuje wszystkie tagi), więc musi dojechać - pomijamy tylko null.
        self::assertSame([
            'title' => 'Acme',
            'region' => 'mazowieckie',
            'district' => 'pruszkowski',
            'categoryId' => 3,
            'leadTagIds' => [12, 15],
        ], (new LeadInput(
            title: 'Acme',
            region: 'mazowieckie',
            district: 'pruszkowski',
            categoryId: 3,
            leadTagIds: [12, 15],
        ))->toArray());

        self::assertSame(['leadTagIds' => []], (new LeadInput(leadTagIds: []))->toArray());
    }

    public function testLeadReadsTagsCategoryAndDistrict(): void
    {
        $this->transport->queueJson(200, '{"data":{"id":659,"title":"Acme","categoryId":3,"region":"mazowieckie","district":"pruszkowski","leadTagIds":[12,15]}}');

        $lead = $this->client()->leads()->get(659);

        self::assertSame([12, 15], $lead->leadTagIds);
        self::assertSame(3, $lead->categoryId);
        self::assertSame('pruszkowski', $lead->district);
    }

    public function testLeadUpdateClearsCategoryWithExplicitNull(): void
    {
        // Zdjęcie kategorii to jawny null w PUT. LeadInput pomija null-e, więc
        // jedyna droga to tablica - klucz z wartością null musi dojechać w body.
        $this->transport->queueJson(200, '{"data":{"id":659,"categoryId":null},"info":{"ids":{"leadId":659}}}');

        $this->client()->leads()->update(659, ['categoryId' => null]);

        $request = $this->transport->lastRequest();
        self::assertSame('PUT', $request->method);
        self::assertSame('v2/leads/659', $request->path);
        self::assertSame(['categoryId' => null], $request->body);
    }

    public function testLeadChangeStatusPostsReasonAndNote(): void
    {
        $this->transport->queueJson(200, '{"data":{"id":659,"leadStatusId":403,"closedAt":"2026-09-18T10:00:00+02:00"},"info":{"ids":{"leadId":659}}}');

        $result = $this->client()->leads()->changeStatus(659, 403, 401, 'Klient wybral oferte konkurencji');

        $request = $this->transport->lastRequest();
        self::assertSame('POST', $request->method);
        self::assertSame('v2/leads/659/status', $request->path);
        self::assertSame([
            'leadStatusId' => 403,
            'statusChangeReasonId' => 401,
            'note' => 'Klient wybral oferte konkurencji',
        ], $request->body);
        self::assertSame(659, $result->id);
        self::assertSame(403, $result->data['leadStatusId'] ?? null);
    }

    public function testLeadChangeStatusSendsStatusAloneWhenReasonOmitted(): void
    {
        // Powód i notatka wolno podać TYLKO przy statusie kończącym - przy zwykłym
        // przejściu nie mogą wyjść w body, bo API odbija je błędem 422.
        $this->transport->queueJson(200, '{"data":{"id":659,"leadStatusId":402},"info":{"ids":{"leadId":659}}}');

        $this->client()->leads()->changeStatus(659, 402);

        self::assertSame(['leadStatusId' => 402], $this->transport->lastRequest()->body);
    }

    public function testLeadChangeStatusSurfacesForeignReasonAsValidationError(): void
    {
        $this->transport->queueJson(422, self::validationJson('statusChangeReasonId', 'Powod nie nalezy do tego statusu.'));

        try {
            $this->client()->leads()->changeStatus(659, 403, 999);
            self::fail('Oczekiwano ValidationException.');
        } catch (ValidationException $e) {
            self::assertSame('body.invalidValue', $e->errorsForField('statusChangeReasonId')[0]->code);
            self::assertCount(1, $this->transport->requests);
        }
    }

    public function testLeadPriorityOutsideEnumIsRejectedByApi(): void
    {
        // Od API 2.15.0 priorytet to enum 0/1/2. SDK nie zna skali instancji, więc
        // wartości nie filtruje - ma czytelnie podać 422 z nazwą pola.
        $this->transport->queueJson(422, self::validationJson('priority', 'Dozwolone wartosci: 0, 1, 2.'));

        try {
            $this->client()->leads()->create(new LeadInput(title: 'Acme', priority: 7));
            self::fail('Oczekiwano ValidationException.');
        } catch (ValidationException $e) {
            self::assertSame(['title' => 'Acme', 'priority' => 7], $this->transport->lastRequest()->body);
            self::assertTrue($e->hasErrorCode('body.invalidValue'));
        }
    }

    public function testPipelineItemCarriesContactIdsBothWays(): void
    {
        $this->transport
            ->queueJson(201, '{"data":{"id":6},"info":{"created":true,"ids":{"pipelineItemId":6}}}')
            ->queueJson(200, '{"data":{"id":6,"contactIds":[]},"info":{"ids":{"pipelineItemId":6}}}');

        $client = $this->client();

        $client->pipelineItems()->create(new PipelineItemInput(
            name: 'Oferta',
            pipelineStageId: 5,
            contractorId: 121,
            contactIds: [50, 51],
        ));
        self::assertSame([
            'name' => 'Oferta',
            'pipelineStageId' => 5,
            'contractorId' => 121,
            'contactIds' => [50, 51],
        ], $this->transport->lastRequest()->body);

        // W PUT pusta lista ODPINA wszystkie kontakty - musi dojechać w body.
        $client->pipelineItems()->update(6, new PipelineItemInput(contactIds: []));
        self::assertSame(['contactIds' => []], $this->transport->lastRequest()->body);
    }

    public function testPipelineItemReadsContactIds(): void
    {
        $this->transport->queueJson(200, '{"data":{"id":6,"name":"Oferta","contactIds":[50,51]}}');

        self::assertSame([50, 51], $this->client()->pipelineItems()->get(6)->contactIds);
    }

    public function testPipelineItemContactFromAnotherContractorIsRejected(): void
    {
        $this->transport->queueJson(422, self::validationJson('contactIds', 'Kontakt nie nalezy do kontrahenta szansy.'));

        try {
            $this->client()->pipelineItems()->update(6, new PipelineItemInput(contactIds: [99]));
            self::fail('Oczekiwano ValidationException.');
        } catch (ValidationException $e) {
            self::assertSame('contactIds', $e->errorsForField('contactIds')[0]->field);
        }
    }

    public function testPipelineItemChangeStagePostsStageId(): void
    {
        $this->transport->queueJson(200, '{"data":{"id":6,"pipelineStageId":5},"info":{"ids":{"pipelineItemId":6},"warnings":{"ownerStageGroupAccess":"Wlasciciel straci dostep w nowym lejku."}}}');

        $result = $this->client()->pipelineItems()->changeStage(6, 5);

        $request = $this->transport->lastRequest();
        self::assertSame('POST', $request->method);
        self::assertSame('v2/pipeline/items/6/stage', $request->path);
        self::assertSame(['pipelineStageId' => 5], $request->body);
        self::assertArrayHasKey('ownerStageGroupAccess', $result->warnings);
    }

    public function testPipelineItemChangeStageSurfacesRequiredFieldsMissing(): void
    {
        $this->transport->queueJson(422, (string) json_encode(['_error' => [
            'code' => 422,
            'message' => 'validation.error',
            'errors' => [[
                'field' => 'pipelineStageId',
                'code' => 'body.requiredFieldsMissing',
                'message' => 'Etap wymaga pol: amount, customField[budzet].',
            ]],
        ]]));

        try {
            $this->client()->pipelineItems()->changeStage(6, 5);
            self::fail('Oczekiwano ValidationException.');
        } catch (ValidationException $e) {
            self::assertTrue($e->hasErrorCode('body.requiredFieldsMissing'));
        }
    }

    public function testPipelineItemChangeStatusPostsReasonAndNote(): void
    {
        $this->transport->queueJson(200, '{"data":{"id":6,"pipelineStatusId":2,"realCloseDate":"2026-09-18"},"info":{"ids":{"pipelineItemId":6}}}');

        $result = $this->client()->pipelineItems()->changeStatus(6, 2, 9, 'Wybrano konkurencyjna oferte');

        $request = $this->transport->lastRequest();
        self::assertSame('POST', $request->method);
        self::assertSame('v2/pipeline/items/6/status', $request->path);
        self::assertSame([
            'pipelineStatusId' => 2,
            'statusChangeReasonId' => 9,
            'note' => 'Wybrano konkurencyjna oferte',
        ], $request->body);
        self::assertSame(6, $result->id);
    }

    public function testPipelineItemReopenSendsStatusAlone(): void
    {
        // Przy ponownym otwarciu (status 1) powód i notatka to 422 - nie mogą
        // wyjść w body tylko dlatego, że metoda ma je w sygnaturze.
        $this->transport->queueJson(200, '{"data":{"id":6,"pipelineStatusId":1},"info":{"ids":{"pipelineItemId":6}}}');

        $this->client()->pipelineItems()->changeStatus(6, 1);

        self::assertSame(['pipelineStatusId' => 1], $this->transport->lastRequest()->body);
    }

    public function testServiceCatalogAndGroups(): void
    {
        $this->transport
            ->queueJson(200, '{"data":[{"id":1,"name":"Abonament","defaultPayValue":"99.00"}],"pagination":{"page":1,"limit":25,"total":1,"pages":1}}')
            ->queueJson(200, '{"data":[{"id":59,"name":"Usługi odbioru","parentId":null,"active":true}]}')
            ->queueJson(201, '{"data":{"id":60}}');

        $client = $this->client();

        $item = $client->serviceCatalog()->list()->first();
        self::assertSame('99.00', $item?->defaultPayValue);

        // groups() zwraca zwykłą listę (trasa bez stronicowania i parametrów).
        $groups = $client->serviceCatalog()->groups();
        self::assertCount(1, $groups);
        self::assertSame('Usługi odbioru', $groups[0]->name);
        self::assertTrue($groups[0]->active);

        $client->serviceCatalog()->createGroup(['name' => 'Nowa grupa']);
        self::assertSame('v2/service/catalog/groups', $this->transport->lastRequest()->path);
    }

    public function testServiceCatalogIterateForcesSortId(): void
    {
        $this->transport->queueJson(200, '{"data":[{"id":1,"name":"Abonament","defaultPayValue":"99.00"}],"pagination":{"page":1,"limit":1000,"total":1,"pages":1}}');

        $items = iterator_to_array($this->client()->serviceCatalog()->iterate(), false);

        self::assertSame('v2/service/catalog', $this->transport->lastRequest()->path);
        self::assertSame('id', $this->transport->lastRequest()->query['sort'] ?? null);
        self::assertSame('Abonament', $items[0]->name);
    }

    public function testTicketFlagsAreBoolsWithLegacyStringTolerance(): void
    {
        // API >= 2.0.4 zwraca prawdziwe boole; 2.0.0-2.0.3 oddawało stringi
        // "0"/"1" - DTO ma czytać oba warianty tak samo.
        $fresh = \TillioCrm\Api\Dto\Ticket::fromArray(['id' => 1, 'open' => true, 'archived' => false]);
        self::assertTrue($fresh->open);
        self::assertFalse($fresh->archived);

        $legacy = \TillioCrm\Api\Dto\Ticket::fromArray(['id' => 2, 'open' => '1', 'archived' => '0']);
        self::assertTrue($legacy->open);
        self::assertFalse($legacy->archived);

        $missing = \TillioCrm\Api\Dto\Ticket::fromArray(['id' => 3]);
        self::assertNull($missing->open);
    }

    public function testServiceCatalogItemInputWritesDefaultPayValue(): void
    {
        // Od API 2.0.4 zapis pozycji katalogu przyjmuje `defaultPayValue` -
        // tę samą nazwę co odczyt (kanon 2.0.0).
        $input = new \TillioCrm\Api\Dto\ServiceCatalogItemInput(name: 'Abonament', groupId: 59, defaultPayValue: '99.00');

        self::assertSame(['name' => 'Abonament', 'groupId' => 59, 'defaultPayValue' => '99.00'], $input->toArray());
    }

    public function testUserCreateReturnsTemporaryPassword(): void
    {
        $this->transport->queueJson(201, '{"data":{"id":12,"firstName":"Jan","email":"jan@firma.pl"},"info":{"created":true,"ids":{"userId":12},"temporaryPassword":"HasloStartowe123","warnings":{}}}');

        $created = $this->client()->users()->create(new UserInput(
            firstName: 'Jan',
            email: 'jan@firma.pl',
            userStatusId: 1,
            roleId: 2,
        ));

        self::assertSame(12, $created->user->id);
        self::assertSame('HasloStartowe123', $created->temporaryPassword);
    }

    public function testUsersIterateForcesSortId(): void
    {
        $this->transport->queueJson(200, '{"data":[{"id":12,"firstName":"Jan"}],"pagination":{"page":1,"limit":1000,"total":1,"pages":1}}');

        $users = iterator_to_array($this->client()->users()->iterate(), false);

        self::assertSame('v2/users', $this->transport->lastRequest()->path);
        self::assertSame('id', $this->transport->lastRequest()->query['sort'] ?? null);
        self::assertSame('Jan', $users[0]->firstName);
    }

    public function testUserInputUsesContractNamesFrom216(): void
    {
        // Kontrakt 2.16.0 przemianowal pola zapisu: position -> jobTitle,
        // phone -> contactPhone, i dolozyl contactEmail (inny niz login).
        self::assertSame([
            'firstName' => 'Jan',
            'email' => 'j.kowalski@firma.pl',
            'jobTitle' => 'Opiekun klienta',
            'contactPhone' => '+48601234567',
            'contactEmail' => 'kontakt@firma.pl',
            'gender' => 'male',
            'userStatusId' => 1,
            'roleId' => 2,
        ], (new UserInput(
            firstName: 'Jan',
            email: 'j.kowalski@firma.pl',
            jobTitle: 'Opiekun klienta',
            contactPhone: '+48601234567',
            contactEmail: 'kontakt@firma.pl',
            gender: 'male',
            userStatusId: 1,
            roleId: 2,
        ))->toArray());
    }

    public function testSystemUserReadsBusinessContactFields(): void
    {
        $this->transport->queueJson(200, '{"data":[{"id":12,"firstName":"Jan","lastName":"Kowalski","email":"j.kowalski@firma.pl","userStatusId":1,"jobTitle":"Opiekun klienta","contactPhone":"+48601234567","contactEmail":"kontakt@firma.pl","gender":"male"}],"pagination":{"page":1,"limit":25,"total":1,"pages":1}}');

        $user = $this->client()->users()->list(['gender' => 'male'])->first();
        self::assertInstanceOf(SystemUser::class, $user);

        self::assertSame(['gender' => 'male'], $this->transport->lastRequest()->query);
        // email to LOGIN; contactEmail to osobny adres sluzbowy.
        self::assertSame('j.kowalski@firma.pl', $user->email);
        self::assertSame('kontakt@firma.pl', $user->contactEmail);
        self::assertSame('Opiekun klienta', $user->jobTitle);
        self::assertSame('+48601234567', $user->contactPhone);
        self::assertSame('male', $user->gender);
    }

    public function testUserActivityListSendsFiltersAndMapsRows(): void
    {
        $this->transport->queueJson(200, '{"data":[{"userId":5,"lastLoginAt":"2026-09-18T08:14:03+02:00","lastActivityAt":"2026-09-18T13:08:18+02:00","loginCount":128}],"pagination":{"page":1,"limit":25,"total":1,"pages":1}}');

        $row = $this->client()->users()->activity(['sort' => 'lastActivityAt', 'sortDir' => 'asc'])->first();
        self::assertInstanceOf(UserActivity::class, $row);

        $request = $this->transport->lastRequest();
        self::assertSame('GET', $request->method);
        self::assertSame('v2/users/activity', $request->path);
        self::assertSame(['sort' => 'lastActivityAt', 'sortDir' => 'asc'], $request->query);
        self::assertSame(5, $row->userId);
        self::assertSame(128, $row->loginCount);
        self::assertTrue($row->hasEverLoggedIn());
    }

    public function testUserActivityIterateForcesSortUserId(): void
    {
        // Ta lista nie ma pola `id`, wiec stabilny porzadek daje sort=userId.
        $this->transport->queueJson(200, '{"data":[{"userId":5,"lastLoginAt":null,"lastActivityAt":null,"loginCount":0}],"pagination":{"page":1,"limit":1000,"total":1,"pages":1}}');

        $rows = iterator_to_array($this->client()->users()->iterateActivity(), false);

        self::assertSame('userId', $this->transport->lastRequest()->query['sort'] ?? null);
        // Konto, ktore nigdy sie nie logowalo: daty null, licznik 0.
        self::assertNull($rows[0]->lastLoginAt);
        self::assertSame(0, $rows[0]->loginCount);
        self::assertFalse($rows[0]->hasEverLoggedIn());
    }

    public function testUserActivityGetUsesIdPath(): void
    {
        $this->transport->queueJson(200, '{"data":{"userId":5,"lastLoginAt":"2026-09-18T08:14:03+02:00","lastActivityAt":"2026-09-18T13:08:18+02:00","loginCount":128}}');

        $activity = $this->client()->users()->getActivity(5);

        self::assertSame('v2/users/5/activity', $this->transport->lastRequest()->path);
        self::assertSame('2026-09-18T08:14:03+02:00', $activity->lastLoginAt);
    }

    public function testNoteTemplateCreateAndCategories(): void
    {
        $this->transport
            ->queueJson(201, '{"data":{"id":21,"categoryId":3,"noteTypeId":1,"name":"Notatka z rozmowy","alias":"!notatka_z_rozmowy","title":"Rozmowa telefoniczna","body":"<p>Ustalenia:</p>","active":true}}')
            ->queueJson(200, '{"data":[{"id":3,"name":"Sprzedaż","parentId":null,"priority":10}]}')
            ->queueJson(201, '{"data":{"id":4,"name":"Serwis"}}')
            ->queueJson(200, '{"data":{"id":4,"name":"Serwis","parentId":3}}');

        $client = $this->client();

        $template = $client->notes()->createTemplate(new NoteTemplateInput(
            categoryId: 3,
            noteTypeId: 1,
            name: 'Notatka z rozmowy',
            title: 'Rozmowa telefoniczna',
            body: '<p>Ustalenia:</p>',
        ));
        self::assertSame('POST', $this->transport->lastRequest()->method);
        self::assertSame('v2/note/templates', $this->transport->lastRequest()->path);
        self::assertSame([
            'categoryId' => 3,
            'noteTypeId' => 1,
            'name' => 'Notatka z rozmowy',
            'title' => 'Rozmowa telefoniczna',
            'body' => '<p>Ustalenia:</p>',
        ], $this->transport->lastRequest()->body);
        self::assertSame(21, $template->id);
        // Alias wraca w postaci znormalizowanej przez CRM.
        self::assertSame('!notatka_z_rozmowy', $template->alias);
        self::assertTrue($template->active);

        $categories = $client->notes()->templateCategories();
        self::assertSame('v2/note/template/categories', $this->transport->lastRequest()->path);
        self::assertInstanceOf(TemplateCategory::class, $categories[0]);
        self::assertSame('Sprzedaż', $categories[0]->name);

        $created = $client->notes()->createTemplateCategory(new CategoryInput(name: 'Serwis'));
        self::assertSame('POST', $this->transport->lastRequest()->method);
        self::assertSame(4, $created->id);

        $updated = $client->notes()->updateTemplateCategory(4, ['parentId' => 3]);
        self::assertSame('PUT', $this->transport->lastRequest()->method);
        self::assertSame('v2/note/template/categories/4', $this->transport->lastRequest()->path);
        self::assertSame(3, $updated->parentId);
    }

    public function testTaskTemplateCreateMapsIntLists(): void
    {
        // Listy id wracają z API czasem jako stringi - Cast::intList musi je znieść.
        $this->transport->queueJson(201, '{"data":{"id":15,"name":"Wdrożenie klienta","alias":"!wdrozenie_klienta","assignedUserIds":["7","8"],"tagIds":[3],"eta":120,"dueInDays":5,"taskPriority":1,"active":true}}');

        $template = $this->client()->tasks()->createTemplate(new TaskTemplateInput(
            name: 'Wdrożenie klienta',
            title: 'Wdrożenie: {companyName}',
            assignedUserIds: [7, 8],
            tagIds: [3],
            eta: 120,
            dueInDays: 5,
            taskPriority: 1,
        ));

        $request = $this->transport->lastRequest();
        self::assertSame('POST', $request->method);
        self::assertSame('v2/task/templates', $request->path);
        self::assertSame([
            'name' => 'Wdrożenie klienta',
            'title' => 'Wdrożenie: {companyName}',
            'assignedUserIds' => [7, 8],
            'tagIds' => [3],
            'eta' => 120,
            'dueInDays' => 5,
            'taskPriority' => 1,
        ], $request->body);

        self::assertSame(15, $template->id);
        self::assertSame([7, 8], $template->assignedUserIds);
        self::assertSame([3], $template->tagIds);
        self::assertSame(120, $template->eta);
        self::assertSame(1, $template->taskPriority);
    }

    public function testTaskTemplateCategoriesRoutes(): void
    {
        $this->transport
            ->queueJson(200, '{"data":[{"id":1,"name":"Onboarding"}]}')
            ->queueJson(201, '{"data":{"id":2,"name":"Serwis"}}')
            ->queueJson(200, '{"data":{"id":2,"name":"Serwis","priority":7}}');

        $client = $this->client();

        $categories = $client->tasks()->templateCategories();
        self::assertSame('v2/task/template/categories', $this->transport->lastRequest()->path);
        self::assertSame('Onboarding', $categories[0]->name);

        $client->tasks()->createTemplateCategory(new CategoryInput(name: 'Serwis'));
        self::assertSame('POST', $this->transport->lastRequest()->method);
        self::assertSame(['name' => 'Serwis'], $this->transport->lastRequest()->body);

        $updated = $client->tasks()->updateTemplateCategory(2, new CategoryInput(priority: 7));
        self::assertSame('v2/task/template/categories/2', $this->transport->lastRequest()->path);
        self::assertSame(['priority' => 7], $this->transport->lastRequest()->body);
        self::assertSame(7, $updated->priority);
    }

    public function testNoteContactsListAddRemove(): void
    {
        $this->transport
            ->queueJson(200, '{"data":[{"id":7,"firstName":"Jan","lastName":"Kowalski","fullName":"Jan Kowalski","email":"jan@acme.pl"}]}')
            ->queueJson(200, '{"data":[{"id":7,"firstName":"Jan","lastName":"Kowalski"},{"id":8,"firstName":"Ewa","lastName":"Nowak"}]}')
            ->queueJson(204, '');

        $client = $this->client();

        $contacts = $client->notes()->contacts(9);
        self::assertSame('GET', $this->transport->lastRequest()->method);
        self::assertSame('v2/notes/9/contacts', $this->transport->lastRequest()->path);
        self::assertInstanceOf(NoteContact::class, $contacts[0]);
        self::assertSame('jan@acme.pl', $contacts[0]->email);

        $afterAdd = $client->notes()->addContact(9, 7);
        $request = $this->transport->lastRequest();
        self::assertSame('POST', $request->method);
        self::assertSame('v2/notes/9/contacts', $request->path);
        self::assertSame(['contactId' => 7], $request->body);
        // Zwraca pełną listę po zmianie.
        self::assertCount(2, $afterAdd);
        self::assertSame(8, $afterAdd[1]->id);

        $client->notes()->removeContact(9, 7);
        $request = $this->transport->lastRequest();
        self::assertSame('DELETE', $request->method);
        self::assertSame('v2/notes/9/contacts/7', $request->path);
    }

    public function testContactCreateNotePostsUnderContact(): void
    {
        $this->transport->queueJson(201, '{"data":{"id":33},"info":{"created":true,"ids":{"noteId":33}}}');

        $result = $this->client()->contacts()->createNote(7, new NoteInput(noteTypeId: 1, title: 'x'));

        $request = $this->transport->lastRequest();
        self::assertSame('POST', $request->method);
        self::assertSame('v2/contacts/7/notes', $request->path);
        self::assertSame(['noteTypeId' => 1, 'title' => 'x'], $request->body);
        self::assertSame(33, $result->id);
    }

    public function testTaskCommentAddAndUpdate(): void
    {
        $this->transport
            ->queueJson(201, '{"data":{"id":3,"body":"<p>x</p>","creatorUserId":7}}')
            ->queueJson(200, '{"data":{"id":3,"body":"<p>y</p>","editorUserId":7}}');

        $client = $this->client();

        $comment = $client->tasks()->addComment(5, new TaskCommentInput(body: '<p>x</p>'));
        $request = $this->transport->lastRequest();
        self::assertSame('POST', $request->method);
        self::assertSame('v2/tasks/5/comments', $request->path);
        self::assertSame(['body' => '<p>x</p>'], $request->body);
        self::assertInstanceOf(TaskComment::class, $comment);
        self::assertSame(3, $comment->id);
        self::assertSame('<p>x</p>', $comment->body);

        $updated = $client->tasks()->updateComment(5, 3, new TaskCommentInput(body: '<p>y</p>'));
        $request = $this->transport->lastRequest();
        self::assertSame('PUT', $request->method);
        self::assertSame('v2/tasks/5/comments/3', $request->path);
        self::assertSame(['body' => '<p>y</p>'], $request->body);
        self::assertSame('<p>y</p>', $updated->body);
    }

    public function testTaskAttachmentUnderCommentSendsCommentIdField(): void
    {
        $this->transport->queueJson(201, '{"data":{"id":12},"info":{"ids":{"attachmentId":12}}}');

        $result = $this->client()->tasks()->addAttachment(
            5,
            FileUpload::fromString('%PDF', 'zalacznik.pdf', 'application/pdf'),
            commentId: 3,
        );

        $request = $this->transport->lastRequest();
        self::assertSame('POST', $request->method);
        self::assertSame('v2/tasks/5/attachments', $request->path);
        self::assertSame(3, $request->body['commentId'] ?? null);
        self::assertArrayHasKey('file', $request->files);
        self::assertSame('zalacznik.pdf', $request->files['file']->fileName);
        self::assertSame(12, $result->id);
    }

    public function testPostUsersDoesNotRetryEvenOnTransportError(): void
    {
        // Hasło startowe jest jednorazowe: powtórka po timeoutcie, który doszedł,
        // to "login zajęty" i hasło przepada. Jedna próba, koniec.
        $this->transport->queueThrowable(new TransportException('timeout'));

        try {
            $this->client()->users()->create(['firstName' => 'Jan', 'email' => 'jan@firma.pl', 'userStatusId' => 1, 'roleId' => 2]);
            self::fail('Oczekiwano TransportException.');
        } catch (TransportException) {
            self::assertCount(1, $this->transport->requests);
        }
    }
}
