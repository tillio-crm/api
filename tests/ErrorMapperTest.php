<?php

declare(strict_types=1);

namespace TillioCrm\Api\Tests;

use PHPUnit\Framework\TestCase;
use TillioCrm\Api\ErrorMapper;
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

final class ErrorMapperTest extends TestCase
{
    /** @param list<array<string, string>> $errors */
    private static function envelope(int $code, string $message, array $errors = []): string
    {
        $error = ['code' => $code, 'message' => $message];
        if ($errors !== []) {
            $error['errors'] = $errors;
        }

        return (string) json_encode(['_error' => $error]);
    }

    public function test401MapsToAuthentication(): void
    {
        $e = ErrorMapper::map(new TransportResponse(401, self::envelope(401, 'auth.invalidCredentials')));

        self::assertInstanceOf(AuthenticationException::class, $e);
        self::assertSame('auth.invalidCredentials', $e->errorCode);
    }

    public function test403TenantBlockedDistinguishedFromPlain403(): void
    {
        $blocked = ErrorMapper::map(new TransportResponse(403, self::envelope(403, 'tenant.blocked')));
        self::assertInstanceOf(TenantBlockedException::class, $blocked);

        $denied = ErrorMapper::map(new TransportResponse(403, self::envelope(403, 'access.denied')));
        self::assertInstanceOf(AccessDeniedException::class, $denied);
        self::assertNotInstanceOf(TenantBlockedException::class, $denied);
    }

    public function test404MapsToNotFound(): void
    {
        self::assertInstanceOf(
            NotFoundException::class,
            ErrorMapper::map(new TransportResponse(404, self::envelope(404, 'resource.notFound'))),
        );
    }

    public function test409UserLimitOnlyWithSymbolicCode(): void
    {
        $limit = ErrorMapper::map(new TransportResponse(409, self::envelope(409, 'user.limitReached')));
        self::assertInstanceOf(UserLimitReachedException::class, $limit);

        // Inne 409 nie może awansować do "skończyły się licencje".
        $other = ErrorMapper::map(new TransportResponse(409, self::envelope(409, 'other.conflict')));
        self::assertNotInstanceOf(UserLimitReachedException::class, $other);
    }

    public function test429CarriesRetryAfterInSeconds(): void
    {
        $e = ErrorMapper::map(new TransportResponse(
            429,
            self::envelope(429, 'rateLimit.exceeded'),
            ['retry-after' => '17'],
        ));

        self::assertInstanceOf(RateLimitException::class, $e);
        self::assertSame(17.0, $e->retryAfterSeconds);
    }

    public function testRetryAfterAsHttpDate(): void
    {
        $e = ErrorMapper::map(new TransportResponse(
            429,
            self::envelope(429, 'rateLimit.exceeded'),
            ['retry-after' => gmdate('D, d M Y H:i:s', time() + 30) . ' GMT'],
        ));

        self::assertInstanceOf(RateLimitException::class, $e);
        self::assertNotNull($e->retryAfterSeconds);
        self::assertGreaterThan(20.0, $e->retryAfterSeconds);
        self::assertLessThanOrEqual(31.0, $e->retryAfterSeconds);
    }

    public function testValidationCarriesTypedErrors(): void
    {
        $e = ErrorMapper::map(new TransportResponse(422, self::envelope(422, 'validation.error', [
            ['field' => 'alias', 'code' => 'body.required', 'message' => 'Alias jest wymagany.'],
            ['field' => 'taxId', 'code' => 'body.invalidValue', 'message' => 'Zły NIP.'],
        ])));

        self::assertInstanceOf(ValidationException::class, $e);
        self::assertCount(2, $e->errors);
        self::assertTrue($e->hasErrorCode('body.required'));
        self::assertSame('taxId', $e->errorsForField('taxId')[0]->field);
    }

    public function testFieldUnavailableAndExternalIdHaveDedicatedTypes(): void
    {
        $unavailable = ErrorMapper::map(new TransportResponse(422, self::envelope(422, 'validation.error', [
            ['field' => 'externalId', 'code' => 'body.fieldUnavailable', 'message' => 'Instancja CRM nie zna kolumny.'],
        ])));
        self::assertInstanceOf(FieldUnavailableException::class, $unavailable);
        self::assertSame(['externalId'], $unavailable->unavailableFields());

        $taken = ErrorMapper::map(new TransportResponse(422, self::envelope(422, 'validation.error', [
            ['field' => 'externalId', 'code' => 'body.externalIdAlreadyUsed', 'message' => 'Wartość przypisana do kontrahenta 121.'],
        ])));
        self::assertInstanceOf(ExternalIdAlreadyUsedException::class, $taken);
        self::assertSame(121, $taken->conflictingContractorId());
    }

    public function test503MaintenanceDistinguishedFromPlain5xx(): void
    {
        $maintenance = ErrorMapper::map(new TransportResponse(503, self::envelope(503, 'system.maintenance')));
        self::assertInstanceOf(MaintenanceException::class, $maintenance);

        $server = ErrorMapper::map(new TransportResponse(500, self::envelope(500, 'server.error')));
        self::assertInstanceOf(ServerException::class, $server);
        self::assertNotInstanceOf(MaintenanceException::class, $server);
    }

