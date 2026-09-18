<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Wpis słownika do zapisu - wspólny dla prostych słowników; null = nie wysyłaj
 * pola, więc jeden typ obsługuje różne warianty pól:
 *
 *  - statusy/źródła/typy: `name`, `color`, `order`, `active`,
 *  - statusy zadań i zgłoszeń: dodatkowo `isFinal` (status kończący),
 *  - typy notatek: dodatkowo `icon` i `acl`,
 *  - priorytety kontrahentów: dodatkowo `icon`,
 *  - typy płatności kontrahenta: dodatkowo `description`,
 *  - typy adresów: `name`, `active`, `isUnique`,
 *  - statusy projektów: `name`, `color`, `order`, `isDefault`, `passTasks`,
 *  - tagi zadań: `name` (CRM sam doda `#`), `color`, `order`,
 *  - działy: `name`, `order`,
 *  - terminy płatności: `days`, `isDefault`, `order`, `active`.
 */
final readonly class DictionaryEntryInput implements Arrayable
{
    /**
     * @param string|null               $icon        nazwa ikony z zestawu CRM
     * @param string|null               $description opis widoczny w panelu (typy płatności kontrahenta)
     * @param bool|null                 $isFinal     status kończący zadanie/zgłoszenie
     * @param bool|null                 $isUnique    jeden adres tego typu na kontrahenta (typy adresów)
     * @param bool|null                 $passTasks   zadania projektu przechodzą dalej przy zmianie statusu
     * @param array<string, mixed>|null $acl         ograniczenie widoczności
     *                                               `{userIds?, departmentIds?, groupIds?}` (typy notatek);
     *                                               puste = bez ograniczeń
     */
    public function __construct(
        public ?string $name = null,
        public ?string $color = null,
        public ?int $order = null,
        public ?bool $active = null,
        public ?int $days = null,
        public ?bool $isDefault = null,
        public ?string $icon = null,
        public ?string $description = null,
        public ?bool $isFinal = null,
        public ?bool $isUnique = null,
        public ?bool $passTasks = null,
        public ?array $acl = null,
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
            'icon' => $this->icon,
            'description' => $this->description,
            'isFinal' => $this->isFinal,
            'isUnique' => $this->isUnique,
            'passTasks' => $this->passTasks,
            'acl' => $this->acl,
        ]);
    }
}
