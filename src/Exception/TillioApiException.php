<?php

declare(strict_types=1);

namespace TillioCrm\Api\Exception;

/**
 * Wspólny przodek WSZYSTKICH błędów tego SDK - pozwala złapać "cokolwiek od Tillio"
 * jednym catchem, nie znając całej hierarchii.
 */
class TillioApiException extends \RuntimeException
{
}
