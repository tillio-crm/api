<?php

declare(strict_types=1);

namespace TillioCrm\Api\Resources;

use TillioCrm\Api\Dto\PhoneLookupResult;

/**
 * Wyszukanie po numerze telefonu (kto dzwoni). Wymaga API >= 2.10.0.
 */
final readonly class Lookup extends Resource
{
    /**
     * `GET /v2/lookup/phone?number=...` - kontakty i kontrahenci z tym numerem.
     * Numer sprowadzany jest po stronie API do kanonu miedzynarodowego.
     */
    public function phone(string $number): PhoneLookupResult
    {
        return PhoneLookupResult::fromArray(self::single($this->client->get('v2/lookup/phone', ['number' => $number])));
    }
}
