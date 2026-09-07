<?php

declare(strict_types=1);

namespace TillioCrm\Api\Resources;

use TillioCrm\Api\Dto\Attachment;
use TillioCrm\Api\Dto\CategoryInput;
use TillioCrm\Api\Dto\Note;
use TillioCrm\Api\Dto\NoteContact;
use TillioCrm\Api\Dto\NoteInput;
use TillioCrm\Api\Dto\NoteTemplate;
use TillioCrm\Api\Dto\NoteTemplateInput;
use TillioCrm\Api\Dto\TemplateCategory;
use TillioCrm\Api\Dto\WriteResult;
use TillioCrm\Api\Page;
use TillioCrm\Api\Transport\FileUpload;

/**
 * Notatki + ich załączniki (upload wyłącznie multipart - w API nie ma wariantu
 * JSON dla plików).
 */
final readonly class Notes extends Resource
{
    /**
     * `GET /v2/notes` - strona listy.
     *
     * Filtry (komplet wg kontraktu): `id`, `contractorId`, `contactId`, `leadId`,
     * `serviceId`, `pipelineId`, `noteTypeId` (typy: `dictionaries()->noteTypes()`),
     * `title`, `body`, `pinned`, `creatorUserId`, `createdAfter`/`createdBefore`,
     * `customField[klucz]`, `sort`/`sortDir`, `page`/`limit`.
     *
     * `id`, `leadId`, `serviceId`, `pipelineId`, `body`, `pinned` i `creatorUserId`
     * wymagają API >= 2.12.2 - starsza instancja odrzuci nieznany parametr błędem 400.
     *
     * @param array<string, mixed> $filters
     *
     * @return Page<Note>
     */
    public function list(array $filters = []): Page
    {
        return self::mapPage($this->client->get('v2/notes', $filters), Note::fromArray(...));
    }

    /**
     * Pełny przebieg wszystkich stron (generator, wymuszone `sort=id`).
     *
     * @param array<string, mixed> $filters
     *
     * @return \Generator<int, Note>
     */
    public function iterate(array $filters = [], int $pageSize = 1000): \Generator
    {
        return $this->iterateMapped('v2/notes', Note::fromArray(...), $filters, $pageSize);
    }

    /**
     * `GET /v2/notes/{id}` - pojedyncza notatka.
     */
    public function get(int $id): Note
    {
        return Note::fromArray(self::single($this->client->get('v2/notes/' . $id)));
    }

    /**
     * `POST /v2/contractors/{contractorId}/notes` - nowa notatka u kontrahenta.
     * Wymagane `noteTypeId` i `title`.
     *
     * @param NoteInput|array<string, mixed> $input
     */
    public function create(int $contractorId, NoteInput|array $input): WriteResult
    {
        return WriteResult::fromResponse(
            $this->client->post(sprintf('v2/contractors/%d/notes', $contractorId), self::payload($input)),
            'noteId',
        );
    }

    /**
     * `PUT /v2/notes/{id}` - aktualizacja pól podanych w input.
     *
     * @param NoteInput|array<string, mixed> $input
     */
    public function update(int $id, NoteInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->put('v2/notes/' . $id, self::payload($input)), 'noteId');
    }

    /**
     * `GET /v2/notes/{id}/attachments` - załączniki notatki. `downloadUrl`
     * w rekordach żyje ~1 minutę - pobieraj od razu
     * ({@see \TillioCrm\Api\TillioClient::download()}).
     *
     * @return list<Attachment>
     */
    public function attachments(int $noteId): array
    {
        return self::mapList(
            $this->client->get(sprintf('v2/notes/%d/attachments', $noteId)),
            Attachment::fromArray(...),
        );
    }

    /**
     * `POST /v2/notes/{id}/attachments` - upload załącznika (multipart, pole
     * `file`). BEZ RETRY: powtórka po timeoutcie, który doszedł, zostawiłaby
     * w CRM drugi plik.
     *
     *     $client->notes()->addAttachment(7, FileUpload::fromPath('C:/oferta.pdf'));
     */
    public function addAttachment(int $noteId, FileUpload $file): WriteResult
    {
        return WriteResult::fromResponse(
            $this->client->postMultipart(sprintf('v2/notes/%d/attachments', $noteId), [], ['file' => $file]),
            'attachmentId',
        );
    }

    /**
     * `POST /v2/note/templates` - nowy szablon notatki (wymagane `noteTypeId`,
     * `name`, `title`). Wymaga API >= 2.4.0.
     *
     * @param NoteTemplateInput|array<string, mixed> $input
     */
    public function createTemplate(NoteTemplateInput|array $input): NoteTemplate
    {
        return NoteTemplate::fromArray(self::single(
            $this->client->post('v2/note/templates', self::payload($input)),
        ));
    }

    /**
     * `GET /v2/note/template/categories` - kategorie szablonów notatek
     * (płaska lista, drzewo po `parentId`). Wymaga API >= 2.4.0.
     *
     * @return list<TemplateCategory>
     */
    public function templateCategories(): array
    {
        return self::mapList($this->client->get('v2/note/template/categories'), TemplateCategory::fromArray(...));
    }

    /**
     * `POST /v2/note/template/categories` - nowa kategoria szablonów
     * (wymagane `name`). Wymaga API >= 2.4.0.
     *
     * @param CategoryInput|array<string, mixed> $input
     */
    public function createTemplateCategory(CategoryInput|array $input): TemplateCategory
    {
        return TemplateCategory::fromArray(self::single(
            $this->client->post('v2/note/template/categories', self::payload($input)),
        ));
    }

    /**
     * `PUT /v2/note/template/categories/{id}` - edycja nazwy, rodzica
     * i priorytetu kategorii. Wymaga API >= 2.4.0.
     *
     * @param CategoryInput|array<string, mixed> $input
     */
    public function updateTemplateCategory(int $id, CategoryInput|array $input): TemplateCategory
    {
        return TemplateCategory::fromArray(self::single(
            $this->client->put('v2/note/template/categories/' . $id, self::payload($input)),
        ));
    }

    /**
     * Kontakty przypięte do notatki. Wymaga API >= 2.8.0.
     *
     * @return list<NoteContact>
     */
    public function contacts(int $noteId): array
    {
        return self::mapList(
            $this->client->get(sprintf('v2/notes/%d/contacts', $noteId)),
            NoteContact::fromArray(...),
        );
    }

    /**
     * Przypina kontakt do notatki; zwraca pełną listę po zmianie. Ponowne
     * przypięcie istniejącego jest bezpieczne (potwierdza stan). Wymaga API >= 2.8.0.
     *
     * @return list<NoteContact>
     */
    public function addContact(int $noteId, int $contactId): array
    {
        return self::mapList(
            $this->client->post(sprintf('v2/notes/%d/contacts', $noteId), ['contactId' => $contactId]),
            NoteContact::fromArray(...),
        );
    }

    /**
     * Odpina kontakt od notatki. Wymaga API >= 2.8.0.
     */
    public function removeContact(int $noteId, int $contactId): void
    {
        $this->client->delete(sprintf('v2/notes/%d/contacts/%d', $noteId, $contactId));
    }
}
