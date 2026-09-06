<?php

declare(strict_types=1);

namespace TillioCrm\Api\Exception;

/**
 * 401 - zły albo brakujący klucz/tenant. v2 celowo nie zdradza, co dokładnie nie gra
 * (`auth.invalidCredentials` na wszystko), więc powtarzanie żądania nie ma sensu:
 * przebieg przerywamy od razu, to błąd konfiguracji.
 */
final class AuthenticationException extends ApiException
{
}
