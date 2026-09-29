<?php

declare(strict_types=1);

namespace TillioCrm\Api\Tests;

use PHPUnit\Framework\TestCase;
use TillioCrm\Api\CustomFieldFilter;
use TillioCrm\Api\Exception\InvalidFilterException;
use TillioCrm\Api\QueryBuilder;

final class QueryBuilderTest extends TestCase
{
    public function testNullsAreRemoved(): void
    {
        self::assertSame(
            ['limit' => '25'],
            QueryBuilder::build(['limit' => 25, 'page' => null]),
        );
    }

    public function testDatesSerializedAsIso8601(): void
    {
        $date = new \DateTimeImmutable('2026-08-26 12:30:00', new \DateTimeZone('Europe/Warsaw'));

        self::assertSame(
            ['updatedAfter' => '2026-08-26T12:30:00+02:00'],
            QueryBuilder::build(['updatedAfter' => $date]),
        );
    }

    public function testBoolSerializedAsOneOrZero(): void
    {
        self::assertSame(
            ['open' => '1', 'archived' => '0'],
            QueryBuilder::build(['open' => true, 'archived' => false]),
        );
    }

    public function testEmptyStringInCustomFieldThrows(): void
    {
        // Pusta wartość znaczy w v2 "pole NIE ustawione" - przypadkowy pusty string
        // w zapytaniu o duplikat zwróciłby wszystkich niepowiązanych i skleił rekordy.
        $this->expectException(InvalidFilterException::class);
        $this->expectExceptionMessage('erp_id');
        QueryBuilder::build(['customField' => ['erp_id' => '']]);
    }

    public function testExplicitNotSetSerializesToEmptyString(): void
    {
        self::assertSame(
            ['customField' => ['erp_id' => '']],
            QueryBuilder::build(['customField' => ['erp_id' => CustomFieldFilter::NotSet]]),
        );
    }

    public function testEmptyStringOutsideCustomFieldPasses(): void
    {
        // Strażnik dotyczy tylko pól niestandardowych - tam pustka ma specjalne
        // znaczenie w kontrakcie; zwykły filtr z pustym stringiem odbije samo API.
        self::assertSame(['name' => ''], QueryBuilder::build(['name' => '']));
    }

    public function testNestedCustomFieldValidatedAtEveryLevel(): void
    {
        $this->expectException(InvalidFilterException::class);
        QueryBuilder::build(['filters' => ['customField' => ['klucz' => '']]]);
    }

    public function testEmptyNestedArrayIsRemoved(): void
    {
        self::assertSame([], QueryBuilder::build(['customField' => ['klucz' => null]]));
    }

    public function testListFilterIsJoinedWithCommas(): void
    {
        // API 2.17.0 przyjmuje wiele id jako `?id=1,2,3`; zagnieżdżone `id[0]=1`
        // byłoby dla v2 błędem 400. Duplikaty wypadają, kolejność zostaje.
        self::assertSame(
            ['id' => '12,15,18', 'ownerUserId' => '7', 'include' => 'contractor'],
            QueryBuilder::build(['id' => [12, 15, 12, 18], 'ownerUserId' => [7], 'include' => ['contractor']]),
        );
    }

    public function testListFilterAcceptsNumericStrings(): void
    {
        // Id z CSV albo bazy przychodzą często jako stringi - przechodzą bez rzutowania.
        self::assertSame(['leadId' => '659,660'], QueryBuilder::build(['leadId' => ['659', 660]]));
    }

    public function testListFilterOverLimitThrowsBeforeSending(): void
    {
        $this->expectException(InvalidFilterException::class);
        $this->expectExceptionMessage('najwyżej 100');
        QueryBuilder::build(['id' => range(1, QueryBuilder::MAX_LIST_VALUES + 1)]);
    }

    public function testListFilterAtLimitPasses(): void
    {
        $query = QueryBuilder::build(['id' => range(1, QueryBuilder::MAX_LIST_VALUES)]);
        self::assertSame(QueryBuilder::MAX_LIST_VALUES, substr_count((string) $query['id'], ',') + 1);
    }

    public function testDuplicatesDoNotCountTowardsLimit(): void
    {
        // API liczy limit po odrzuceniu powtórek - strażnik lokalny tak samo.
        $ids = array_merge(range(1, QueryBuilder::MAX_LIST_VALUES), range(1, 20));
        self::assertArrayHasKey('id', QueryBuilder::build(['id' => $ids]));
    }

    public function testEmptyListFilterThrows(): void
    {
        // Bez filtra zapytanie zwróciłoby WSZYSTKIE rekordy - pusta paczka id
        // nie może po cichu zamienić się w pobranie całej bazy.
        $this->expectException(InvalidFilterException::class);
        $this->expectExceptionMessage('ownerUserId');
        QueryBuilder::build(['ownerUserId' => []]);
    }

    public function testListElementMustBeIntOrPlainString(): void
    {
        $this->expectException(InvalidFilterException::class);
        QueryBuilder::build(['id' => [1, 2.5]]);
    }

    public function testListElementWithCommaIsRejected(): void
    {
        // "1,2" w jednym elemencie rozjechałoby się z liczeniem limitu i duplikatów.
        $this->expectException(InvalidFilterException::class);
        QueryBuilder::build(['id' => ['1,2']]);
    }

    public function testCustomFieldMapIsNotTreatedAsList(): void
    {
        // Mapa `customField` zostaje zagnieżdżona - tylko lista (klucze 0..n) jest filtrem-listą.
        self::assertSame(
            ['customField' => ['erp_id' => 'OPT-1']],
            QueryBuilder::build(['customField' => ['erp_id' => 'OPT-1']]),
        );
    }
}
