<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Zapis kategorii - wspólny kształt dla kategorii szablonów (maili, notatek,
 * zadań) i kategorii typów dokumentów. Przy tworzeniu wymagane `name`;
 * przy aktualizacji pola pominięte zostają bez zmian.
 *
 * Dostępne od wersji API 2.4.0.
 */
final readonly class CategoryInput implements Arrayable
{
    /**
     * @param int|null $parentId kategoria nadrzędna; null = poziom główny drzewka
     * @param int|null $priority kolejność na listach (wyższy = wyżej)
     */
    public function __construct(
        public ?string $name = null,
        public ?int $parentId = null,
        public ?int $priority = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'name' => $this->name,
            'parentId' => $this->parentId,
            'priority' => $this->priority,
        ]);
    }
}
