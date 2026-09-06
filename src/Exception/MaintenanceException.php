<?php

declare(strict_types=1);

namespace TillioCrm\Api\Exception;

/**
 * 503 `system.maintenance` - zaplanowana przerwa serwisowa Tillio (cała strefa /v2).
 *
 * DLACZEGO osobno od zwykłego 5xx: przerwa trwa minuty, nie milisekundy, więc
 * backoff w ramach jednego żądania jest bez sensu. Klient NIE ponawia takiego
 * żądania - przebieg ma się zatrzymać i wrócić przy następnym harmonogramie.
 */
final class MaintenanceException extends ServerException
{
    public const string ERROR_CODE = 'system.maintenance';
}
