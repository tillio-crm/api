<?php

declare(strict_types=1);

namespace TillioCrm\Api\Resources;

use TillioCrm\Api\Dto\ProductGroup;
use TillioCrm\Api\Dto\ProductGroupInput;
use TillioCrm\Api\Dto\WriteResult;
use TillioCrm\Api\Page;

/**
 * Grupy produktów (drzewo przez `parentId`).
 */
final readonly class ProductGroups extends Resource
{
    /**
     * `GET /v2/product/groups` - strona listy.
     *
     * Filtry (komplet wg kontraktu): `name`, `parentId`, `id`, `status`,
     * `sort`/`sortDir`, `page`/`limit`.
     *
     * @param array<string, mixed> $filters
     *
     * @return Page<ProductGroup>
     */
    public function list(array $filters = []): Page
    {
        return self::mapPage($this->client->get('v2/product/groups', $filters), ProductGroup::fromArray(...));
    }

    /**
     * Pełny przebieg wszystkich stron (generator, wymuszone `sort=id`).
     *
     * @param array<string, mixed> $filters
     *
     * @return \Generator<int, ProductGroup>
     */
    public function iterate(array $filters = [], int $pageSize = 1000): \Generator
    {
        return $this->iterateMapped('v2/product/groups', ProductGroup::fromArray(...), $filters, $pageSize);
    }

    /**
     * `GET /v2/product/groups/{id}` - pojedyncza grupa.
     */
    public function get(int $id): ProductGroup
    {
        return ProductGroup::fromArray(self::single($this->client->get('v2/product/groups/' . $id)));
    }

    /**
     * `POST /v2/product/groups` - założenie grupy. Wymagane `name`.
     *
     * @param ProductGroupInput|array<string, mixed> $input
     */
    public function create(ProductGroupInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->post('v2/product/groups', self::payload($input)), 'groupId');
    }

    /**
     * `PUT /v2/product/groups/{id}` - aktualizacja pól podanych w input.
     *
     * @param ProductGroupInput|array<string, mixed> $input
     */
    public function update(int $id, ProductGroupInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->put('v2/product/groups/' . $id, self::payload($input)), 'groupId');
    }
}
