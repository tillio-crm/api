<?php

declare(strict_types=1);

namespace TillioCrm\Api;

/**
 * Jawny znacznik specjalnych wartości filtra `customField[...]`.
 *
 * W kontrakcie v2 PUSTA wartość filtra pola niestandardowego znaczy "pole NIE
 * ustawione" - zwraca rekordy jeszcze niepowiązane (przydatne przy pierwszym
 * zasileniu integracji). To samo jest pułapką: pusty string, który przypadkiem
 * trafi do zapytania szukającego duplikatu, zwróci wszystkich niepowiązanych
 * zamiast jednego rekordu - i sklei dwa różne podmioty.
 *
 * Dlatego `QueryBuilder` ODRZUCA pusty string w `customField[...]` wyjątkiem,
 * a kto naprawdę chce filtrować po braku wartości, mówi to tym enumem:
 *
 *     ['customField' => ['erp_id' => CustomFieldFilter::NotSet]]
 *
 * Dla pól liczbowych zero jest wartością: od API 2.14.0 `NotSet` nie łapie
 * rekordów z 0 (wcześniej łapał).
 */
enum CustomFieldFilter
{
    /** Rekordy, które NIE mają ustawionej wartości tego pola. */
    case NotSet;
}
