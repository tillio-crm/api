<?php

declare(strict_types=1);

namespace TillioCrm\Api\Exception;

use TillioCrm\Api\ApiError;

/**
 * API v2 odpowiedziało statusem błędu. Niesie CAŁY kontrakt `_error`:
 * kod HTTP, symboliczny kod błędu (`validation.error`, `auth.invalidCredentials`…)
 * i listę `{field, code, message}`.
 *
 * DLACZEGO osobne podklasy zamiast samego statusu: konsument reaguje inaczej na 401
 * (przerwij cały przebieg - zły klucz), 422 (odrzuć rekord, leć dalej) i 429/5xx
 * (poczekaj i powtórz). `instanceof` jest tu czytelniejszy i trudniejszy do
 * pomylenia niż `if ($e->getCode() === 422)`.
 */
class ApiException extends TillioApiException
{
    /**
     * @param int                       $status    kod HTTP odpowiedzi
     * @param string                    $errorCode symboliczny kod z `_error.message` (np. `validation.error`)
     * @param string                    $message   komunikat gotowy do logu
     * @param list<ApiError>            $errors    typowana lista `{field, code, message}`
     * @param array<string, mixed>|null $body      pełne zdekodowane body (null, gdy nie był to JSON)
     * @param string                    $rawBody   surowe body - do diagnozy odpowiedzi spoza kontraktu
     */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $errors = [],
        public readonly ?array $body = null,
        public readonly string $rawBody = '',
    ) {
        parent::__construct($message, $status);
    }

    /**
     * Czy w liście błędów jest wpis o tym kodzie (np. `body.fieldNotUpdatable`).
     */
    public function hasErrorCode(string $code): bool
    {
        foreach ($this->errors as $error) {
            if ($error->code === $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * Błędy dotyczące konkretnego pola.
     *
     * @return list<ApiError>
     */
    public function errorsForField(string $field): array
    {
        return array_values(array_filter($this->errors, static fn (ApiError $e): bool => $e->field === $field));
    }

    /**
     * Zwięzły opis do logu: kod + wszystkie pola, których dotyczy.
     */
    public function describe(): string
    {
        if ($this->errors === []) {
            return sprintf('HTTP %d %s', $this->status, $this->errorCode);
        }

        return sprintf(
            'HTTP %d %s: %s',
            $this->status,
            $this->errorCode,
            implode('; ', array_map(static fn (ApiError $e): string => (string) $e, $this->errors)),
        );
    }
}
