<?php

declare(strict_types=1);

namespace TillioCrm\Api\Tests;

use PHPUnit\Framework\TestCase;
use TillioCrm\Api\RouteMap;

/**
 * GWARANCJA POKRYCIA 100% TRAS.
 *
 * `RouteMap::MAP` jest kanonicznym obrazem specyfikacji OpenAPI API v2
 * (230 tras, kontrakt 2.12.1). Sama specyfikacja nie leży w repo - każda instancja serwuje ją
 * publicznie pod `GET /v2/openapi.json`, więc pełne porównanie 1:1 robi się
 * na żądanie: pobierz zrzut i wskaż go zmienną środowiskową
 * `TILLIO_OPENAPI_FILE` - test porówna mapę z plikiem. Bez tej zmiennej
 * pilnujemy tego, co da się sprawdzić bez kontraktu: że każda zmapowana trasa
 * ma istniejącą, publiczną metodę SDK i że liczba tras się nie rozjechała.
 */
final class RouteCoverageTest extends TestCase
{
    /**
     * Trasy zaimplementowane WYPRZEDZAJĄCO wobec wdrożonego kontraktu - są
     * w kodzie API, ale dopiero od wersji, której starsza instancja jeszcze
     * nie serwuje. W porównaniu 1:1 ze specyfikacją takiej instancji wyszłyby
     * jako "nadmiarowe" - i słusznie: to funkcje, których ta instancja nie ma.
     * Dlatego wykluczamy je z tego jednego sprawdzenia (nie z reszty testów).
     *
     * Puste, gdy cała mapa mieści się w najnowszym wdrożonym kontrakcie.
     *
     * @var list<string>
     */
    private const AHEAD_OF_CONTRACT = [];

    public function testEveryRouteMapsToExistingPublicSdkMethod(): void
    {
        $broken = [];
        foreach (RouteMap::MAP as $route => $target) {
            [$class, $method] = explode('::', $target);
            if (!class_exists($class) || !method_exists($class, $method)) {
                $broken[] = $route . ' => ' . $target;
                continue;
            }
            if (!(new \ReflectionMethod($class, $method))->isPublic()) {
                $broken[] = $route . ' => ' . $target . ' (metoda niepubliczna)';
            }
        }

        self::assertSame([], $broken, 'Trasa zmapowana na nieistniejącą albo niepubliczną metodę SDK - pokrycie 100% przestało być prawdą.');
    }

    public function testRouteCountMatchesContract(): void
    {
        // Twarda liczba ze specyfikacji (kontrakt 2.12.1). Aktualizacja
        // kontraktu = świadoma zmiana tej liczby RAZEM z wpisem i implementacją.
        self::assertCount(230, RouteMap::MAP);
    }

    public function testEveryRouteKeyIsWellFormed(): void
    {
        foreach (array_keys(RouteMap::MAP) as $route) {
            self::assertMatchesRegularExpression(
                '#^(GET|POST|PUT|DELETE) /v2/[a-z0-9/{}._-]+$#i',
                $route,
                sprintf('Klucz mapy "%s" nie ma formatu "METODA /v2/ścieżka".', $route),
            );
        }
    }

    public function testEveryMapEntryPointsToClassAndMethod(): void
    {
        foreach (RouteMap::MAP as $route => $target) {
            self::assertMatchesRegularExpression(
                '/^TillioCrm\\\\Api\\\\[A-Za-z\\\\]+::[a-zA-Z]+$/',
                $target,
                sprintf('Wpis mapy dla "%s" nie ma formatu Klasa::metoda.', $route),
            );
        }
    }

    public function testMapMatchesOpenApiSpecWhenProvided(): void
    {
        $specPath = getenv('TILLIO_OPENAPI_FILE');
        if ($specPath === false || $specPath === '') {
            self::markTestSkipped('Pełne porównanie 1:1: pobierz GET /v2/openapi.json i ustaw TILLIO_OPENAPI_FILE.');
        }

        self::assertFileExists($specPath);
        $spec = json_decode((string) file_get_contents($specPath), true);
        self::assertIsArray($spec);
        self::assertIsArray($spec['paths'] ?? null);

        $routes = [];
        foreach ($spec['paths'] as $path => $operations) {
            self::assertIsString($path);
            self::assertIsArray($operations);
            foreach (array_keys($operations) as $method) {
                if (!in_array($method, ['get', 'post', 'put', 'delete', 'patch'], true)) {
                    continue; // parameters/summary na poziomie ścieżki to nie operacje
                }
                $routes[strtoupper((string) $method) . ' ' . $path] = true;
            }
        }

        self::assertSame(
            [],
            array_keys(array_diff_key($routes, RouteMap::MAP)),
            'Trasy w openapi BEZ wpisu w RouteMap - API urosło; uzupełnij mapę (i implementację).',
        );

        // Trasy wyprzedzające wdrożenie (AHEAD_OF_CONTRACT) wykluczamy: jeśli
        // instancja już je serwuje, wypadną też z $routes i różnica będzie pusta;
        // jeśli jeszcze nie (starsza wersja), nie liczymy ich jako błąd.
        $mapWithoutAhead = array_diff_key(RouteMap::MAP, array_flip(self::AHEAD_OF_CONTRACT));
        self::assertSame(
            [],
            array_keys(array_diff_key($mapWithoutAhead, $routes)),
            'Wpisy w RouteMap, których NIE MA w openapi - literówka w ścieżce albo trasa zniknęła z API.',
        );
    }
}
