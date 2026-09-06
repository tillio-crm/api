<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Zmiana stanu magazynowego (`PUT /v2/warehouses/{warehouseId}/stocks/{productId}`).
 *
 * DOKŁADNIE JEDNO z dwóch: `quantity` (ustaw stan absolutnie) ALBO `adjustBy`
 * (skoryguj o wartość, także ujemną). Ilości jako stringi dziesiętne.
 */
final readonly class StockInput implements Arrayable
{
    /**
     * @param string|null $quantity docelowy stan absolutny (np. "12.500")
     * @param string|null $adjustBy korekta względna (np. "-2", "0.5")
     */
    public function __construct(
        public ?string $quantity = null,
        public ?string $adjustBy = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'quantity' => $this->quantity,
            'adjustBy' => $this->adjustBy,
        ]);
    }
}
