<?php

declare(strict_types=1);

namespace TillioCrm\Api\Tests;

use PHPUnit\Framework\TestCase;
use TillioCrm\Api\Dto\CategoryInput;
use TillioCrm\Api\Dto\CustomFieldInput;
use TillioCrm\Api\Dto\DictionaryEntry;
use TillioCrm\Api\Dto\DictionaryEntryInput;
use TillioCrm\Api\Dto\DocumentTypeInput;
use TillioCrm\Api\Dto\MailSendInput;
use TillioCrm\Api\Dto\MailTemplateAttachment;
use TillioCrm\Api\Dto\MailTemplateInput;
use TillioCrm\Api\Dto\PipelineFunnelInput;
use TillioCrm\Api\Dto\ProcessStageInput;
use TillioCrm\Api\Exception\TransportException;
use TillioCrm\Api\Tests\Support\FakeClock;
use TillioCrm\Api\Tests\Support\MockTransport;
use TillioCrm\Api\TillioClient;
use TillioCrm\Api\Transport\FileUpload;

/**
 * Zasoby pozostałe: słowniki, pola niestandardowe, DMS (adresowanie publicId),
 * generator dokumentów, mail (JSON kontra multipart z polem payload), wiki,
 * moduły.
 */
final class MiscResourcesTest extends TestCase
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

    public function testSimpleDictionaryReadAndWrite(): void
    {
        $this->transport
            ->queueJson(200, '{"data":[{"id":1,"name":"Aktywny","color":"#4caf50","active":true,"isDefault":true}]}')
            ->queueJson(201, '{"data":{"id":9}}')
            ->queueJson(200, '{"data":{"id":9}}');

        $client = $this->client();

        $statuses = $client->dictionaries()->contractorStatuses();
        self::assertSame('v2/contractor/statuses', $this->transport->lastRequest()->path);
        self::assertSame('Aktywny', $statuses[0]->name);
        self::assertTrue($statuses[0]->isDefault);

        $client->dictionaries()->createContractorStatus(new DictionaryEntryInput(name: 'Nowy', color: '#fff'));
        self::assertSame(['name' => 'Nowy', 'color' => '#fff'], $this->transport->lastRequest()->body);

        $client->dictionaries()->updateContractorStatus(9, ['active' => false]);
        self::assertSame('v2/contractor/statuses/9', $this->transport->lastRequest()->path);
        self::assertSame('PUT', $this->transport->lastRequest()->method);
    }

    public function testFunnelsWithNestedStages(): void
    {
        $this->transport
            ->queueJson(200, '{"data":[{"id":1,"name":"Lejek","isActive":true,"stages":[{"id":5,"name":"Kontakt","probability":20,"order":1}]}]}')
            ->queueJson(201, '{"data":{"id":2}}')
            ->queueJson(201, '{"data":{"id":6}}');

        $client = $this->client();

        $funnels = $client->dictionaries()->pipelineFunnels();
        self::assertSame(20, $funnels[0]->stages[0]->probability);

        $client->dictionaries()->createPipelineFunnel(new PipelineFunnelInput(
            name: 'Nowy lejek',
            stages: [new ProcessStageInput(name: 'Etap 1', probability: 10)],
        ));
        self::assertSame([
            'name' => 'Nowy lejek',
            'stages' => [['name' => 'Etap 1', 'probability' => 10]],
        ], $this->transport->lastRequest()->body);

        $client->dictionaries()->createPipelineStage(2, ['name' => 'Etap 2']);
        self::assertSame('v2/pipeline/funnels/2/stages', $this->transport->lastRequest()->path);
    }

    public function testSpecialShapeDictionaries(): void
    {
        $this->transport
            ->queueJson(200, '{"data":[{"id":2,"name":"Proces handlowy","isActive":true,"statuses":[{"id":11,"name":"Nowy","type":"default","order":1}]}]}')
            ->queueJson(200, '{"data":[{"id":5,"name":"Handlowiec","permissions":["contractor.canEdit"]}]}')
            ->queueJson(200, '{"data":[{"id":38,"key":"contractor.canEdit","name":"Edycja Kontrahentów"}]}')
            ->queueJson(200, '{"data":["PLN","EUR"]}');

        $client = $this->client();

        $processes = $client->dictionaries()->leadProcesses();
        self::assertSame('default', $processes[0]->statuses[0]->type);

        $roles = $client->dictionaries()->userRoles();
        self::assertSame(['contractor.canEdit'], $roles[0]->permissions);

        $permissions = $client->dictionaries()->userPermissions();
        self::assertSame('contractor.canEdit', $permissions[0]->key);

        self::assertSame(['PLN', 'EUR'], $client->dictionaries()->currencies());
    }

    public function testCustomFieldsListCreateUpdate(): void
    {
        $this->transport
            ->queueJson(200, '{"data":[{"key":"contractor_str_2","name":"ERP ID","type":"string","fieldTypeId":1,"required":false,"assignedTo":[7]}]}')
            ->queueJson(201, '{"data":{"key":"contractor_str_3","name":"Nowe pole"}}')
            ->queueJson(200, '{"data":{"key":"contractor_str_3"}}');

        $client = $this->client();

        $fields = $client->customFields()->list('contractor');
        self::assertSame('v2/contractor/custom-fields', $this->transport->lastRequest()->path);
        // Klucz generuje CRM - integracja musi go odczytać z listy/odpowiedzi.
        self::assertSame('contractor_str_2', $fields[0]->key);

        $created = $client->customFields()->create(new CustomFieldInput(
            entity: 'contractor',
            name: 'Nowe pole',
            type: 'string',
            editableBy: ['userIds' => [7]],
        ));
        self::assertSame('v2/custom-fields', $this->transport->lastRequest()->path);
        self::assertSame('contractor_str_3', $created->data['key']);
        self::assertSame(['userIds' => [7]], $this->transport->lastRequest()->body['editableBy'] ?? null);

        $client->customFields()->update('contractor', 'contractor_str_3', ['assignedTo' => [7, 7]]);
        self::assertSame('v2/contractor/custom-fields/contractor_str_3', $this->transport->lastRequest()->path);
    }

    public function testGetFieldFileReturnsDtoOrNull(): void
    {
        $this->transport
            ->queueJson(200, '{"data":{"fileName":"protokol.pdf","mimeType":"application/pdf","sizeBytes":171605,"storagePath":"visibleFields/attachments/18270/9bcb5d.pdf","fileUrl":"/v2/note/18270/custom-fields/note_file_1/file","downloadUrl":"https://storage.example/signed"}}')
            ->queueJson(200, '{"data":null}');

        $client = $this->client();

        $file = $client->customFields()->getFieldFile('note', 18270, 'note_file_1');
        $request = $this->transport->lastRequest();
        self::assertSame('GET', $request->method);
        self::assertSame('v2/note/18270/custom-fields/note_file_1/file', $request->path);
        self::assertNotNull($file);
        self::assertSame('protokol.pdf', $file->fileName);
        self::assertSame(171605, $file->sizeBytes);
        self::assertSame('https://storage.example/signed', $file->downloadUrl);

        // data:null = pole istnieje, ale nie ma w nim pliku.
        self::assertNull($client->customFields()->getFieldFile('note', 18270, 'note_file_1'));
    }

    public function testUploadFieldFileUsesMultipartWithoutRetry(): void
    {
        // Upload zastępuje poprzedni plik i nie jest idempotentny - błąd
        // transportu nie może wywołać drugiej próby.
        $this->transport->queueThrowable(new TransportException('timeout'));

        try {
            $this->client()->customFields()->uploadFieldFile('note', 18270, 'note_file_1', FileUpload::fromString('tresc', 'protokol.pdf', 'application/pdf'));
            self::fail('Oczekiwano TransportException.');
        } catch (TransportException) {
            self::assertCount(1, $this->transport->requests);
        }

        $request = $this->transport->lastRequest();
        self::assertSame('POST', $request->method);
        self::assertSame('v2/note/18270/custom-fields/note_file_1/file', $request->path);
        self::assertArrayHasKey('file', $request->files);
        self::assertSame('protokol.pdf', $request->files['file']->fileName);
    }

    public function testUploadFieldFileMapsResponse(): void
    {
        $this->transport->queueJson(200, '{"data":{"fileName":"umowa.pdf","mimeType":"application/pdf","sizeBytes":2048,"storagePath":"visibleFields/attachments/42/aa.pdf","fileUrl":"/v2/contractor/42/custom-fields/contractor_file_2/file","downloadUrl":null}}');

        $file = $this->client()->customFields()->uploadFieldFile('contractor', 42, 'contractor_file_2', FileUpload::fromString('x', 'umowa.pdf', 'application/pdf'));
        self::assertSame('umowa.pdf', $file->fileName);
        self::assertSame(2048, $file->sizeBytes);
        self::assertNull($file->downloadUrl);
    }

    public function testDeleteFieldFileSendsDelete(): void
    {
        $this->transport->queueJson(200, '{"data":null}');

        $this->client()->customFields()->deleteFieldFile('lead', 12, 'leads_file_1');
        $request = $this->transport->lastRequest();
        self::assertSame('DELETE', $request->method);
        self::assertSame('v2/lead/12/custom-fields/leads_file_1/file', $request->path);
    }

    public function testDmsListingAndPublicIdAddressing(): void
    {
        $this->transport
            ->queueJson(200, '{"data":{"directory":null,"directories":[{"id":3,"name":"Umowy"}],"documents":[{"publicId":"pub-abc","id":991,"fileName":"umowa.pdf","downloadUrl":"https://storage.example.test/x?sig=1"}]}}')
            ->queueJson(200, '{"data":{"publicId":"pub-abc","id":991,"fileName":"umowa.pdf","downloadUrl":"https://storage.example.test/x?sig=2"}}')
            ->queueJson(200, '{"data":{"publicId":"pub-abc","id":991,"fileName":"umowa-2026.pdf"}}');

        $client = $this->client();

        $listing = $client->dms()->listing(42);
        // directoryId=null (poziom główny) jest wycinane z query przez QueryBuilder.
        self::assertSame([], $this->transport->requests[0]->query);
        self::assertNull($listing->directory);
        self::assertSame('Umowy', $listing->findDirectory('Umowy')?->name);

        $document = $listing->findDocument('umowa.pdf');
        self::assertNotNull($document);
        // publicId - jedyny identyfikator działający w ścieżce; id to referencja CRM.
        self::assertSame('pub-abc', $document->publicId);
        self::assertSame(991, $document->id);

        $fresh = $client->dms()->getDocument('pub-abc');
        self::assertSame('v2/dms/documents/pub-abc', $this->transport->lastRequest()->path);
        self::assertStringContainsString('sig=2', (string) $fresh->downloadUrl);

        $renamed = $client->dms()->renameDocument('pub-abc', 'umowa-2026.pdf');
        self::assertSame('PUT', $this->transport->lastRequest()->method);
        self::assertSame('umowa-2026.pdf', $renamed->fileName);
    }

    public function testDmsEnsureDirectoryIsIdempotent(): void
    {
        // Katalog już istnieje - ensureDirectory NIE wysyła POST-a.
        $this->transport->queueJson(200, '{"data":{"directory":null,"directories":[{"id":3,"name":"Umowy"}],"documents":[]}}');

        $directory = $this->client()->dms()->ensureDirectory(42, 'Umowy');

        self::assertSame(3, $directory->id);
        self::assertCount(1, $this->transport->requests);
    }

    public function testDmsUploadMultipartWithDirectory(): void
    {
        $this->transport->queueJson(201, '{"data":{"publicId":"pub-new","id":1001,"fileName":"oferta.pdf"}}');

        $doc = $this->client()->dms()->uploadDocument(
            42,
            FileUpload::fromString('%PDF', 'oferta.pdf', 'application/pdf'),
            directoryId: 3,
        );

        $request = $this->transport->lastRequest();
        self::assertSame('v2/contractors/42/dms/documents', $request->path);
        self::assertSame(['directoryId' => 3], $request->body);
        self::assertArrayHasKey('file', $request->files);
        self::assertSame('pub-new', $doc->publicId);
    }

    public function testGeneratedDocumentsFlow(): void
    {
        $this->transport
            ->queueJson(200, '{"data":[{"id":411,"name":"Oferta handlowa","requiresTemplate":true,"templates":[{"id":1,"name":"Klasyczny"}]}]}')
            ->queueJson(200, '{"data":{"fields":[{"name":"cena"}]}}')
            ->queueJson(201, '{"data":{"id":70,"downloadUrl":null},"info":{"ids":{"documentId":70}}}')
            ->queueJson(200, '{"data":{"id":70,"downloadUrl":"https://storage.example.test/doc?sig=9","publishUrl":"https://docs.example.test/p/abc"}}');

        $client = $this->client();

        $types = $client->generatedDocuments()->types();
        self::assertTrue($types[0]->requiresTemplate);

        // contractorId jest wymagane w query (prefill danych kartoteki)
        $form = $client->generatedDocuments()->typeForm(411, 42, templateId: 1);
        self::assertSame('v2/document/types/411/form', $this->transport->lastRequest()->path);
        self::assertSame(['contractorId' => '42', 'templateId' => '1'], $this->transport->lastRequest()->query);
        self::assertArrayHasKey('fields', $form);

        $created = $client->generatedDocuments()->create(42, ['documentTypeId' => 411, 'templateId' => 1, 'data' => ['cena' => '100']]);
        self::assertSame('v2/contractors/42/documents', $this->transport->lastRequest()->path);
        self::assertSame(70, $created->id);

        $document = $client->generatedDocuments()->get(70);
        self::assertNotNull($document->downloadUrl);
        self::assertNotNull($document->publishUrl);
    }

    public function testDocumentTypesIncludeInactiveOnlyWhenRequested(): void
    {
        $this->transport
            ->queueJson(200, '{"data":[{"id":411,"name":"Oferta handlowa","templates":[]}]}')
            ->queueJson(200, '{"data":[{"id":411,"name":"Oferta handlowa","templates":[]},{"id":412,"name":"Szkic","draft":true,"active":false,"templates":[]}]}');

        $client = $this->client();

        // Domyślne wywołanie NIE wysyła parametru - musi działać też na API 2.0.4.
        $client->generatedDocuments()->types();
        self::assertSame([], $this->transport->lastRequest()->query);

        $types = $client->generatedDocuments()->types(true);
        self::assertSame(['includeInactive' => '1'], $this->transport->lastRequest()->query);
        self::assertTrue($types[1]->draft);
        self::assertFalse($types[1]->active);
    }

    public function testDocumentTypeCreateAndActivateMapLifecycleFields(): void
    {
        $this->transport
            ->queueJson(201, '{"data":{"id":9,"name":"Umowa serwisowa","draft":true,"active":false,"store":true,"publishDays":14,"variables":[],"templates":[]}}')
            ->queueJson(200, '{"data":{"id":9,"name":"Umowa serwisowa","draft":false,"active":true,"variables":["{{cena}}","{{nip}}"],"templateVersion":2,"templates":[]}}');

        $client = $this->client();

        $created = $client->generatedDocuments()->createType(new DocumentTypeInput(
            name: 'Umowa serwisowa',
            store: true,
            publishDays: 14,
        ));
        self::assertSame('v2/document/types', $this->transport->lastRequest()->path);
        self::assertSame('POST', $this->transport->lastRequest()->method);
        // Input bez null-i - tylko podane pola.
        self::assertSame(['name' => 'Umowa serwisowa', 'store' => true, 'publishDays' => 14], $this->transport->lastRequest()->body);
        self::assertTrue($created->draft);
        self::assertFalse($created->active);

        $activated = $client->generatedDocuments()->activateType(9);
        self::assertSame('v2/document/types/9/activate', $this->transport->lastRequest()->path);
        self::assertSame('POST', $this->transport->lastRequest()->method);
        self::assertTrue($activated->active);
        self::assertFalse($activated->draft);
        self::assertSame(['{{cena}}', '{{nip}}'], $activated->variables);
        self::assertSame(2, $activated->templateVersion);
    }

    public function testUploadTypeSourceUsesMultipartWithoutRetry(): void
    {
        // Upload pliku źródłowego nie jest idempotentny - nawet błąd transportu
        // nie może wywołać drugiej próby.
        $this->transport->queueThrowable(new TransportException('timeout'));

        try {
            $this->client()->generatedDocuments()->uploadTypeSource(9, FileUpload::fromString('szablon', 'umowa.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'));
            self::fail('Oczekiwano TransportException.');
        } catch (TransportException) {
            self::assertCount(1, $this->transport->requests);
        }

        $request = $this->transport->lastRequest();
        self::assertSame('v2/document/types/9/source', $request->path);
        self::assertArrayHasKey('file', $request->files);
        self::assertSame('umowa.docx', $request->files['file']->fileName);
    }

    public function testUpdateTypeFormPutsFormFieldsAndReturnsRawMap(): void
    {
        $this->transport->queueJson(200, '{"data":{"id":9,"draft":false,"templateVersion":3,"variables":["{{cena}}"],"missingVariables":[]}}');

        $formFields = [['group' => 'Dane oferty', 'fields' => [['variable' => '{{cena}}', 'label' => 'Cena', 'type' => 'string']]]];
        $result = $this->client()->generatedDocuments()->updateTypeForm(9, ['formFields' => $formFields]);

        $request = $this->transport->lastRequest();
        self::assertSame('PUT', $request->method);
        self::assertSame('v2/document/types/9/form', $request->path);
        self::assertSame(['formFields' => $formFields], $request->body);
        // Kształt zależy od typu - surowa mapa, nie DTO.
        self::assertSame(['{{cena}}'], $result['variables']);
        self::assertSame([], $result['missingVariables']);
        self::assertFalse($result['draft']);
    }

    public function testDocumentCategoriesAndNumerations(): void
    {
        $this->transport
            ->queueJson(200, '{"data":[{"id":1,"name":"Umowy","parentId":null,"priority":10,"system":true},{"id":2,"name":"Oferty","parentId":1,"system":false}]}')
            ->queueJson(201, '{"data":{"id":3,"name":"Aneksy","parentId":1,"system":false}}')
            ->queueJson(200, '{"data":{"id":3,"name":"Aneksy 2026","parentId":1,"priority":5,"system":false}}')
            ->queueJson(200, '{"data":[{"id":4,"schema":"OF/[NR]/[MM]/[RRRR]","startNumber":1,"resetPeriod":"month","active":true}]}');

        $client = $this->client();

        $categories = $client->generatedDocuments()->categories();
        self::assertSame('v2/document/categories', $this->transport->lastRequest()->path);
        // Kategorie systemowe są tylko do odczytu - flaga musi się mapować.
        self::assertTrue($categories[0]->system);
        self::assertFalse($categories[1]->system);
        self::assertSame(1, $categories[1]->parentId);

        $created = $client->generatedDocuments()->createCategory(new CategoryInput(name: 'Aneksy', parentId: 1));
        self::assertSame('POST', $this->transport->lastRequest()->method);
        self::assertSame(['name' => 'Aneksy', 'parentId' => 1], $this->transport->lastRequest()->body);
        self::assertSame(3, $created->id);

        $updated = $client->generatedDocuments()->updateCategory(3, ['name' => 'Aneksy 2026', 'priority' => 5]);
        self::assertSame('PUT', $this->transport->lastRequest()->method);
        self::assertSame('v2/document/categories/3', $this->transport->lastRequest()->path);
        self::assertSame('Aneksy 2026', $updated->name);

        $numerations = $client->generatedDocuments()->numerations();
        self::assertSame('v2/document/numerations', $this->transport->lastRequest()->path);
        self::assertSame('OF/[NR]/[MM]/[RRRR]', $numerations[0]->schema);
        self::assertSame('month', $numerations[0]->resetPeriod);
        self::assertTrue($numerations[0]->active);
    }

    public function testMailWithoutAttachmentsUsesJson(): void
    {
        $this->transport->queueJson(200, '{"data":{"sent":true},"info":{"ids":{"messageId":5}}}');

        $this->client()->mail()->send(new MailSendInput(
            accountId: 5,
            to: ['jan@acme.pl'],
            subject: 'Oferta',
            body: '<p>Treść</p>',
        ));

        $request = $this->transport->lastRequest();
        self::assertSame('v2/mail/send', $request->path);
        self::assertSame([], $request->files);
        self::assertSame(['jan@acme.pl'], $request->body['to'] ?? null);
    }

    public function testMailWithAttachmentsUsesMultipartWithPayloadField(): void
    {
        // Kontrakt multipart: cały payload wysyłki jako JSON w polu `payload`,
        // pliki w `attachments[]`.
        $this->transport->queueJson(200, '{"data":{"sent":true}}');

        $this->client()->mail()->send(
            new MailSendInput(accountId: 5, to: ['jan@acme.pl'], subject: 'Oferta', body: 'Treść'),
            [FileUpload::fromString('%PDF', 'oferta.pdf', 'application/pdf')],
        );

        $request = $this->transport->lastRequest();
        self::assertArrayHasKey('attachments[0]', $request->files);
        $payload = json_decode((string) ($request->body['payload'] ?? ''), true);
        self::assertSame(['jan@acme.pl'], $payload['to'] ?? null);
        self::assertSame('Oferta', $payload['subject'] ?? null);
    }

    public function testMailTemplates(): void
    {
        $this->transport
            ->queueJson(200, '{"data":[{"id":12,"name":"Follow up","subject":"Oferta"}]}')
            ->queueJson(200, '{"data":{"id":12,"name":"Follow up","body":"<p>{companyName}</p>","to":["{email}"]}}');

        $client = $this->client();

        $templates = $client->mail()->templates();
        self::assertSame('Follow up', $templates[0]->name);
        self::assertNull($templates[0]->body); // lista bez treści

        $full = $client->mail()->getTemplate(12);
        self::assertStringContainsString('{companyName}', (string) $full->body);
        self::assertSame(['{email}'], $full->to);
    }

    public function testMailCreateTemplateSendsFullBodyIncludingDefaultFlag(): void
    {
        $this->transport->queueJson(201, '{"data":{"id":31,"name":"Follow up","subject":"Oferta {companyName}","body":"<p>{companyName}</p>","alias":"!follow_up","default":true}}');

        $template = $this->client()->mail()->createTemplate(new MailTemplateInput(
            name: 'Follow up',
            subject: 'Oferta {companyName}',
            body: '<p>{companyName}</p>',
            to: ['{email}'],
            default: true,
        ));

        $request = $this->transport->lastRequest();
        self::assertSame('POST', $request->method);
        self::assertSame('v2/mail/templates', $request->path);
        self::assertSame([
            'name' => 'Follow up',
            'subject' => 'Oferta {companyName}',
            'body' => '<p>{companyName}</p>',
            'to' => ['{email}'],
            'default' => true,
        ], $request->body);
        self::assertSame(31, $template->id);
        self::assertSame('Follow up', $template->name);
        // Alias nie ma dedykowanego pola w MailTemplate - zostaje w raw.
        self::assertSame('!follow_up', $template->raw['alias'] ?? null);
    }

    public function testMailTemplateCategoriesCrud(): void
    {
        $this->transport
            ->queueJson(200, '{"data":[{"id":1,"name":"Sprzedaż","parentId":null,"priority":10}]}')
            ->queueJson(201, '{"data":{"id":2,"name":"Serwis","parentId":null}}')
            ->queueJson(200, '{"data":{"id":2,"name":"Serwis","parentId":1,"priority":3}}');

        $client = $this->client();

        $categories = $client->mail()->templateCategories();
        self::assertSame('v2/mail/template/categories', $this->transport->lastRequest()->path);
        self::assertSame('Sprzedaż', $categories[0]->name);
        self::assertNull($categories[0]->parentId);

        $created = $client->mail()->createTemplateCategory(new CategoryInput(name: 'Serwis'));
        self::assertSame('POST', $this->transport->lastRequest()->method);
        self::assertSame(['name' => 'Serwis'], $this->transport->lastRequest()->body);
        self::assertSame(2, $created->id);

        $updated = $client->mail()->updateTemplateCategory(2, new CategoryInput(parentId: 1, priority: 3));
        self::assertSame('PUT', $this->transport->lastRequest()->method);
        self::assertSame('v2/mail/template/categories/2', $this->transport->lastRequest()->path);
        self::assertSame(['parentId' => 1, 'priority' => 3], $this->transport->lastRequest()->body);
        self::assertSame(1, $updated->parentId);
    }

    public function testMailTemplateAttachmentsList(): void
    {
        $this->transport->queueJson(200, '{"data":[{"id":"att-1","fileName":"regulamin.pdf","mimeType":"application/pdf","sizeBytes":2048,"downloadUrl":"https://storage.example.test/x?sig=1"}]}');

        $attachments = $this->client()->mail()->templateAttachments(2);

        $request = $this->transport->lastRequest();
        self::assertSame('GET', $request->method);
        self::assertSame('v2/mail/templates/2/attachments', $request->path);
        self::assertInstanceOf(MailTemplateAttachment::class, $attachments[0]);
        // id załącznika szablonu to STRING (nie liczba jak w encjach CRM).
        self::assertSame('att-1', $attachments[0]->id);
        self::assertSame('regulamin.pdf', $attachments[0]->fileName);
        self::assertNotNull($attachments[0]->downloadUrl);
    }

    public function testAddMailTemplateAttachmentUsesMultipartWithoutRetry(): void
    {
        // Upload nie jest idempotentny - błąd transportu nie może wywołać drugiej
        // próby (druga kopia pliku zostałaby w szablonie).
        $this->transport->queueThrowable(new TransportException('timeout'));

        try {
            $this->client()->mail()->addTemplateAttachment(2, FileUpload::fromString('%PDF', 'regulamin.pdf', 'application/pdf'));
            self::fail('Oczekiwano TransportException.');
        } catch (TransportException) {
            self::assertCount(1, $this->transport->requests);
        }

        $request = $this->transport->lastRequest();
        self::assertSame('POST', $request->method);
        self::assertSame('v2/mail/templates/2/attachments', $request->path);
        self::assertArrayHasKey('file', $request->files);
        self::assertSame('regulamin.pdf', $request->files['file']->fileName);
    }

    public function testAddMailTemplateAttachmentMapsResponse(): void
    {
        $this->transport->queueJson(201, '{"data":{"id":"att-9","fileName":"regulamin.pdf","mimeType":"application/pdf","sizeBytes":2048}}');

        $attachment = $this->client()->mail()->addTemplateAttachment(2, FileUpload::fromString('%PDF', 'regulamin.pdf', 'application/pdf'));

        self::assertInstanceOf(MailTemplateAttachment::class, $attachment);
        self::assertSame('att-9', $attachment->id);
        self::assertSame(2048, $attachment->sizeBytes);
    }

    public function testDeleteMailTemplateAttachment(): void
    {
        $this->transport->queueJson(204, '');

        $this->client()->mail()->deleteTemplateAttachment(2, 'abc');

        $request = $this->transport->lastRequest();
        self::assertSame('DELETE', $request->method);
        self::assertSame('v2/mail/templates/2/attachments/abc', $request->path);
    }

    public function testCalendarTypesDictionary(): void
    {
        $this->transport->queueJson(200, '{"data":[{"id":1,"name":"Tillio"},{"id":2,"name":"Google"}]}');

        $types = $this->client()->dictionaries()->calendarTypes();

        self::assertSame('GET', $this->transport->lastRequest()->method);
        self::assertSame('v2/calendar/types', $this->transport->lastRequest()->path);
        self::assertCount(2, $types);
        self::assertInstanceOf(DictionaryEntry::class, $types[0]);
        self::assertSame('Google', $types[1]->name);
    }

    public function testWikiFullCycleWithDelete(): void
    {
        $this->transport
            ->queueJson(200, '{"data":[{"id":1,"name":"Procedury","type":"procedure"}]}')
            ->queueJson(200, '{"data":[{"id":4,"baseId":1,"name":"Onboarding"}]}')
            ->queueJson(200, '{"data":[{"id":8,"title":"Pierwsze kroki","published":true}],"pagination":{"page":1,"limit":25,"total":1,"pages":1}}')
            ->queueJson(201, '{"data":{"id":9},"info":{"ids":{"entryId":9}}}')
            ->queueJson(200, '{}');

        $client = $this->client();

        self::assertSame('procedure', $client->wiki()->bases()[0]->type);
        self::assertSame('Onboarding', $client->wiki()->categories(1)[0]->name);
        self::assertTrue($client->wiki()->entries(['baseId' => 1])->first()?->published);

        $created = $client->wiki()->createEntry(['categoryId' => 4, 'title' => 'Nowy wpis']);
        self::assertSame(9, $created->id);

        $client->wiki()->deleteEntry(9);
        self::assertSame('DELETE', $this->transport->lastRequest()->method);
        self::assertSame('v2/wiki/entries/9', $this->transport->lastRequest()->path);
    }

    public function testWikiEntriesIterationDoesNotForceSort(): void
    {
        // Trasa wpisów wiki nie przyjmuje parametru `sort` - generator nie może
        // go narzucić.
        $this->transport->queueJson(200, '{"data":[{"id":8,"title":"Pierwsze kroki"}],"pagination":{"page":1,"limit":1000,"total":1,"pages":1}}');

        $entries = iterator_to_array($this->client()->wiki()->iterateEntries(['baseId' => 1]), false);

        self::assertSame('v2/wiki/entries', $this->transport->lastRequest()->path);
        self::assertArrayNotHasKey('sort', $this->transport->lastRequest()->query);
        self::assertSame('Pierwsze kroki', $entries[0]->title);
    }

    public function testModulesReturnsTypedModules(): void
    {
        $this->transport->queueJson(200, '{"data":[{"id":"tickets","name":"Zgłoszenia","active":true,"accessUntil":"2099-12-31"}]}');

        $modules = $this->client()->modules();

        self::assertSame('v2/modules', $this->transport->lastRequest()->path);
        self::assertSame('tickets', $modules[0]->id);
        self::assertTrue($modules[0]->active);
    }
}
