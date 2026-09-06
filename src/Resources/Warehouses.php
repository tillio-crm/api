<?php

declare(strict_types=1);

namespace TillioCrm\Api\Resources;

use TillioCrm\Api\Dto\Warehouse;
use TillioCrm\Api\Dto\WarehouseInput;
use TillioCrm\Api\Dto\WriteResult;
use TillioCrm\Api\Page;

/**
 * Magazyny. Stany magazynowe żyją w osobnym zasobie {@see Stocks}.
 */
final readonly class Warehouses extends Resource
{
    /**
     * `GET /v2/warehouses` - strona listy.
     *
     * Filtry (komplet wg kontraktu): `id`, `name`, `symbol`, `status`,
     * `sort`/`sortDir`, `page`/`limit`.
     *
     * @param array<string, mixed> $filters
     *
     * @return Page<Warehouse>
     */
    public function list(array $filters = []): Page
    {
        return self::mapPage($this->client->get('v2/warehouses', $filters), Warehouse::fromArray(...));
    }

    /**
     * Pełny przebieg wszystkich stron (generator, wymuszone `sort=id`).
     *
     * @param array<string, mixed> $filters
     *
     * @return \Generator<int, Warehouse>
     */
    public function iterate(array $filters = [], int $pageSize = 1000): \Generator
    {
        return $this->iterateMapped('v2/warehouses', Warehouse::fromArray(...), $filters, $pageSize);
    }

    /**
     * `GET /v2/warehouses/{id}` - pojedynczy magazyn.
     */
    public function get(int $id): Warehouse
    {
        return Warehouse::fromArray(self::single($this->client->get('v2/warehouses/' . $id)));
    }

    /**
     * `POST /v2/warehouses` - założenie magazynu. Wymagane `name` i `symbol`.
     *
     * @param WarehouseInput|array<string, mixed> $input
     */
    public function create(WarehouseInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->post('v2/warehouses', self::payload($input)), 'warehouseId');
    }

    /**
     * `PUT /v2/warehouses/{id}` - aktualizacja pól podanych w input.
     *
     * @param WarehouseInput|array<string, mixed> $input
     */
    public function update(int $id, WarehouseInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->put('v2/warehouses/' . $id, self::payload($input)), 'warehouseId');
    }
}
