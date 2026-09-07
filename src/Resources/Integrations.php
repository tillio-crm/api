<?php

declare(strict_types=1);

namespace TillioCrm\Api\Resources;

use TillioCrm\Api\Dto\TillioCallsIntegration;

/**
 * Integracje instancji z usługami zewnętrznymi. Na razie Tillio Calls
 * (telefonia): odczyt stanu, rejestracja i usunięcie. Wymaga API >= 2.11.0.
 */
final readonly class Integrations extends Resource
{
    /**
     * `GET /v2/integrations/tillio-calls` - stan integracji Tillio Calls
     * (klucz API nie jest zwracany, tylko flaga `hasApiKey`).
     */
    public function tillioCalls(): TillioCallsIntegration
    {
        return TillioCallsIntegration::fromArray(self::single($this->client->get('v2/integrations/tillio-calls')));
    }

    /**
     * `PUT /v2/integrations/tillio-calls` - rejestracja integracji Tillio Calls
     * (oba pola wymagane); zwraca stan po zapisie.
     */
    public function registerTillioCalls(string $apiUrl, string $apiKey): TillioCallsIntegration
    {
        return TillioCallsIntegration::fromArray(self::single($this->client->put(
            'v2/integrations/tillio-calls',
            ['apiUrl' => $apiUrl, 'apiKey' => $apiKey],
        )));
    }

    /**
     * `DELETE /v2/integrations/tillio-calls` - usunięcie integracji Tillio Calls.
     */
    public function deleteTillioCalls(): void
    {
        $this->client->delete('v2/integrations/tillio-calls');
    }
}
