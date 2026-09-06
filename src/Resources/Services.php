<?php

declare(strict_types=1);

namespace TillioCrm\Api\Resources;

use TillioCrm\Api\Dto\Service;
use TillioCrm\Api\Dto\ServiceInput;
use TillioCrm\Api\Dto\WriteResult;
use TillioCrm\Api\Page;

/**
 * Usługi u kontrahentów (instancje pozycji katalogu - katalog:
 * {@see ServiceCatalog}).
 */
final readonly class Services extends Resource
{
    /**
     * `GET /v2/services` - strona listy.
     *
     * Filtry (komplet wg kontraktu): `contractorId`, `serviceStatusId`,
     * `customName`, `currency`, `updatedAfter`/`updatedBefore`,
     * `createdAfter`/`createdBefore`, `customField[klucz]`, `sort`/`sortDir`,
     * `page`/`limit`.
     *
     * @param array<string, mixed> $filters
     *
     * @return Page<Service>
     */
    public function list(array $filters = []): Page
    {
        return self::mapPage($this->client->get('v2/services', $filters), Service::fromArray(...));
    }

    /**
     * Pełny przebieg wszystkich stron (generator, wymuszone `sort=id`).
     *
     * @param array<string, mixed> $filters
     *
     * @return \Generator<int, Service>
     */
    public function iterate(array $filters = [], int $pageSize = 1000): \Generator
    {
        return $this->iterateMapped('v2/services', Service::fromArray(...), $filters, $pageSize);
    }

    /**
     * `GET /v2/services/{id}` - pojedyncza usługa.
     */
    public function get(int $id): Service
    {
        return Service::fromArray(self::single($this->client->get('v2/services/' . $id)));
    }

    /**
     * `POST /v2/services` - nowa usługa. Wymagane `catalogId` i `contractorId`.
     *
     * @param ServiceInput|array<string, mixed> $input
     */
    public function create(ServiceInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->post('v2/services', self::payload($input)), 'serviceId');
    }

    /**
     * `PUT /v2/services/{id}` - aktualizacja pól podanych w input.
     *
     * @param ServiceInput|array<string, mixed> $input
     */
    public function update(int $id, ServiceInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->put('v2/services/' . $id, self::payload($input)), 'serviceId');
    }
}
