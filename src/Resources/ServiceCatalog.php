<?php

declare(strict_types=1);

namespace TillioCrm\Api\Resources;

use TillioCrm\Api\Dto\ServiceCatalogGroup;
use TillioCrm\Api\Dto\ServiceCatalogGroupInput;
use TillioCrm\Api\Dto\ServiceCatalogItem;
use TillioCrm\Api\Dto\ServiceCatalogItemInput;
use TillioCrm\Api\Dto\WriteResult;
use TillioCrm\Api\Page;

/**
 * Katalog usług (szablony) + grupy katalogu (drzewo).
 */
final readonly class ServiceCatalog extends Resource
{
    /**
     * `GET /v2/service/catalog` - strona listy pozycji katalogu.
     *
     * Filtry (komplet wg kontraktu): `active`, `name`, `groupId`,
     * `sort`/`sortDir`, `page`/`limit`.
     *
     * @param array<string, mixed> $filters
     *
     * @return Page<ServiceCatalogItem>
     */
    public function list(array $filters = []): Page
    {
        return self::mapPage($this->client->get('v2/service/catalog', $filters), ServiceCatalogItem::fromArray(...));
    }

    /**
     * Pełny przebieg wszystkich stron (generator, wymuszone `sort=id`).
     *
     * @param array<string, mixed> $filters
     *
     * @return \Generator<int, ServiceCatalogItem>
     */
    public function iterate(array $filters = [], int $pageSize = 1000): \Generator
    {
        return $this->iterateMapped('v2/service/catalog', ServiceCatalogItem::fromArray(...), $filters, $pageSize);
    }

    /**
     * `POST /v2/service/catalog` - nowa pozycja katalogu. Wymagane `name`
     * i `groupId`.
     *
     * @param ServiceCatalogItemInput|array<string, mixed> $input
     */
    public function create(ServiceCatalogItemInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->post('v2/service/catalog', self::payload($input)), 'catalogId');
    }

    /**
     * `PUT /v2/service/catalog/{id}` - aktualizacja pozycji katalogu.
     *
     * @param ServiceCatalogItemInput|array<string, mixed> $input
     */
    public function update(int $id, ServiceCatalogItemInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->put('v2/service/catalog/' . $id, self::payload($input)), 'catalogId');
    }

    /**
     * `GET /v2/service/catalog/groups` - wszystkie grupy katalogu (drzewo przez
     * `parentId`; lista bez stronicowania, trasa nie przyjmuje parametrów).
     *
     * @return list<ServiceCatalogGroup>
     */
    public function groups(): array
    {
        return self::mapList($this->client->get('v2/service/catalog/groups'), ServiceCatalogGroup::fromArray(...));
    }

    /**
     * `POST /v2/service/catalog/groups` - nowa grupa katalogu. Wymagane `name`.
     *
     * @param ServiceCatalogGroupInput|array<string, mixed> $input
     */
    public function createGroup(ServiceCatalogGroupInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->post('v2/service/catalog/groups', self::payload($input)), 'groupId');
    }

    /**
     * `PUT /v2/service/catalog/groups/{id}` - aktualizacja grupy katalogu.
     *
     * @param ServiceCatalogGroupInput|array<string, mixed> $input
     */
    public function updateGroup(int $id, ServiceCatalogGroupInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->put('v2/service/catalog/groups/' . $id, self::payload($input)), 'groupId');
    }
}
