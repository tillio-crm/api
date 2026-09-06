<?php

declare(strict_types=1);

namespace TillioCrm\Api\Exception;

/**
 * Filtr odrzucony LOKALNIE, zanim żądanie w ogóle wyszło.
 *
 * Jedyny dziś powód: pusty string w filtrze `customField[...]`. W kontrakcie v2 pusta
 * wartość znaczy "pole NIE ustawione" - zapytanie, które miało znaleźć jeden konkretny
 * rekord, zwróciłoby wszystkie jeszcze niepowiązane i skleiło dwa różne podmioty.
 * Kto naprawdę chce filtrować po braku wartości, mówi to jawnie:
 * `CustomFieldFilter::NotSet` zamiast pustego stringa.
 */
final class InvalidFilterException extends TillioApiException
{
}
