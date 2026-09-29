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
 * to gwarantowany 400. Od API 2.14.0 daty są sprawdzane ściśle: pusty string
 * albo nieistniejący dzień też daje 400 `query.invalidDate` zamiast cichej
 * zamiany na "teraz".
 *
 * STRAŻNIK `customField[...]`: pusty string jest odrzucany wyjątkiem (w kontrakcie
 * v2 znaczy "pole nieustawione" i przypadkowo sklejałby rekordy - patrz
 * `CustomFieldFilter`), a zamierzone filtrowanie po braku wartości wyraża się
 * enumem `CustomFieldFilter::NotSet`.
 *
 * FILTRY-LISTY (API >= 2.17.0): lista wartości (`['id' => [12, 15, 18]]`) wychodzi
 * jako `id=12,15,18` - tak v2 przyjmuje wiele id w jednym filtrze dokładnym po liczbie
 * całkowitej (`id`, `contractorId`, `leadId`, `ownerUserId` i pozostałe `*Id` na każdej
 * liście), a `include` wiele wartości. Zagnieżdżona tablica `id[0]=12&id[1]=15` to dla
 * v2 błąd 400, więc sklejamy tu, a nie u wołającego. Duplikaty wypadają (API i tak je
 * pomija), a PRZED wysłaniem odrzucamy: więcej niż {@see MAX_LIST_VALUES} wartości
 * (limit API), pustą listę (bez filtra zapytanie zwróciłoby WSZYSTKIE rekordy) i element,
 * który nie jest liczbą całkowitą ani tekstem bez przecinka. Instancja starsza niż
 * 2.17.0 odrzuci listę błędem 400 `query.invalidValue`.
 *
 * NIE ESCAPUJEMY `%` ANI `_` - decyzja, nie przeoczenie. Filtry częściowe v2
 * escapują znaki wieloznaczne po swojej stronie, zanim opakują wartość w `%…%`.
 * Dołożenie escapowania tutaj dałoby podwójne escapowanie: wartość zawierająca `%`
 * przestałaby się znajdować. Wstrzyknięcia to nie dotyczy - wartości są bindowane.
 */
final class QueryBuilder
{
    /**
     * Najwięcej wartości w jednym filtrze-liście (`?id=1,2,3`) - limit API v2.
     * Większy zbiór dziel na paczki: `array_chunk($ids, QueryBuilder::MAX_LIST_VALUES)`.
     */
    public const int MAX_LIST_VALUES = 100;

    /**
     * Normalizuje tablicę parametrów: wycina `null`-e, formatuje daty i booleany,
     * skleja listy wartości przecinkiem, waliduje wartości filtrów `customField[...]`.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     *
     * @throws InvalidFilterException gdy filtr `customField[...]` niesie pusty string albo
     *                                filtr-lista jest pusta, za długa lub ma niedozwolony element
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

            if (is_array($value) && array_is_list($value) && !$inCustomField && $key !== 'customField') {
                $query[$key] = self::csv((string) $key, $value);
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

    /**
     * Filtr-lista sklejony przecinkiem (`id=12,15,18`), bez duplikatów.
     *
     * @param list<mixed> $values
     *
     * @throws InvalidFilterException
     */
    private static function csv(string $key, array $values): string
    {
        if ($values === []) {
            throw new InvalidFilterException(sprintf(
                'Pusta lista w filtrze %s - zapytanie bez tego filtra zwróciłoby WSZYSTKIE rekordy. '
                . 'Przy pustym zbiorze pomiń zapytanie albo sam filtr.',
                $key,
            ));
        }

        $items = [];
        foreach ($values as $item) {
            if (!is_int($item) && !(is_string($item) && $item !== '' && !str_contains($item, ','))) {
                throw new InvalidFilterException(sprintf(
                    'Element listy w filtrze %s musi być liczbą całkowitą albo niepustym tekstem bez przecinka (%s).',
                    $key,
                    get_debug_type($item),
                ));
            }
            $items[(string) $item] = true;
        }

        if (count($items) > self::MAX_LIST_VALUES) {
            throw new InvalidFilterException(sprintf(
                'Filtr %s przyjmuje najwyżej %d wartości na raz (podano %d różnych). '
                . 'Podziel zbiór: array_chunk($ids, QueryBuilder::MAX_LIST_VALUES).',
                $key,
                self::MAX_LIST_VALUES,
                count($items),
            ));
        }

        return implode(',', array_keys($items));
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
