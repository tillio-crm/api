<?php

declare(strict_types=1);

namespace TillioCrm\Api\Exception;

/**
 * Zapis odrzucony LOKALNIE, zanim żądanie wyszło: `duplicateCheck` wskazuje pole,
 * które nie ma wartości w payloadzie.
 *
 * DLACZEGO to jest wyjątek, a nie "API sobie poradzi": warunek po polu bez
 * wartości API POMIJA z samym ostrzeżeniem - sprawdzenie duplikatu robi się po
 * okrojonym zestawie pól i rekord, który już istnieje, zostaje założony drugi
 * raz. To jest cicha utrata spójności danych, więc SDK
 * zatrzymuje takie żądanie u siebie: uzupełnij wartość pola albo zdejmij je
 * z `duplicateCheck`.
 */
final class IncompleteDuplicateCheckException extends TillioApiException
{
    /**
     * @param list<string> $missingFields pola z duplicateCheck bez wartości w payloadzie
     */
    public function __construct(public readonly array $missingFields)
    {
        parent::__construct(sprintf(
            'duplicateCheck wskazuje pola bez wartości w payloadzie: %s. '
            . 'API pominęłoby te warunki z samym ostrzeżeniem i mogłoby założyć duplikat rekordu - '
            . 'uzupełnij wartości albo zdejmij pola z duplicateCheck.',
            implode(', ', $missingFields),
        ));
    }
}
