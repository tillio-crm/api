<?php

declare(strict_types=1);

namespace TillioCrm\Api\Tests;

use PHPUnit\Framework\TestCase;
use TillioCrm\Api\ApiResponse;
use TillioCrm\Api\Page;
use TillioCrm\Api\Tests\Support\FakeClock;
use TillioCrm\Api\Tests\Support\MockTransport;
use TillioCrm\Api\TillioClient;

final class PageIterateAllTest extends TestCase
{
    private MockTransport $transport;

    protected function setUp(): void
    {
        $this->transport = new MockTransport();
    }

    private function client(): TillioClient
    {
        return new TillioClient([
            'apiKey' => 'Test:klucz',
            'tenantDomain' => 'firma.tillio.app',
            'tenantId' => 'firma-abc123',
            'rateLimits' => [],
            'transport' => $this->transport,
            'clock' => new FakeClock(),
        ]);
    }

    public function testPageFromListEnvelope(): void
    {
        $page = Page::fromResponse(new ApiResponse(200, [
            'data' => [['id' => 1], ['id' => 2], 'smiec-nie-tablica'],
            'pagination' => ['page' => 2, 'limit' => 2, 'total' => 5, 'pages' => 3],
        ]));

        self::assertSame([['id' => 1], ['id' => 2]], $page->rows);
        self::assertSame(2, $page->page);
        self::assertSame(5, $page->total);
        self::assertTrue($page->hasNextPage());
        self::assertFalse($page->isEmpty());
        self::assertSame(['id' => 1], $page->first());
        self::assertCount(2, $page);
        self::assertSame([['id' => 1], ['id' => 2]], iterator_to_array($page));
    }

    public function testLastPageHasNoNextPage(): void
    {
        $page = new Page([['id' => 9]], page: 3, limit: 2, total: 5, pages: 3);

        self::assertFalse($page->hasNextPage());
    }

    public function testIterateAllWalksAllPagesAndForcesSortId(): void
    {
        $this->transport
            ->queueJson(200, '{"data":[{"id":1},{"id":2}],"pagination":{"page":1,"limit":2,"total":3,"pages":2}}')
            ->queueJson(200, '{"data":[{"id":3}],"pagination":{"page":2,"limit":2,"total":3,"pages":2}}');

        $rows = iterator_to_array($this->client()->iterateAll('v2/contractors', pageSize: 2), false);

        self::assertSame([['id' => 1], ['id' => 2], ['id' => 3]], $rows);
        self::assertCount(2, $this->transport->requests);

        // Wymuszone `sort=id`: LIMIT/OFFSET po zmiennej kolumnie gubi rekordy.
        self::assertSame('id', $this->transport->requests[0]->query['sort']);
        self::assertSame('1', $this->transport->requests[0]->query['page']);
        self::assertSame('2', $this->transport->requests[1]->query['page']);
    }

    public function testExplicitCallerSortWins(): void
    {
        $this->transport->queueJson(200, '{"data":[],"pagination":{"page":1,"limit":1000,"total":0,"pages":1}}');

        iterator_to_array($this->client()->iterateAll('v2/contractors', ['sort' => '-updatedAt']), false);

        self::assertSame('-updatedAt', $this->transport->lastRequest()->query['sort']);
    }

    public function testStableSortNullForcesNothing(): void
    {
        // Stany magazynowe nie mają sortowalnego id - tam wymuszenie dałoby 400.
        $this->transport->queueJson(200, '{"data":[],"pagination":{"page":1,"limit":1000,"total":0,"pages":1}}');

        iterator_to_array($this->client()->iterateAll('v2/stocks', stableSort: null), false);

        self::assertArrayNotHasKey('sort', $this->transport->lastRequest()->query);
    }

    public function testLoopPageAndLimitOverrideCallerFilters(): void
    {
        // Podane z zewnątrz `page` zamroziłoby numer strony - nieskończona pętla.
        $this->transport->queueJson(200, '{"data":[],"pagination":{"page":1,"limit":50,"total":0,"pages":1}}');

        iterator_to_array($this->client()->iterateAll('v2/contractors', ['page' => 7, 'limit' => 5], pageSize: 50), false);

        self::assertSame('1', $this->transport->lastRequest()->query['page']);
        self::assertSame('50', $this->transport->lastRequest()->query['limit']);
    }
}
