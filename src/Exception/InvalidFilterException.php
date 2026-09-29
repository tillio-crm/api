<?php

declare(strict_types=1);

namespace TillioCrm\Api\Exception;

/**
 * Filtr odrzucony LOKALNIE, zanim żądanie w ogóle wyszło.
 *
 * Powody:
 *  - pusty string w filtrze `customField[...]`. W kontrakcie v2 pusta wartość znaczy
 *    "pole NIE ustawione" - zapytanie, które miało znaleźć jeden konkretny rekord,
 *    zwróciłoby wszystkie jeszcze niepowiązane i skleiło dwa różne podmioty. Kto naprawdę
 *    chce filtrować po braku wartości, mówi to jawnie: `CustomFieldFilter::NotSet`
 *    zamiast pustego stringa,
 *  - filtr-lista (`['id' => [...]]`, API >= 2.17.0) pusty, dłuższy niż
 *    {@see \TillioCrm\Api\QueryBuilder::MAX_LIST_VALUES} albo z elementem, który nie jest
 *    liczbą całkowitą ani tekstem bez przecinka. Pusta lista to ten sam problem co wyżej:
 *    bez filtra zapytanie zwróciłoby wszystkie rekordy,
 *  - wartość, której nie da się zapisać w query stringu (np. obiekt).
 */
final class InvalidFilterException extends TillioApiException
{
}
