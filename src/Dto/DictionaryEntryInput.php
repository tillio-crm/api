<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Wpis słownika do zapisu - wspólny dla prostych słowników; null = nie wysyłaj
 * pola, więc jeden typ obsługuje różne warianty pól:
 *
 *  - statusy/źródła/typy: `name`, `color`, `order`, `active`,
 *  - tagi zadań: `name` (CRM sam doda `#`), `color`, `order`,
 *  - działy: `name`, `order`,
 *  - terminy płatności: `days`, `isDefault`, `order`, `active`.
 */
final readonly class DictionaryEntryInput implements Arrayable
{
    public function __construct(
        public ?string $name = null,
        public ?string $color = null,
        public ?int $order = null,
        public ?bool $active = null,
        public ?int $days = null,
        public ?bool $isDefault = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'name' => $this->name,
            'color' => $this->color,
            'order' => $this->order,
            'active' => $this->active,
            'days' => $this->days,
            'isDefault' => $this->isDefault,
        ]);
    }
}
