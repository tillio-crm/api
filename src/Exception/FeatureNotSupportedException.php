<?php

declare(strict_types=1);

namespace TillioCrm\Api\Exception;

/**
 * 501 `feature.notSupportedByCrmVersion` - trasa istnieje w API v2, ale CRM
 * TEJ instalacji jest starszy i nie ma jeszcze funkcji, której trasa wymaga
 * (np. przypinanie kontaktów do notatki, pola wielowartościowe). Reszta API
 * działa normalnie.
 *
 * Stan deterministyczny: ponowienie da ten sam wynik, więc SDK NIE ponawia.
 * Naprawia się aktualizacją CRM instalacji, nie zmianą żądania. Odróżnij od
 * {@see ServiceUnavailableException} (503 - moduł wyłączony w planie, np. brak
 * modułu kalendarza).
 */
final class FeatureNotSupportedException extends ApiException
{
    public const string ERROR_CODE = 'feature.notSupportedByCrmVersion';
}
