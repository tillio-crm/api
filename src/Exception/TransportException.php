<?php

declare(strict_types=1);

namespace TillioCrm\Api\Exception;

/**
 * Żądania nie udało się dowieźć (DNS, timeout, zerwane połączenie) - API nawet nie
 * zdążyło odpowiedzieć. Osobno od `ApiException`, bo tu NIE MA statusu ani kontraktu
 * błędu do interpretacji, a decyzja retry jest inna: powtarzamy do wyczerpania prób.
 */
final class TransportException extends TillioApiException
{
}
