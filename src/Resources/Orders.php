<?php

declare(strict_types=1);

namespace TillioCrm\Api\Resources;

use TillioCrm\Api\Dto\Order;
use TillioCrm\Api\Dto\OrderInput;
use TillioCrm\Api\Dto\WriteResult;
use TillioCrm\Api\Page;

/**
 * Zamówienia. Tworzenie jest podpięte pod kartotekę
 * (`POST /v2/contractors/{contractorId}/orders`); zamówienia nie podlegają
 * wstecznej edycji pozycji - `update()` zmienia metadane (status, daty, notatkę).
 */
final readonly class Orders extends Resource
{
    /**
     * `GET /v2/orders` - strona listy.
     *
     * Filtry (komplet wg kontraktu): `contractorId`, `orderStatusId` (statusy:
     * `dictionaries()->orderStatuses()`), `number` (pełny numer dokumentu),
     * `foreignNumber` (numer sekwencyjny - pewny klucz dla ERP), `id`,
     * `updatedAfter`/`updatedBefore`, `createdAfter`/`createdBefore`,
     * `customField[klucz]`, `include`, `sort`/`sortDir`, `page`/`limit`.
     *
     * @param array<string, mixed> $filters
     *
     * @return Page<Order>
     */
    public function list(array $filters = []): Page
    {
        return self::mapPage($this->client->get('v2/orders', $filters), Order::fromArray(...));
    }

    /**
     * Pełny przebieg wszystkich stron (generator, wymuszone `sort=id`).
     *
     * @param array<string, mixed> $filters
     *
     * @return \Generator<int, Order>
     */
    public function iterate(array $filters = [], int $pageSize = 1000): \Generator
    {
        return $this->iterateMapped('v2/orders', Order::fromArray(...), $filters, $pageSize);
    }

    /**
     * `GET /v2/orders/{id}` - pojedyncze zamówienie z pozycjami (trasa nie
     * przyjmuje parametrów).
     */
    public function get(int $id): Order
    {
        return Order::fromArray(self::single($this->client->get('v2/orders/' . $id)));
    }

    /**
     * `POST /v2/contractors/{contractorId}/orders` - nowe zamówienie dla
     * kartoteki. Wymagana niepusta lista `products`.
     *
     *     $client->orders()->create(42, new OrderInput(
     *         products: [new OrderProductInput(productSku: 'LIC-PRO', quantity: '2')],
     *     ));
     *
     * @param OrderInput|array<string, mixed> $input
     */
    public function create(int $contractorId, OrderInput|array $input): WriteResult
    {
        return WriteResult::fromResponse(
            $this->client->post(sprintf('v2/contractors/%d/orders', $contractorId), self::payload($input)),
            'orderId',
        );
    }

    /**
     * `PUT /v2/orders/{id}` - aktualizacja metadanych zamówienia.
     *
     * @param OrderInput|array<string, mixed> $input
     */
    public function update(int $id, OrderInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->put('v2/orders/' . $id, self::payload($input)), 'orderId');
    }
}
