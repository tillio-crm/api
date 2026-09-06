<?php

declare(strict_types=1);

namespace TillioCrm\Api\Tests;

use PHPUnit\Framework\TestCase;
use TillioCrm\Api\Dto\Contact;
use TillioCrm\Api\Dto\Contractor;
use TillioCrm\Api\Dto\ContractorInput;
use TillioCrm\Api\Dto\StockInput;
use TillioCrm\Api\Dto\WriteOptions;
use TillioCrm\Api\Exception\IncompleteDuplicateCheckException;
use TillioCrm\Api\Tests\Support\FakeClock;
use TillioCrm\Api\Tests\Support\MockTransport;
use TillioCrm\Api\TillioClient;

/**
 * Zasoby kartotek: właściwe ścieżki/payloady, mapowanie na DTO i strażnik
 * kompletności duplicateCheck (żądanie z warunkiem bez wartości w payloadzie
 * nie wychodzi z SDK).
 */
final class MasterDataResourcesTest extends TestCase
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

    public function testContractorListMapsToDto(): void
    {
        $this->transport->queueJson(200, '{"data":[{"id":1,"name":"Acme"}],"pagination":{"page":1,"limit":25,"total":1,"pages":1}}');

        $page = $this->client()->contractors()->list(['taxId' => '0000000000']);

        self::assertSame('v2/contractors', $this->transport->lastRequest()->path);
        self::assertSame(['taxId' => '0000000000'], $this->transport->lastRequest()->query);
        $first = $page->first();
        self::assertInstanceOf(Contractor::class, $first);
        self::assertSame('Acme', $first->name);
    }

    public function testContractorListPassesIncludeAddressFilter(): void
    {
        // `include=address` to parametr trasy LISTY - trasa pojedynczego rekordu
        // nie przyjmuje parametrów.
        $this->transport->queueJson(200, '{"data":[{"id":42,"name":"Acme","ownerUserId":7}],"pagination":{"page":1,"limit":25,"total":1,"pages":1}}');

        $page = $this->client()->contractors()->list(['include' => 'address']);

        self::assertSame('v2/contractors', $this->transport->lastRequest()->path);
        self::assertSame(['include' => 'address'], $this->transport->lastRequest()->query);
        self::assertSame(7, $page->first()?->ownerUserId);
    }

    public function testContractorGetUsesIdPathWithoutQuery(): void
    {
        $this->transport->queueJson(200, '{"data":{"id":42,"name":"Acme","ownerUserId":7}}');

        $dto = $this->client()->contractors()->get(42);

        self::assertSame('v2/contractors/42', $this->transport->lastRequest()->path);
        self::assertSame([], $this->transport->lastRequest()->query);
        self::assertSame(7, $dto->ownerUserId);
    }

    public function testCreateMergesInputWithWriteOptions(): void
    {
        $this->transport->queueJson(201, '{"data":{"id":5},"info":{"created":true,"ids":{"contractorId":5}}}');

        $result = $this->client()->contractors()->create(
            new ContractorInput(name: 'Acme', alias: 'acme', contractorTypeId: 1, taxId: '0000000000'),
            new WriteOptions(duplicateCheck: ['taxId'], createSystemNote: true),
        );

        $request = $this->transport->lastRequest();
        self::assertSame('POST', $request->method);
        self::assertSame('v2/contractors', $request->path);
        self::assertSame([
            'name' => 'Acme',
            'alias' => 'acme',
            'taxId' => '0000000000',
            'contractorTypeId' => 1,
            'duplicateCheck' => ['taxId'],
            'createSystemNote' => true,
        ], $request->body);
        self::assertTrue($result->created);
        self::assertSame(5, $result->id);
    }

    public function testGuardBlocksDuplicateCheckWithoutValues(): void
    {
        // Cichy duplikat: ["custom:erp_id","taxId"] z samym NIP-em przechodzi przez
        // API (warunek custom pominięty z ostrzeżeniem) i robi drugą kartotekę.
        // SDK zatrzymuje takie żądanie lokalnie - transport nie może być tknięty.
        try {
            $this->client()->contractors()->create(
                new ContractorInput(name: 'Acme', contractorTypeId: 1, taxId: '0000000000'),
                new WriteOptions(duplicateCheck: ['custom:erp_id', 'taxId']),
            );
            self::fail('Oczekiwano IncompleteDuplicateCheckException.');
        } catch (IncompleteDuplicateCheckException $e) {
            self::assertSame(['custom:erp_id'], $e->missingFields);
            self::assertSame([], $this->transport->requests);
        }
    }

    public function testGuardSeesCustomFieldValues(): void
    {
        $this->transport->queueJson(201, '{"data":{"id":5},"info":{"created":true}}');

        $this->client()->contractors()->create(
            new ContractorInput(name: 'Acme', contractorTypeId: 1, customField: ['erp_id' => 'K-42']),
            new WriteOptions(duplicateCheck: ['custom:erp_id']),
        );

        self::assertCount(1, $this->transport->requests);
    }

    public function testGuardIsDisabledByAllowDuplicates(): void
    {
        // allowDuplicates wyłącza całe wyszukiwanie duplikatu po stronie API -
        // wymuszanie wartości pól nie miałoby czego chronić.
        $this->transport->queueJson(201, '{"data":{"id":6},"info":{"created":true}}');

        $this->client()->contractors()->create(
            ['name' => 'Acme', 'contractorTypeId' => 1, 'duplicateCheck' => ['taxId'], 'allowDuplicates' => true],
        );

        self::assertCount(1, $this->transport->requests);
    }

    public function testUpsertValidatesEachItemSeparately(): void
    {
        try {
            $this->client()->contractors()->upsert(
                [
                    new ContractorInput(name: 'A', taxId: '111'),
                    new ContractorInput(name: 'B'), // bez taxId - warunek niesprawdzalny
                ],
                new WriteOptions(duplicateCheck: ['taxId']),
            );
            self::fail('Oczekiwano IncompleteDuplicateCheckException.');
        } catch (IncompleteDuplicateCheckException) {
            self::assertSame([], $this->transport->requests);
        }
    }

    public function testUpsertBuildsBatchPayload(): void
    {
        $this->transport->queueJson(200, '{"data":{"results":[{"index":0,"status":"created","contractorId":1}]},"info":{"summary":{"total":1,"created":1}}}');

        $result = $this->client()->contractors()->upsert(
            [new ContractorInput(name: 'A', taxId: '111')],
            new WriteOptions(duplicateCheck: ['taxId']),
        );

        $request = $this->transport->lastRequest();
        self::assertSame('v2/contractors/upsert', $request->path);
        self::assertSame([
            'items' => [['name' => 'A', 'taxId' => '111']],
            'duplicateCheck' => ['taxId'],
        ], $request->body);
        self::assertFalse($result->hasFailures());
        self::assertSame(1, $result->createdCount());
    }

    public function testContractorAddressesFullCycle(): void
    {
        $this->transport
            ->queueJson(200, '{"data":[{"id":7,"addressTypeId":1,"city":"Warszawa"}]}')
            ->queueJson(201, '{"data":{"id":8}}')
            ->queueJson(200, '{"data":{"id":8}}')
            ->queueJson(200, '{}');

        $client = $this->client();

        $addresses = $client->contractors()->addresses(42);
        self::assertSame('v2/contractors/42/addresses', $this->transport->lastRequest()->path);
        self::assertSame('Warszawa', $addresses[0]->city);

        $created = $client->contractors()->addAddress(42, ['addressTypeId' => 1, 'city' => 'Kraków']);
        self::assertSame('POST', $this->transport->lastRequest()->method);
        self::assertSame(8, $created->id);

        $client->contractors()->updateAddress(8, ['city' => 'Gdańsk']);
        self::assertSame('PUT', $this->transport->lastRequest()->method);
        self::assertSame('v2/addresses/8', $this->transport->lastRequest()->path);

        $client->contractors()->deleteAddress(8);
        self::assertSame('DELETE', $this->transport->lastRequest()->method);
        self::assertSame('v2/addresses/8', $this->transport->lastRequest()->path);
    }

    public function testContactsAndProducts(): void
    {
        $this->transport
            ->queueJson(200, '{"data":[{"id":3,"firstName":"Jan","contractorIds":[42]}],"pagination":{"page":1,"limit":25,"total":1,"pages":1}}')
            ->queueJson(201, '{"data":{"id":9},"info":{"created":true,"ids":{"productId":9}}}');

        $client = $this->client();

        $contact = $client->contacts()->list()->first();
        self::assertInstanceOf(Contact::class, $contact);
        self::assertSame([42], $contact->contractorIds);

        $result = $client->products()->create(['name' => 'Licencja', 'sku' => 'LIC']);
        self::assertSame('v2/products', $this->transport->lastRequest()->path);
        self::assertSame(9, $result->id);
    }

    public function testStockIterationDoesNotForceSort(): void
    {
        // Stany nie mają sortowalnego id - wymuszenie `sort=id` dałoby 400.
        $this->transport->queueJson(200, '{"data":[{"warehouseId":3,"productId":120,"quantity":"5.000"}],"pagination":{"page":1,"limit":1000,"total":1,"pages":1}}');

        $stocks = iterator_to_array($this->client()->stocks()->iterate(), false);

        self::assertArrayNotHasKey('sort', $this->transport->lastRequest()->query);
        self::assertSame('5.000', $stocks[0]->quantity);
    }

    public function testStockUpdateUsesCompositeKeyAndReturnsStock(): void
    {
        // Odpowiedź niesie pełny stan po zapisie - metoda mapuje go na Dto\Stock.
        $this->transport->queueJson(200, '{"data":{"warehouseId":3,"productId":120,"quantity":"3.000"}}');

        $stock = $this->client()->stocks()->update(3, 120, new StockInput(adjustBy: '-2'));

        $request = $this->transport->lastRequest();
        self::assertSame('PUT', $request->method);
        self::assertSame('v2/warehouses/3/stocks/120', $request->path);
        self::assertSame(['adjustBy' => '-2'], $request->body);
        self::assertSame('3.000', $stock->quantity);
        self::assertSame(3, $stock->warehouseId);
        self::assertSame(120, $stock->productId);
    }

    public function testProductGroupsIterateForcesSortId(): void
    {
        $this->transport->queueJson(200, '{"data":[{"id":1,"name":"Licencje"}],"pagination":{"page":1,"limit":1000,"total":1,"pages":1}}');

        $groups = iterator_to_array($this->client()->productGroups()->iterate(), false);

        self::assertSame('v2/product/groups', $this->transport->lastRequest()->path);
        self::assertSame('id', $this->transport->lastRequest()->query['sort'] ?? null);
        self::assertSame('Licencje', $groups[0]->name);
    }

    public function testWarehousesIterateForcesSortId(): void
    {
        $this->transport->queueJson(200, '{"data":[{"id":3,"name":"Główny"}],"pagination":{"page":1,"limit":1000,"total":1,"pages":1}}');

        $warehouses = iterator_to_array($this->client()->warehouses()->iterate(), false);

        self::assertSame('v2/warehouses', $this->transport->lastRequest()->path);
        self::assertSame('id', $this->transport->lastRequest()->query['sort'] ?? null);
        self::assertSame('Główny', $warehouses[0]->name);
    }

    public function testOrderCreatedUnderContractor(): void
    {
        $this->transport->queueJson(201, '{"data":{"id":77},"info":{"created":true,"ids":{"orderId":77}}}');

        $result = $this->client()->orders()->create(42, [
            'products' => [['productSku' => 'LIC', 'quantity' => '1']],
        ]);

        self::assertSame('v2/contractors/42/orders', $this->transport->lastRequest()->path);
        self::assertSame(77, $result->id);
    }

    public function testAccessorsReturnSameInstances(): void
    {
        $client = $this->client();

        self::assertSame($client->contractors(), $client->contractors());
        self::assertSame($client->stocks(), $client->stocks());
    }
}
