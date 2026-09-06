<?php

declare(strict_types=1);

namespace TillioCrm\Api\Exception;

/**
 * 400 (złe query) albo 422 (złe body) - rekord jest do odrzucenia, ale przebieg
 * integracji leci dalej. Lista `errors` niesie komplet powodów naraz.
 *
 * Konwencja v2: błąd w parametrach adresu (query string, ścieżka) to 400 z kodami
 * `query.*`, błąd w ciele żądania to 422 z kodami `body.*`. Dla konsumenta oba są
 * błędem żądania (żadnego retry) - rozróżnia je `ApiError::$code`.
 *
 * UWAGA na kolejność catchów: `FieldUnavailableException` i
 * `ExternalIdAlreadyUsedException` dziedziczą po tej klasie - jeżeli chcesz je
 * obsłużyć osobno, łap je PRZED `ValidationException`.
 */
class ValidationException extends ApiException
{
}