    public function test503WithNonMaintenanceSymbolicCodeIsServiceUnavailable(): void
    {
        // Funkcja niedostępna w instancji (brak modułu) - deterministyczne 503
        // z kodem symbolicznym, ale NIE przerwa serwisowa.
        $e = ErrorMapper::map(new TransportResponse(503, self::envelope(503, 'calendar.serviceUnavailable')));

        // ServiceUnavailableException leży poza gałęzią ServerException/Maintenance -
        // sam typ wystarcza za rozróżnienie.
        self::assertInstanceOf(ServiceUnavailableException::class, $e);
        self::assertSame('calendar.serviceUnavailable', $e->errorCode);
    }

    public function test503MaintenanceStillWinsOverServiceUnavailable(): void
    {
        // `system.maintenance` też jest kodem symbolicznym - przerwa serwisowa
        // musi zachować swój dedykowany typ.
        $e = ErrorMapper::map(new TransportResponse(503, self::envelope(503, 'system.maintenance')));

        self::assertInstanceOf(MaintenanceException::class, $e);
    }

    public function test503WithoutSymbolicCodeStaysServerException(): void
    {
        // Gołe 503 (HTML z reverse proxy, brak koperty) to chwilowa awaria -
        // zwykły ServerException, podlega retry.
        $e = ErrorMapper::map(new TransportResponse(503, '<html>Service Unavailable</html>'));

        self::assertInstanceOf(ServerException::class, $e);
        self::assertNotInstanceOf(MaintenanceException::class, $e);
    }

    public function testOffContractBodyGivesReadableMessage(): void
    {
        $e = ErrorMapper::map(new TransportResponse(502, '<html>Bad Gateway</html>'));

        self::assertInstanceOf(ServerException::class, $e);
        self::assertStringContainsString('spoza kontraktu', $e->getMessage());
        self::assertStringContainsString('Bad Gateway', $e->getMessage());
    }

    // --- Tryb proxy: kto odmówił - platforma czy API ------------------------------

    public function testHumanSentenceMessageViaProxyIsPlatform403(): void
    {
        // Platforma zwraca tę samą kopertę `_error`, ale message jest zdaniem.
        $e = ErrorMapper::map(new TransportResponse(
            403,
            self::envelope(403, "Operacja GET v2/wiki/entries nie jest dozwolona przez adapter usługi 'tillio'."),
        ), viaProxy: true);

        self::assertInstanceOf(ProxyAccessDeniedException::class, $e);
    }

    public function testSymbolic403ViaProxyStillMapsAsApiError(): void
    {
        // 403 z SAMEGO API (tenant.blocked) przechodzi przez proxy nietknięte -
        // musi mapować się jak w trybie bezpośrednim.
        $e = ErrorMapper::map(new TransportResponse(403, self::envelope(403, 'tenant.blocked')), viaProxy: true);

        self::assertInstanceOf(TenantBlockedException::class, $e);
    }

    public function testBinaryRefusalHasDedicatedException(): void
    {
        $e = ErrorMapper::map(new TransportResponse(
            502,
            self::envelope(502, "Zewnętrzne API zwróciło nieobsługiwany typ treści 'application/pdf' (HTTP 200)."),
        ), viaProxy: true);

        self::assertInstanceOf(ProxyBinaryResponseException::class, $e);
        self::assertStringContainsString('downloadUrl', $e->getMessage());
    }

    public function testProxy502UnreachableIsProxyException(): void
    {
        $e = ErrorMapper::map(new TransportResponse(
            502,
            self::envelope(502, 'Zewnętrzne API nieosiągalne: timeout'),
        ), viaProxy: true);

        self::assertInstanceOf(ProxyException::class, $e);
        self::assertNotInstanceOf(ProxyBinaryResponseException::class, $e);
    }

    public function testWithoutProxyFlagHumanSentenceKeepsType(): void
    {
        // W trybie bezpośrednim nie ma proxy - heurystyka nie może się włączać.
        $e = ErrorMapper::map(new TransportResponse(403, self::envelope(403, 'Jakieś zdanie z spacjami.')));

        // AccessDeniedException nie leży w gałęzi ProxyException - sam typ wystarcza.
        self::assertInstanceOf(AccessDeniedException::class, $e);
    }

    public function test501MapsToFeatureNotSupportedWithErrorCode(): void
    {
        // Trasa istnieje, ale CRM instalacji jest za stary na tę funkcję.
        $e = ErrorMapper::map(new TransportResponse(501, self::envelope(501, 'feature.notSupportedByCrmVersion')));

        self::assertInstanceOf(FeatureNotSupportedException::class, $e);
        self::assertSame('feature.notSupportedByCrmVersion', $e->errorCode);
    }

    public function test501WithEmptyBodyGetsDefaultErrorCode(): void
    {
        // Gołe 501 bez koperty - typ ten sam, kod z domyślnej stałej.
        $e = ErrorMapper::map(new TransportResponse(501, ''));

        self::assertInstanceOf(FeatureNotSupportedException::class, $e);
        self::assertSame(FeatureNotSupportedException::ERROR_CODE, $e->errorCode);
    }

    public function testUnknownStatusFallsBackToApiException(): void
    {
        $e = ErrorMapper::map(new TransportResponse(418, self::envelope(418, 'teapot.brew')));

        self::assertSame(ApiException::class, $e::class);
    }
}
