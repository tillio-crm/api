<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Magazyn do zapisu - named arguments, null = nie wysyłaj pola.
 * Przy tworzeniu API wymaga `name` i `symbol`.
 */
final readonly class WarehouseInput implements Arrayable
{
    /**
     * @param string|null $createdAt     data utworzenia przy imporcie historycznym
     * @param int|null    $creatorUserId tylko przy tworzeniu (import historii)
     */
    public function __construct(
        public ?string $name = null,
        public ?string $symbol = null,
        public ?int $status = null,
        public ?string $createdAt = null,
        public ?int $creatorUserId = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'name' => $this->name,
            'symbol' => $this->symbol,
            'status' => $this->status,
            'createdAt' => $this->createdAt,
            'creatorUserId' => $this->creatorUserId,
        ]);
    }
}
