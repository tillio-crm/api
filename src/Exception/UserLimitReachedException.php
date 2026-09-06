<?php

declare(strict_types=1);

namespace TillioCrm\Api\Exception;

/**
 * 409 `user.limitReached` - instancja wyczerpała limit AKTYWNYCH kont użytkowników.
 *
 * Z żądaniem jest wszystko w porządku, a mimo to konto nie powstaje - to STAN
 * BIZNESOWY, nie awaria. Konsument ma na to zareagować inaczej niż na 5xx (ponawiaj)
 * i na 422 (popraw dane): zatrzymać import, pokazać człowiekowi "skończyły się
 * licencje" i poczekać na decyzję. Retry nigdy nie ma szans się udać.
 *
 * OBEJŚCIE przy imporcie danych historycznych: konta zakładane ze statusem
 * NIEAKTYWNYM nie liczą się do limitu.
 */
final class UserLimitReachedException extends ApiException
{
    public const string ERROR_CODE = 'user.limitReached';
}
