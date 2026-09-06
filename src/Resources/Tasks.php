<?php

declare(strict_types=1);

namespace TillioCrm\Api\Resources;

use TillioCrm\Api\Dto\Attachment;
use TillioCrm\Api\Dto\CategoryInput;
use TillioCrm\Api\Dto\Task;
use TillioCrm\Api\Dto\TaskComment;
use TillioCrm\Api\Dto\TaskCommentInput;
use TillioCrm\Api\Dto\TaskInput;
use TillioCrm\Api\Dto\TaskTemplate;
use TillioCrm\Api\Dto\TaskTemplateInput;
use TillioCrm\Api\Dto\TemplateCategory;
use TillioCrm\Api\Dto\WriteResult;
use TillioCrm\Api\Page;
use TillioCrm\Api\Transport\FileUpload;

/**
 * Zadania + komentarze + załączniki (upload wyłącznie multipart).
 */
final readonly class Tasks extends Resource
{
    /**
     * `GET /v2/tasks` - strona listy.
     *
     * Filtry (komplet wg kontraktu): `contractorId`, `contactId`, `projectId`,
     * `assignedUserId`, `done`, `archived`, `title`, `id`, `description`,
     * `taskTypeId`, `priority`, `leadId`, `ownerUserId`, `creatorUserId`,
     * `updatedAfter`/`updatedBefore`, `createdAfter`/`createdBefore`,
     * `customField[klucz]`, `sort`/`sortDir`, `page`/`limit`.
     *
     * @param array<string, mixed> $filters
     *
     * @return Page<Task>
     */
    public function list(array $filters = []): Page
    {
        return self::mapPage($this->client->get('v2/tasks', $filters), Task::fromArray(...));
    }

    /**
     * Pełny przebieg wszystkich stron (generator, wymuszone `sort=id`).
     *
     * @param array<string, mixed> $filters
     *
     * @return \Generator<int, Task>
     */
    public function iterate(array $filters = [], int $pageSize = 1000): \Generator
    {
        return $this->iterateMapped('v2/tasks', Task::fromArray(...), $filters, $pageSize);
    }

    /**
     * `GET /v2/tasks/{id}` - pojedyncze zadanie.
     */
    public function get(int $id): Task
    {
        return Task::fromArray(self::single($this->client->get('v2/tasks/' . $id)));
    }

    /**
     * `POST /v2/tasks` - nowe zadanie. Wymagane `title` i niepusta lista
     * `assignedUserIds`.
     *
     * @param TaskInput|array<string, mixed> $input
     */
    public function create(TaskInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->post('v2/tasks', self::payload($input)), 'taskId');
    }

    /**
     * `PUT /v2/tasks/{id}` - aktualizacja pól podanych w input.
     *
     * @param TaskInput|array<string, mixed> $input
     */
    public function update(int $id, TaskInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->put('v2/tasks/' . $id, self::payload($input)), 'taskId');
    }

    /**
     * `GET /v2/tasks/{id}/comments` - komentarze zadania (wątki przez
     * `parentCommentId`).
     *
     * @return list<TaskComment>
     */
    public function comments(int $taskId): array
    {
        return self::mapList(
            $this->client->get(sprintf('v2/tasks/%d/comments', $taskId)),
            TaskComment::fromArray(...),
        );
    }

    /**
     * `POST /v2/tasks/{id}/comments` - dopisuje komentarz do zadania
     * (HTML; parentCommentId = odpowiedź). Wymaga API >= 2.7.0.
     *
     * @param TaskCommentInput|array<string, mixed> $input
     */
    public function addComment(int $taskId, TaskCommentInput|array $input): TaskComment
    {
        return TaskComment::fromArray(self::single(
            $this->client->post(sprintf('v2/tasks/%d/comments', $taskId), self::payload($input)),
        ));
    }

    /**
     * `PUT /v2/tasks/{taskId}/comments/{commentId}` - edytuje komentarz zadania.
     * Wymaga API >= 2.7.0.
     *
     * @param TaskCommentInput|array<string, mixed> $input
     */
    public function updateComment(int $taskId, int $commentId, TaskCommentInput|array $input): TaskComment
    {
        return TaskComment::fromArray(self::single(
            $this->client->put(sprintf('v2/tasks/%d/comments/%d', $taskId, $commentId), self::payload($input)),
        ));
    }

    /**
     * `GET /v2/tasks/{id}/attachments` - załączniki zadania (`commentId` wskazuje
     * załącznik dodany w komentarzu). `downloadUrl` żyje ~1 minutę.
     *
     * @return list<Attachment>
     */
    public function attachments(int $taskId): array
    {
        return self::mapList(
            $this->client->get(sprintf('v2/tasks/%d/attachments', $taskId)),
            Attachment::fromArray(...),
        );
    }

    /**
     * `POST /v2/tasks/{id}/attachments` - upload załącznika (multipart, pole
     * `file`). BEZ RETRY: powtórka po timeoutcie, który doszedł, zostawiłaby
     * w CRM drugi plik. Od API 2.7.0 można podać `commentId` - załącznik ląduje
     * pod komentarzem zamiast na zadaniu.
     */
    public function addAttachment(int $taskId, FileUpload $file, ?int $commentId = null): WriteResult
    {
        $fields = [];
        if ($commentId !== null) {
            $fields['commentId'] = $commentId;
        }

        return WriteResult::fromResponse(
            $this->client->postMultipart(sprintf('v2/tasks/%d/attachments', $taskId), $fields, ['file' => $file]),
            'attachmentId',
        );
    }

    /**
     * `POST /v2/task/templates` - nowy szablon zadania (wymagane `name`).
     * Wymaga API >= 2.4.0.
     *
     * @param TaskTemplateInput|array<string, mixed> $input
     */
    public function createTemplate(TaskTemplateInput|array $input): TaskTemplate
    {
        return TaskTemplate::fromArray(self::single(
            $this->client->post('v2/task/templates', self::payload($input)),
        ));
    }

    /**
     * `GET /v2/task/template/categories` - kategorie szablonów zadań
     * (płaska lista, drzewo po `parentId`). Wymaga API >= 2.4.0.
     *
     * @return list<TemplateCategory>
     */
    public function templateCategories(): array
    {
        return self::mapList($this->client->get('v2/task/template/categories'), TemplateCategory::fromArray(...));
    }

    /**
     * `POST /v2/task/template/categories` - nowa kategoria szablonów
     * (wymagane `name`). Wymaga API >= 2.4.0.
     *
     * @param CategoryInput|array<string, mixed> $input
     */
    public function createTemplateCategory(CategoryInput|array $input): TemplateCategory
    {
        return TemplateCategory::fromArray(self::single(
            $this->client->post('v2/task/template/categories', self::payload($input)),
        ));
    }

    /**
     * `PUT /v2/task/template/categories/{id}` - edycja nazwy, rodzica
     * i priorytetu kategorii. Wymaga API >= 2.4.0.
     *
     * @param CategoryInput|array<string, mixed> $input
     */
    public function updateTemplateCategory(int $id, CategoryInput|array $input): TemplateCategory
    {
        return TemplateCategory::fromArray(self::single(
            $this->client->put('v2/task/template/categories/' . $id, self::payload($input)),
        ));
    }
}
