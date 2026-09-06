<?php

declare(strict_types=1);

namespace TillioCrm\Api\Exception;

/**
 * 422 z kodem `body.fieldUnavailable` - STAN PRZEJŚCIOWY, nie błąd danych.
 *
 * Zapis w v2 idzie przez klasy core CRM, a te wycinają pola spoza modelu. Gdy
 * instancja CRM nie ma jeszcze kolumny, v2 woli jawnie odrzucić zapis, niż PO CICHU
 * zgubić wartość. Dlatego ten przypadek ma własny typ: konsument ma go zaraportować
 * jako "zaktualizuj CRM", a nie jako zepsute dane - retry z tym samym payloadem
 * nie ma sensu.
 */
final class FieldUnavailableException extends ValidationException
{
    public const string ERROR_CODE = 'body.fieldUnavailable';

    /**
     * Nazwy pól, których instancja CRM jeszcze nie zna.
     *
     * @return list<string>
     */
    public function unavailableFields(): array
    {
        $fields = [];
        foreach ($this->errors as $error) {
            if ($error->code === self::ERROR_CODE && $error->field !== '') {
                $fields[] = $error->field;
            }
        }

        return array_values(array_unique($fields));
    }
}
