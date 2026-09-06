<?php

declare(strict_types=1);

namespace TillioCrm\Api\Resources;

use TillioCrm\Api\Dto\Stock;
use TillioCrm\Api\Dto\StockInput;
use TillioCrm\Api\Page;

/**
 * Stany magazynowe - read-only odzwierciedlenie ilości z magazynu; rekord
 * identyfikuje PARA magazyn/produkt, nie własne id. Bez filtra przyrostowego
 * (`updatedAfter`) - stany czyta się pełnym przebiegiem.
 */
final readonly class Stocks extends Resource
{
    /**
     * `GET /v2/stocks` - strona listy.
     *
     * Filtry (komplet wg kontraktu): `warehouseId`, `productId`,
     * `sort`/`sortDir` (magazyn/produkt/ilość), `page`/`limit`.
     *
     * @param array<string, mixed> $filters
     *
     * @return Page<Stock>
     */
    public function list(array $filters = []): Page
    {
        return self::mapPage($this->client->get('v2/stocks', $filters), Stock::fromArray(...));
    }

    /**
     * Pełny przebieg wszystkich stron. BEZ wymuszania `sort=id` - stany nie mają
     * sortowalnego identyfikatora (sortowalne są magazyn/produkt/ilość); stały
     * porządek domyka API po swojej stronie parą kluczy magazyn/produkt.
     *
     * @param array<string, mixed> $filters
     *
     * @return \Generator<int, Stock>
     */
    public function iterate(array $filters = [], int $pageSize = 1000): \Generator
    {
        return $this->iterateMapped('v2/stocks', Stock::fromArray(...), $filters, $pageSize, stableSort: null);
    }

    /**
     * `PUT /v2/warehouses/{warehouseId}/stocks/{productId}` - zmiana stanu:
     * absolutna (`quantity`) ALBO względna (`adjustBy`), dokładnie jedno z dwóch.
     * Odpowiedź niesie PEŁNY stan po zapisie (bez koperty `info` - stany nie
     * mają własnego id), dlatego zwracamy {@see Stock}, nie WriteResult.
     *
     *     $stock = $client->stocks()->update(3, 120, new StockInput(adjustBy: '-2'));
     *     $stock->quantity;
     *
     * @param StockInput|array<string, mixed> $input
     */
    public function update(int $warehouseId, int $productId, StockInput|array $input): Stock
    {
        return Stock::fromArray(self::single(
            $this->client->put(sprintf('v2/warehouses/%d/stocks/%d', $warehouseId, $productId), self::payload($input)),
        ));
    }
}
