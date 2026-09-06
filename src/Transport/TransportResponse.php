<?php

declare(strict_types=1);

namespace TillioCrm\Api\Transport;

/**
 * Surowa odpowiedź transportu. Celowo BEZ dekodowania JSON-a i bez oceny statusu:
 * kontrakt v2 (`_error`, `data`, `pagination`) interpretuje klient, więc atrapa
 * transportu w testach nie musi znać ani grama tego kontraktu.
 */
final readonly class TransportResponse
{
    /**
     * @param int                   $status  kod HTTP
     * @param string                $body    surowe body (JSON, a przy download - bajty pliku)
     * @param array<string, string> $headers nazwy nagłówków znormalizowane do małych liter
     */
    public function __construct(
        public int $status,
        public string $body = '',
        public array $headers = [],
    ) {
    }

    /**
     * Nagłówek po nazwie (bez rozróżniania wielkości liter) albo null.
     */
    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
