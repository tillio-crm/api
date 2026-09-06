<?php

declare(strict_types=1);

namespace TillioCrm\Api\Exception;

/**
 * 422 z kodem `body.externalIdAlreadyUsed` - KOLIZJA POWIĄZANIA, nie błąd danych.
 *
 * API pilnuje reguły CRM: jedna wartość `externalId` wskazuje najwyżej jednego
 * kontrahenta. Ten wyjątek znaczy więc dokładnie tyle: obiekt z systemu zewnętrznego
 * jest już powiązany z INNĄ kartoteką w Tillio. To konflikt do rozstrzygnięcia przez
 * człowieka (dwie kartoteki tego samego klienta albo pomyłka w mapowaniu), nie
 * "popraw wartość" - dlatego osobny typ zamiast zwykłej `ValidationException`.
 * Retry nie ma sensu: powtórka da ten sam wynik.
 */
final class ExternalIdAlreadyUsedException extends ValidationException
{
    public const string ERROR_CODE = 'body.externalIdAlreadyUsed';

    /**
     * Id kontrahenta, który już trzyma tę wartość - o ile v2 podało je w komunikacie.
     *
     * API nie zwraca tego jako pola strukturalnego (jest tylko w treści komunikatu),
     * więc wyciągamy je z tekstu i traktujemy jako informację pomocniczą: `null` nie
     * znaczy "nie ma kolizji", tylko "nie wiadomo z kim".
     */
    public function conflictingContractorId(): ?int
    {
        foreach ($this->errors as $error) {
            if ($error->code !== self::ERROR_CODE) {
                continue;
            }

            if (preg_match('/kontrahenta\s+(\d+)/u', $error->message, $match) === 1) {
                return (int) $match[1];
            }
        }

        return null;
    }
}
