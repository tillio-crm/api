<?php

declare(strict_types=1);

namespace TillioCrm\Api\Exception;

use TillioCrm\Api\ApiError;

/**
 * 429 `rateLimit.exceeded`. Klient sam pilnuje własnych okien, więc 429 z serwera
 * oznacza, że limit po stronie v2 jest ciaśniejszy niż nasz albo ktoś jeszcze wali
 * tym samym kluczem (limit liczy się PER KLUCZ API). Niesie `Retry-After` (sekundy
 * do resetu okna) - klient używa go do backoffu z sufitem, a jeżeli próby się
 * wyczerpią, wartość leci dalej do wołającego.
 */
final class RateLimitException extends ApiException
{
    /**
     * @param list<ApiError>            $errors
     * @param array<string, mixed>|null $body
     */
    public function __construct(
        int $status,
        string $errorCode,
        string $message,
        public readonly ?float $retryAfterSeconds = null,
        array $errors = [],
        ?array $body = null,
        string $rawBody = '',
    ) {
        parent::__construct($status, $errorCode, $message, $errors, $body, $rawBody);
    }
}
