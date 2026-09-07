<?php

declare(strict_types=1);

namespace TillioCrm\Api\Resources;

use TillioCrm\Api\Dto\Contact;
use TillioCrm\Api\Dto\Contractor;
use TillioCrm\Api\Dto\PhoneLookupResult;

/**
 * Wyszukanie po numerze telefonu (kto dzwoni). Wymaga API >= 2.10.0.
 */
final readonly class Lookup extends Resource
{
    /**
     * `GET /v2/lookup/phone?number=...` - kontakty i kontrahenci z tym numerem.
     * Numer sprowadzany jest po stronie API do kanonu międzynarodowego.
     *
     * Od API 2.12.0 oba zestawy to PEŁNE rekordy ({@see Contact}, {@see Contractor}),
     * a nie okrojone podsumowania - jedno żądanie wystarcza, żeby pokazać dzwoniącego
     * razem z e-mailem, opiekunem i polami niestandardowymi. Odpowiedź dokłada przy
     * kontakcie flagę `active` (kontakt aktywny w CRM), której nie ma w zwykłym
     * `GET /v2/contacts/{id}` - czytasz ją z `$contact->raw['active']`.
     */
    public function phone(string $number): PhoneLookupResult
    {
        return PhoneLookupResult::fromArray(self::single($this->client->get('v2/lookup/phone', ['number' => $number])));
    }
}
