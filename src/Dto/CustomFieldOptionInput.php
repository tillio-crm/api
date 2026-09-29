<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Opcja pola SELECT/MULTISELECT do zapisu: nazwa i opcjonalny kolor. Przyjmują ją
 * `CustomFieldInput::$options` (zakładanie pola) oraz `CustomFieldUpdateInput::$options`
 * i `customFields()->appendOptions()` (dopisanie do istniejącego pola, API >= 2.17.0).
 * Opcję bez koloru można podać zwykłym stringiem - ten obiekt jest potrzebny dla koloru.
 */
final readonly class CustomFieldOptionInput implements Arrayable
{
    /**
     * @param string      $name  nazwa opcji widoczna w CRM; przy dopisywaniu po niej API
     *                           rozpoznaje opcje już obecne w polu (porównanie dokładne,
     *                           po obcięciu spacji na końcach)
     * @param string|null $color kolor opcji w CRM, np. `#1877f2`; null = domyślny kolor CRM
     */
    public function __construct(
        public string $name,
        public ?string $color = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'name' => $this->name,
            'color' => $this->color,
        ]);
    }

    /**
     * Lista opcji w kształcie żądania: string zostaje stringiem, obiekt staje się
     * `{name, color?}`. Wspólne dla zakładania pola i dopisywania opcji.
     *
     * @param list<string|self> $options
     *
     * @return list<string|array<string, mixed>>
     *
     * @internal
     */
    public static function listPayload(array $options): array
    {
        return array_map(
            static fn (string|self $option): string|array => $option instanceof self ? $option->toArray() : $option,
            $options,
        );
    }
}
