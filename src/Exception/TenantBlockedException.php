<?php

declare(strict_types=1);

namespace TillioCrm\Api\Exception;

/**
 * 403 `tenant.blocked` - instancja klienta zablokowana (nieopłacona/wyłączona).
 * Odróżnione od zwykłego 403, bo to nie jest problem uprawnień klucza, tylko stan
 * konta: konsument ma to zaraportować operatorowi, a nie sugerować grzebanie w ACL.
 */
final class TenantBlockedException extends AccessDeniedException
{
    public const string ERROR_CODE = 'tenant.blocked';
}
