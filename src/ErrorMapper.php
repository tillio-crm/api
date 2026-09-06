<?php

declare(strict_types=1);

namespace TillioCrm\Api;

use TillioCrm\Api\Exception\AccessDeniedException;
use TillioCrm\Api\Exception\ApiException;
use TillioCrm\Api\Exception\AuthenticationException;
use TillioCrm\Api\Exception\ExternalIdAlreadyUsedException;
use TillioCrm\Api\Exception\FeatureNotSupportedException;
use TillioCrm\Api\Exception\FieldUnavailableException;
use TillioCrm\Api\Exception\MaintenanceException;
use TillioCrm\Api\Exception\NotFoundException;
use TillioCrm\Api\Exception\ProxyAccessDeniedException;
use TillioCrm\Api\Exception\ProxyBinaryResponseException;
use TillioCrm\Api\Exception\ProxyException;
use TillioCrm\Api\Exception\RateLimitException;
use TillioCrm\Api\Exception\ServerException;
use TillioCrm\Api\Exception\ServiceUnavailableException;
use TillioCrm\Api\Exception\TenantBlockedException;
use TillioCrm\Api\Exception\UserLimitReachedException;
use TillioCrm\Api\Exception\ValidationException;
use TillioCrm\Api\Transport\TransportResponse;

/**
 * Jedyne miejsce, w którym kontrakt błędu v2 zamienia się na wyjątek.
 *
 * Kontrakt: `{"_error": {"code": 422, "message": "validation.error",
 * "errors": [{"field", "code", "message"}]}}`. v2 zawsze zwraca prawdziwy status
 * HTTP, więc decyzję podejmujemy po statusie, a symboliczny `message` służy do
 * rozróżnień w obrębie tego samego kodu (403 `tenant.blocked`,
 * 409 `user.limitReached`, 503 `system.maintenance`).
 *
 * TRYB PROXY: platforma odpowiada TĄ SAMĄ kopertą `_error`, ale jej `message` to
 * ludzkie zdanie ("Operacja GET ... nie jest dozwolona przez adapter usługi
 * 'tillio'."), a API v2 daje tam ZAWSZE kod symboliczny (`kropka.wewnątrz`).
 * Po tym rozpoznajemy, KTO odmówił - bo 403 z proxy (allowlista apki) naprawia
 * się w zupełnie innym miejscu niż 403 z API (uprawnienia klucza w CRM).
 */
