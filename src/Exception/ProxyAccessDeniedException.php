<?php

declare(strict_types=1);

namespace TillioCrm\Api\Exception;

/**
 * 403 z proxy platformy - allowlista adaptera nie zezwala apce na tę operację.
 *
 * To NIE jest problem uprawnień w CRM: manifest apki nie deklaruje tej ścieżki
 * (albo zakres tokenu jej nie obejmuje). Naprawia się to w manifeście/zakresach
 * apki po stronie platformy, nie w kluczu API tenanta.
 */
final class ProxyAccessDeniedException extends ProxyException
{
}
