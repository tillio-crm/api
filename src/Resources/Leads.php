<?php

declare(strict_types=1);

namespace TillioCrm\Api\Resources;

use TillioCrm\Api\Dto\Lead;
use TillioCrm\Api\Dto\LeadInput;
use TillioCrm\Api\Dto\WriteResult;
use TillioCrm\Api\Page;

/**
 * Leady.
 */
final readonly class Leads extends Resource
{
    /**
     * `GET /v2/leads` - strona listy.
     *
     * Filtry (komplet wg kontraktu): `leadStatusId`, `leadStageId` (statusy i
     * etapy z procesów leadowych: `dictionaries()->leadProcesses()`), `ownerUserId`,
     * `title`, `taxId`, `id`, `statusChangeReasonId`, `categoryId`,
     * `contractorSourceId` (`dictionaries()->contractorSources()`), `priority`,
     * `creatorUserId`, `contractorId`, `contactId`, `salesPipelineId`, `companyName`,
     * `regon`, `domain`, `firstName`, `lastName`, `position`, `phone`,
     * `phoneAlternative`, `street`, `postCode`, `city`, `region`, `country`,
     * `updatedAfter`/`updatedBefore`, `createdAfter`/`createdBefore`,
     * `customField[klucz]`, `sort`/`sortDir`, `page`/`limit`.
     *
     * @param array<string, mixed> $filters
     *
     * @return Page<Lead>
     */
    public function list(array $filters = []): Page
    {
        return self::mapPage($this->client->get('v2/leads', $filters), Lead::fromArray(...));
    }

    /**
     * Pełny przebieg wszystkich stron (generator, wymuszone `sort=id`).
     *
     * @param array<string, mixed> $filters
     *
     * @return \Generator<int, Lead>
     */
    public function iterate(array $filters = [], int $pageSize = 1000): \Generator
    {
        return $this->iterateMapped('v2/leads', Lead::fromArray(...), $filters, $pageSize);
    }

    /**
     * `GET /v2/leads/{id}` - pojedynczy lead (trasa nie przyjmuje parametrów).
     */
    public function get(int $id): Lead
    {
        return Lead::fromArray(self::single($this->client->get('v2/leads/' . $id)));
    }

    /**
     * `POST /v2/leads` - nowy lead. Wymagane `title`.
     *
     * @param LeadInput|array<string, mixed> $input
     */
    public function create(LeadInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->post('v2/leads', self::payload($input)), 'leadId');
    }

    /**
     * `PUT /v2/leads/{id}` - aktualizacja pól podanych w input.
     *
     * @param LeadInput|array<string, mixed> $input
     */
    public function update(int $id, LeadInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->put('v2/leads/' . $id, self::payload($input)), 'leadId');
    }
}
