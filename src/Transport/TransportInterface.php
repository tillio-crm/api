<?php

declare(strict_types=1);

namespace TillioCrm\Api\Transport;

use TillioCrm\Api\Exception\TransportException;

/**
 * JEDYNY szew SDK na świat zewnętrzny.
 *
 * DLACZEGO tak wąsko: różnica między trybem bezpośrednim a proxy platformy żyje
 * WYŁĄCZNIE w implementacji transportu (base URL, uwierzytelnienie, `_connector`).
 * Przez ten interfejs przechodzi tylko to, co wspólne dla obu trybów: metoda,
 * ścieżka względna, query, body i pliki. Dzięki temu ani jedna klasa zasobu nie
 * wie, którym trybem działa klient - a testy podmieniają transport na atrapę.
 */
interface TransportInterface
{
    /**
     * Wysyła żądanie i zwraca surową odpowiedź - BEZ interpretacji statusu i body.
     * Mapowanie błędów robi klient, żeby kontrakt v2 miał jedno miejsce; transport,
     * który zamieniałby 4xx na wyjątek po drodze, odebrałby klientowi możliwość
     * odróżnienia 201 od 200-z-duplikatem czy 422 od 401.
     *
     * @throws TransportException gdy nie udało się w ogóle dowieźć żądania
     *                            (DNS, timeout, zerwane połączenie)
     */
    public function send(TransportRequest $request): TransportResponse;
}
