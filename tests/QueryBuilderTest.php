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
}
