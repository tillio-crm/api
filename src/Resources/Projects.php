<?php

declare(strict_types=1);

namespace TillioCrm\Api\Resources;

use TillioCrm\Api\Dto\Project;
use TillioCrm\Api\Dto\ProjectInput;
use TillioCrm\Api\Dto\WriteResult;
use TillioCrm\Api\Page;

/**
 * Projekty.
 */
final readonly class Projects extends Resource
{
    /**
     * `GET /v2/projects` - strona listy.
     *
     * Filtry (komplet wg kontraktu): `contractorId`, `projectStatusId`, `name`,
     * `archived`, `updatedAfter`/`updatedBefore`, `createdAfter`/`createdBefore`,
     * `customField[klucz]`, `sort`/`sortDir`, `page`/`limit`.
     *
     * @param array<string, mixed> $filters
     *
     * @return Page<Project>
     */
    public function list(array $filters = []): Page
    {
        return self::mapPage($this->client->get('v2/projects', $filters), Project::fromArray(...));
    }

    /**
     * Pełny przebieg wszystkich stron (generator, wymuszone `sort=id`).
     *
     * @param array<string, mixed> $filters
     *
     * @return \Generator<int, Project>
     */
    public function iterate(array $filters = [], int $pageSize = 1000): \Generator
    {
        return $this->iterateMapped('v2/projects', Project::fromArray(...), $filters, $pageSize);
    }

    /**
     * `GET /v2/projects/{id}` - pojedynczy projekt.
     */
    public function get(int $id): Project
    {
        return Project::fromArray(self::single($this->client->get('v2/projects/' . $id)));
    }

    /**
     * `POST /v2/projects` - nowy projekt. Wymagane `name`.
     *
     * @param ProjectInput|array<string, mixed> $input
     */
    public function create(ProjectInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->post('v2/projects', self::payload($input)), 'projectId');
    }

    /**
     * `PUT /v2/projects/{id}` - aktualizacja pól podanych w input.
     *
     * @param ProjectInput|array<string, mixed> $input
     */
    public function update(int $id, ProjectInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->put('v2/projects/' . $id, self::payload($input)), 'projectId');
    }
}
