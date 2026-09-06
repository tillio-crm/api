<?php

declare(strict_types=1);

namespace TillioCrm\Api\Exception;

/**
 * 5xx - awaria po stronie v2/CRM. Nadaje się do retry z backoffem; jeśli i tak
 * doleci do wołającego, znaczy że próby się wyczerpały.
 */
class ServerException extends ApiException
{
}
