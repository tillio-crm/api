<?php

declare(strict_types=1);

namespace TillioCrm\Api\Resources;

use TillioCrm\Api\Dto\Product;
use TillioCrm\Api\Dto\ProductInput;
use TillioCrm\Api\Dto\WriteOptions;
use TillioCrm\Api\Dto\WriteResult;
use TillioCrm\Api\Page;

/**
 * Produkty (katalog). Kluczem integracji jest `externalId`
 * (a `duplicateCheck` zna też `sku` i `ean`).
 */
final readonly class Products extends Resource
{
    /**
     * `GET /v2/products` - strona listy.
     *
     * Filtry (komplet wg kontraktu): `name`, `sku`, `externalId`, `id`,
     * `description`, `ean`, `groupId` (grupy: `productGroups()->list()`),
     * `measureId`, `currency`, `status`, `updatedAfter`/`updatedBefore`,
     * `customField[klucz]`, `sort`/`sortDir`, `page`/`limit`.
     *
     * @param array<string, mixed> $filters
     *
     * @return Page<Product>
     */
    public function list(array $filters = []): Page
    {
        return self::mapPage($this->client->get('v2/products', $filters), Product::fromArray(...));
    }

    /**
     * Pełny przebieg wszystkich stron (generator, wymuszone `sort=id`).
     *
     * @param array<string, mixed> $filters
     *
     * @return \Generator<int, Product>
     */
    public function iterate(array $filters = [], int $pageSize = 1000): \Generator
    {
        return $this->iterateMapped('v2/products', Product::fromArray(...), $filters, $pageSize);
    }

    /**
     * `GET /v2/products/{id}` - pojedynczy produkt (trasa nie przyjmuje parametrów).
     */
    public function get(int $id): Product
    {
        return Product::fromArray(self::single($this->client->get('v2/products/' . $id)));
    }

    /**
     * `POST /v2/products` - założenie produktu (201) albo duplikat (200).
     * Wymagane `name`; `duplicateCheck` przyjmuje m.in. `externalId`, `sku`, `ean`.
     *
     * @param ProductInput|array<string, mixed> $input
     * @param WriteOptions|array<string, mixed> $options
     *
     * @throws \TillioCrm\Api\Exception\IncompleteDuplicateCheckException zanim żądanie
     *                                                                    wyjdzie - pole z duplicateCheck bez wartości w payloadzie
     */
    public function create(ProductInput|array $input, WriteOptions|array $options = []): WriteResult
    {
        $payload = self::payload($input) + self::payload($options);
        self::assertDuplicateCheckUsable($payload);

        return WriteResult::fromResponse($this->client->post('v2/products', $payload), 'productId');
    }

    /**
     * `PUT /v2/products/{id}` - aktualizacja pól podanych w input.
     *
     * @param ProductInput|array<string, mixed> $input
     */
    public function update(int $id, ProductInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->put('v2/products/' . $id, self::payload($input)), 'productId');
    }
}
