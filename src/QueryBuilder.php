<?php

declare(strict_types=1);

namespace TillioCrm\Api;

use TillioCrm\Api\Exception\InvalidFilterException;

/**
 * Normalizacja parametrów zapytania do postaci, której oczekuje v2.
 *
 * DLACZEGO w ogóle: v2 odrzuca nieznany parametr błędem 400 (literówka w filtrze
 * nie może cicho zwrócić całej bazy), więc do query stringa ma trafić dokładnie to,
 * co wołający podał - bez śmieci po `null`-ach z opcjonalnych argumentów. A daty
 * i booleany muszą wyjść w JEDNYM formacie, bo `updatedAfter` z `DateTime::__toString`
 * to gwarantowany 400.
 *
 * STRAŻNIK `customField[...]`: pusty string jest odrzucany wyjątkiem (w kontrakcie
 * v2 znaczy "pole nieustawione" i przypadkowo sklejałby rekordy - patrz
 * `CustomFieldFilter`), a zamierzone filtrowanie po braku wartości wyraża się
 * enumem `CustomFieldFilter::NotSet`.
 *
 * NIE ESCAPUJEMY `%` ANI `_` - decyzja, nie przeoczenie. Filtry częściowe v2
 * escapują znaki wieloznaczne po swojej stronie, zanim opakują wartość w `%…%`.
 * Dołożenie escapowania tutaj dałoby podwójne escapowanie: wartość zawierająca `%`
 * przestałaby się znajdować. Wstrzyknięcia to nie dotyczy - wartości są bindowane.
 */
final class QueryBuilder
{
    /**
     * Normalizuje tablicę parametrów: wycina `null`-e, formatuje daty i booleany,
     * waliduje wartości filtrów `customField[...]`.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     *
     * @throws InvalidFilterException gdy filtr `customField[...]` niesie pusty string
     */
    public static function build(array $params): array
    {
        return self::normalize($params, false);
    }

    /**
     * Data w ISO 8601 z offsetem strefy - format przyjmowany przez wszystkie filtry dat v2.
     */
    public static function date(\DateTimeInterface $date): string
    {
        return $date->format(\DateTimeInterface::ATOM);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private static function normalize(array $params, bool $inCustomField): array
    {
        $query = [];

        foreach ($params as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (is_array($value)) {
                $nested = self::normalize($value, $inCustomField || $key === 'customField');
                if ($nested !== []) {
                    $query[$key] = $nested;
                }
                continue;
            }

            $query[$key] = self::scalar($key, $value, $inCustomField || $key === 'customField');
        }

        return $query;
    }

    private static function scalar(string|int $key, mixed $value, bool $inCustomField): string
    {
        if ($value instanceof CustomFieldFilter) {
            // Jedyna legalna droga do pustej wartości filtra - jawna, nie przypadkowa.
            return '';
        }

        if ($inCustomField && $value === '') {
            throw new InvalidFilterException(sprintf(
                'Pusty string w filtrze customField[%s] - w kontrakcie v2 znaczy "pole nieustawione" '
                . 'i zwróciłby WSZYSTKIE rekordy bez wartości. Jeżeli o to chodzi, użyj CustomFieldFilter::NotSet.',
                $key,
            ));
        }

        if ($value instanceof \DateTimeInterface) {
            return self::date($value);
        }

        if (is_bool($value)) {
            // `1`/`0`, a nie `true`/`false`: v2 przyjmuje oba zapisy na filtrach typu
            // bool, ale `1`/`0` przechodzi też tam, gdzie pole bywało typu int.
            return $value ? '1' : '0';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        throw new InvalidFilterException(sprintf(
            'Wartości filtra %s nie da się zapisać w query stringu (%s).',
            $key,
            get_debug_type($value),
        ));
    }
}