final class ErrorMapper
{
    /**
     * Mapuje odpowiedź błędu (status >= 400) na właściwy wyjątek.
     *
     * @param bool $viaProxy czy żądanie szło przez proxy platformy (tryb accessToken)
     */
    public static function map(TransportResponse $response, bool $viaProxy = false): ApiException
    {
        $decoded = json_decode($response->body, true);
        $body = is_array($decoded) ? $decoded : null;
        $error = is_array($body['_error'] ?? null) ? $body['_error'] : [];

        $rawMessage = $error['message'] ?? '';
        $errorCode = is_scalar($rawMessage) ? (string) $rawMessage : '';
        $errors = [];
        foreach ((array) ($error['errors'] ?? []) as $row) {
            if (is_array($row)) {
                $errors[] = ApiError::fromArray($row);
            }
        }

        $message = self::humanMessage($response->status, $errorCode, $errors, $response->body);
        $status = $response->status;

        // Błąd zgłoszony przez PROXY PLATFORMY, nie przez API - rozpoznawany po tym,
        // że `message` jest zdaniem, a nie kodem symbolicznym.
        if ($viaProxy && $errorCode !== '' && !self::isSymbolicCode($errorCode)) {
            // Proxy nie przepuszcza binariów (deterministyczne 502) - pliki pobiera
            // się podpisanym URL-em z metadanych, nie przez proxy.
            if (str_contains($errorCode, 'nieobsługiwany typ treści')) {
                return new ProxyBinaryResponseException(
                    $status,
                    'proxy.binaryResponse',
                    $message . ' - pobierz plik podpisanym URL-em (downloadUrl z metadanych), proxy nie przenosi binariów.',
                    $errors,
                    $body,
                    $response->body,
                );
            }

            if ($status === 403) {
                return new ProxyAccessDeniedException($status, 'proxy.accessDenied', $message, $errors, $body, $response->body);
            }

            return new ProxyException($status, 'proxy.error', $message, $errors, $body, $response->body);
        }

        // 401: jednolita odmowa v2 - nie wiadomo (celowo), czy padł tenant, bucket czy klucz.
        if ($status === 401) {
            return new AuthenticationException($status, $errorCode !== '' ? $errorCode : 'auth.invalidCredentials', $message, $errors, $body, $response->body);
        }

        if ($status === 403) {
            return $errorCode === TenantBlockedException::ERROR_CODE
                ? new TenantBlockedException($status, $errorCode, $message, $errors, $body, $response->body)
                : new AccessDeniedException($status, $errorCode !== '' ? $errorCode : 'access.denied', $message, $errors, $body, $response->body);
        }

        if ($status === 404) {
            return new NotFoundException($status, $errorCode !== '' ? $errorCode : 'resource.notFound', $message, $errors, $body, $response->body);
        }

        // 409 `user.limitReached`: żądanie jest poprawne, ale instancja nie ma wolnej
        // licencji. STAN BIZNESOWY, nie awaria - warunkujemy kodem symbolicznym, żeby
        // inne 409 nie awansowały przypadkiem do "skończyły się licencje".
        if ($status === 409 && $errorCode === UserLimitReachedException::ERROR_CODE) {
            return new UserLimitReachedException($status, $errorCode, $message, $errors, $body, $response->body);
        }

        if ($status === 429) {
            return new RateLimitException(
                $status,
                $errorCode !== '' ? $errorCode : 'rateLimit.exceeded',
                $message,
                self::retryAfter($response),
                $errors,
                $body,
                $response->body,
            );
        }

        if ($status === 400 || $status === 422) {
            // Dwa przypadki walidacji, które NIE są błędem payloadu i wymagają innej
            // reakcji niż "popraw wartość" - dostają własne typy, zanim zdegradujemy
            // je do zwykłej walidacji.
            foreach ($errors as $item) {
                if ($item->code === FieldUnavailableException::ERROR_CODE) {
                    return new FieldUnavailableException($status, $errorCode !== '' ? $errorCode : 'validation.error', $message, $errors, $body, $response->body);
                }

                if ($item->code === ExternalIdAlreadyUsedException::ERROR_CODE) {
                    return new ExternalIdAlreadyUsedException($status, $errorCode !== '' ? $errorCode : 'validation.error', $message, $errors, $body, $response->body);
                }
            }

            return new ValidationException($status, $errorCode !== '' ? $errorCode : 'validation.error', $message, $errors, $body, $response->body);
        }

        if ($status === 503 && $errorCode === MaintenanceException::ERROR_CODE) {
            return new MaintenanceException($status, $errorCode, $message, $errors, $body, $response->body);
        }

        // 503 z INNYM kodem symbolicznym to nie chwilowa awaria, tylko funkcja
        // niedostępna w tej instancji (np. `calendar.serviceUnavailable` przy
        // instalacji bez modułu kalendarza) - deterministyczne, bez retry.
        if ($status === 503 && $errorCode !== '' && self::isSymbolicCode($errorCode)) {
            return new ServiceUnavailableException($status, $errorCode, $message, $errors, $body, $response->body);
        }

        // 501 `feature.notSupportedByCrmVersion` - trasa istnieje, ale CRM tej
        // instalacji jest za stary na tę funkcję. Deterministyczne, bez retry.
        if ($status === 501) {
            return new FeatureNotSupportedException(
                $status,
                $errorCode !== '' ? $errorCode : FeatureNotSupportedException::ERROR_CODE,
                $message,
                $errors,
                $body,
                $response->body,
            );
        }

        if ($status >= 500) {
            return new ServerException($status, $errorCode !== '' ? $errorCode : 'server.error', $message, $errors, $body, $response->body);
        }

        return new ApiException($status, $errorCode !== '' ? $errorCode : 'api.error', $message, $errors, $body, $response->body);
    }

    /**
     * `Retry-After` v2 podaje w sekundach do resetu okna. Nagłówek bywa też datą HTTP
     * (RFC 7231) - obsługujemy oba, bo koszt jest zerowy, a rozjazd formatu potrafi
     * zamienić backoff w tight loop.
     */
    public static function retryAfter(TransportResponse $response): ?float
    {
        $header = $response->header('Retry-After');
        if ($header === null || trim($header) === '') {
            return null;
        }

        $header = trim($header);
        if (is_numeric($header)) {
            return max(0.0, (float) $header);
        }

        $timestamp = strtotime($header);

        return $timestamp === false ? null : max(0.0, (float) ($timestamp - time()));
    }

    /**
     * Kod symboliczny API (`validation.error`, `auth.invalidCredentials`) kontra
     * ludzkie zdanie z proxy platformy. Kod nie ma spacji i ma kropkę w środku.
     */
    private static function isSymbolicCode(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9_-]+(\.[A-Za-z0-9_-]+)+$/', $value) === 1;
    }

    /** @param list<ApiError> $errors */
    private static function humanMessage(int $status, string $errorCode, array $errors, string $rawBody): string
    {
        if ($errors !== []) {
            return sprintf(
                'Tillio API v2 [%d %s]: %s',
                $status,
                $errorCode !== '' ? $errorCode : 'error',
                implode('; ', array_map(static fn (ApiError $e): string => (string) $e, $errors)),
            );
        }

        if ($errorCode !== '') {
            return sprintf('Tillio API v2 [%d]: %s', $status, $errorCode);
        }

        // Body spoza kontraktu (HTML z reverse proxy, pusta odpowiedź) - do logu leci
        // przycięty oryginał, bo bez niego takie przypadki są nie do zdiagnozowania.
        $snippet = trim(substr($rawBody, 0, 200));

        return sprintf('Tillio API v2 [%d]: odpowiedź spoza kontraktu błędu%s', $status, $snippet !== '' ? ' - ' . $snippet : '');
    }
}
