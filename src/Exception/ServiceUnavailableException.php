<?php

declare(strict_types=1);

namespace TillioCrm\Api\Exception;

/**
 * 503 z kodem symbolicznym innym niż przerwa serwisowa - funkcja niedostępna
 * w TEJ instancji (np. `calendar.serviceUnavailable`: instalacja bez modułu
 * kalendarza). Stan deterministyczny: ponowienie da ten sam wynik, więc SDK
 * NIE ponawia (w odróżnieniu od gołego 503 bez kodu, które traktujemy jak
 * chwilową awarię). Naprawia się w konfiguracji instancji, nie w kodzie.
 */
final class ServiceUnavailableException extends ApiException
{
}
