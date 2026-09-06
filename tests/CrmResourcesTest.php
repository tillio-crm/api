<?php

declare(strict_types=1);

namespace TillioCrm\Api\Tests;

use PHPUnit\Framework\TestCase;
use TillioCrm\Api\Dto\CategoryInput;
use TillioCrm\Api\Dto\NoteContact;
use TillioCrm\Api\Dto\NoteInput;
use TillioCrm\Api\Dto\NoteTemplateInput;
use TillioCrm\Api\Dto\PipelineItemInput;
use TillioCrm\Api\Dto\ServiceInput;
use TillioCrm\Api\Dto\TaskComment;
use TillioCrm\Api\Dto\TaskCommentInput;
use TillioCrm\Api\Dto\TaskInput;
use TillioCrm\Api\Dto\TaskTemplateInput;
use TillioCrm\Api\Dto\TemplateCategory;
use TillioCrm\Api\Dto\TicketInput;
use TillioCrm\Api\Dto\TicketMessageInput;
use TillioCrm\Api\Dto\UserInput;
use TillioCrm\Api\Exception\TransportException;
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
