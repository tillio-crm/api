<?php

declare(strict_types=1);

namespace TillioCrm\Api\Resources;

use TillioCrm\Api\Dto\PipelineItem;
use TillioCrm\Api\Dto\PipelineItemInput;
use TillioCrm\Api\Dto\WriteResult;
use TillioCrm\Api\Page;

/**
 * Szanse sprzedaży (pozycje lejków). Lejki i ich etapy:
 * `Dictionaries::pipelineFunnels()`.
 */
final readonly class PipelineItems extends Resource
{
    /**
     * `GET /v2/pipeline/items` - strona listy.
     *
     * Filtry (komplet wg kontraktu): `contractorId`, `pipelineStageId`,
     * `pipelineStatusId` (etapy i statusy z lejków: `dictionaries()->pipelineFunnels()`),
     * `ownerUserId`, `name`, `id`, `creatorUserId`, `closerUserId`, `currency`,
     * `probability`, `note`, `externalId`, `leadId`, `updatedAfter`/`updatedBefore`,
     * `createdAfter`/`createdBefore`, `customField[klucz]`, `sort`/`sortDir`,
     * `page`/`limit`.
     *
     * @param array<string, mixed> $filters
     *
     * @return Page<PipelineItem>
     */
    public function list(array $filters = []): Page
    {
        return self::mapPage($this->client->get('v2/pipeline/items', $filters), PipelineItem::fromArray(...));
    }

    /**
     * Pełny przebieg wszystkich stron (generator, wymuszone `sort=id`).
     *
     * @param array<string, mixed> $filters
     *
     * @return \Generator<int, PipelineItem>
     */
    public function iterate(array $filters = [], int $pageSize = 1000): \Generator
    {
        return $this->iterateMapped('v2/pipeline/items', PipelineItem::fromArray(...), $filters, $pageSize);
    }

    /**
     * `GET /v2/pipeline/items/{id}` - pojedyncza szansa.
     */
    public function get(int $id): PipelineItem
    {
        return PipelineItem::fromArray(self::single($this->client->get('v2/pipeline/items/' . $id)));
    }

    /**
     * `POST /v2/pipeline/items` - nowa szansa. Wymagane `name`,
     * `pipelineStageId` i `contractorId`.
     *
     * @param PipelineItemInput|array<string, mixed> $input
     */
    public function create(PipelineItemInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->post('v2/pipeline/items', self::payload($input)), 'pipelineItemId');
    }

    /**
     * `PUT /v2/pipeline/items/{id}` - aktualizacja pól podanych w input
     * (etap/status zmienia proces lejka, nie ten zapis).
     *
     * @param PipelineItemInput|array<string, mixed> $input
     */
    public function update(int $id, PipelineItemInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->put('v2/pipeline/items/' . $id, self::payload($input)), 'pipelineItemId');
    }
}
