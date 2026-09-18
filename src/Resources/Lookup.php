<?php

declare(strict_types=1);

namespace TillioCrm\Api\Resources;

use TillioCrm\Api\Dto\Contact;
use TillioCrm\Api\Dto\Contractor;
use TillioCrm\Api\Dto\EmailLookupResult;
use TillioCrm\Api\Dto\PhoneLookupResult;

/**
 * Rozpoznanie rozmówcy: po numerze telefonu (kto dzwoni, API >= 2.10.0)
 * i po adresie e-mail (kto pisze, API >= 2.15.0). Oba dopasowania są DOKŁADNE -
 * inaczej niż filtry `phone`/`email` na listach, które szukają częściowo i tylko
 * po jednym polu.
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

    /**
     * `GET /v2/lookup/email?email=...` - kontakty i kontrahenci z tym adresem
     * (od API 2.15.0). Kontakt trafia na listę, gdy adres jest wśród jego
     * e-maili; kontrahent - gdy siedzi w polu `email` kartoteki.
     *
     * Adres podajesz w dowolnym zapisie (wielkie litery, forma
     * `Jan Kowalski <jan@acme.pl>`); API sprowadza go do kanonu i oddaje
     * w `EmailLookupResult::$email` - to po nim faktycznie szukało. Rekordy są
     * PEŁNE, jak w {@see phone()}: kontakt dokłada flagę `active` czytaną
     * z `$contact->raw['active']`. Brak trafień to 200 z pustymi listami,
     * adres niepoprawny albo brak parametru - 422.
     */
    public function email(string $email): EmailLookupResult
    {
        return EmailLookupResult::fromArray(self::single($this->client->get('v2/lookup/email', ['email' => $email])));
    }
}
