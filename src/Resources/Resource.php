<?php

declare(strict_types=1);

namespace TillioCrm\Api\Resources;

use TillioCrm\Api\ApiResponse;
use TillioCrm\Api\Dto\Arrayable;
use TillioCrm\Api\Exception\IncompleteDuplicateCheckException;
use TillioCrm\Api\Page;
use TillioCrm\Api\TillioClient;

/**
 * Baza klas zasobów. Zasoby znają ŚCIEŻKI i KONTRAKTY v2, ale nie transport -
 * i nie wiedzą, którym trybem (bezpośredni/proxy) działa klient. Ani jednego
 * warunku sprawdzającego tryb: różnica żyje w warstwie transport/konfiguracja.
 *
 * WYJĄTKI: każda metoda zasobu może rzucić
 * {@see \TillioCrm\Api\Exception\ApiException} (i jego podklasy - status błędu
 * z API) oraz {@see \TillioCrm\Api\Exception\TransportException} (żądanie nie
 * dojechało). W docblokach metod wymieniamy tylko wyjątki PONAD ten komplet:
 * rzucane przez SDK lokalnie albo specyficzne dla trasy.
 */
abstract readonly class Resource
{
    public function __construct(protected TillioClient $client)
    {
    }

    /**
     * Strona listy z rekordami zmapowanymi na DTO.
     *
     * @template T
     *
     * @param callable(array<string, mixed>): T $map
     *
     * @return Page<T>
     */
    protected static function mapPage(ApiResponse $response, callable $map): Page
    {
        $raw = Page::fromResponse($response);
        $rows = [];
        foreach ($raw->rows as $row) {
            $rows[] = $map($row);
        }

        return new Page($rows, $raw->page, $raw->limit, $raw->total, $raw->pages);
    }

    /**
     * Lista rekordów zmapowanych na DTO z odpowiedzi BEZ stronicowania
     * (koperta `{data: [...]}` - słowniki, listingi zamknięte).
     *
     * @template T
     *
     * @param callable(array<string, mixed>): T $map
     *
     * @return list<T>
     */
    protected static function mapList(ApiResponse $response, callable $map): array
    {
        $rows = [];
        foreach ($response->data() as $row) {
            if (is_array($row)) {
                /** @var array<string, mixed> $row */
                $rows[] = $map($row);
            }
        }

        return $rows;
    }

    /**
     * Pełny przebieg listy stronicowanej z mapowaniem na DTO - generator,
     * rekordy po jednym (materializacja dziesiątek tysięcy rekordów w pamięci
     * to proszenie się o OOM). Stronicowanie i stabilny porządek:
     * {@see TillioClient::iterateAll()}.
     *
     * @template T
     *
     * @param callable(array<string, mixed>): T $map
     * @param array<string, mixed>              $filters
     * @param string|null                       $stableSort pole sortowania wymuszane na czas
     *                                                      przebiegu; null = nie narzucaj
     *
     * @return \Generator<int, T>
     */
    protected function iterateMapped(string $path, callable $map, array $filters = [], int $pageSize = 1000, ?string $stableSort = 'id'): \Generator
    {
        foreach ($this->client->iterateAll($path, $filters, $pageSize, $stableSort) as $row) {
            yield $map($row);
        }
    }

    /**
     * Pojedynczy rekord z koperty `data`.
     *
     * @return array<string, mixed>
     */
    protected static function single(ApiResponse $response): array
    {
        /** @var array<string, mixed> $data */
        $data = $response->data();

        return $data;
    }

    /**
     * Normalizacja `Input|array` do payloadu żądania - tablica to świadomy
     * fallback (jedyna droga wysłania jawnego nulla i pól, których DTO
     * jeszcze nie zna).
     *
     * @param Arrayable|array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    protected static function payload(Arrayable|array $input): array
    {
        return $input instanceof Arrayable ? $input->toArray() : $input;
    }

    /**
     * STRAŻNIK kompletności duplicateCheck: każde wskazane w nim pole musi mieć
     * wartość w payloadzie TEGO żądania. Warunek po polu bez wartości API pomija
     * z samym ostrzeżeniem - sprawdzenie robi się po okrojonym zestawie i rekord,
     * który już istnieje, zostaje założony drugi raz (cichy duplikat). Dlatego
     * takie żądanie w ogóle nie wychodzi z SDK.
     *
     * Pola `custom:<klucz>` sprawdzane są w `customField`. `allowDuplicates`
     * wyłącza całe wyszukiwanie duplikatu, więc i ten strażnik.
     *
     * @param array<string, mixed> $payload payload scalony z opcjami zapisu
     *
     * @throws IncompleteDuplicateCheckException
     */
    protected static function assertDuplicateCheckUsable(array $payload): void
    {
        $check = $payload['duplicateCheck'] ?? null;
        if (!is_array($check) || $check === [] || (bool) ($payload['allowDuplicates'] ?? false)) {
            return;
        }

        $missing = [];
        foreach ($check as $field) {
            if (!is_string($field) || $field === '') {
                continue;
            }

            if (str_starts_with($field, 'custom:')) {
                $customField = $payload['customField'] ?? null;
                $value = is_array($customField) ? ($customField[substr($field, 7)] ?? null) : null;
            } else {
                $value = $payload[$field] ?? null;
            }

            if ($value === null || $value === '') {
                $missing[] = $field;
            }
        }

        if ($missing !== []) {
            throw new IncompleteDuplicateCheckException($missing);
        }
    }
}
