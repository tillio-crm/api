<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Definicja pola niestandardowego (odczyt).
 *
 * `key` GENERUJE CRM z etykiety (nie da się go narzucić) - odczytaj go
 * z odpowiedzi po założeniu pola i zapisz po swojej stronie; nim adresuje się
 * wartości (`customField[<key>]`). Definicje adresuje się kluczem `key`, nie `id`.
 */
final readonly class CustomFieldDefinition
{
    /**
     * @param array<string, mixed>       $config     konfiguracja typu (np. opcje select)
     * @param list<array<string, mixed>> $options    opcje pól wyboru
     * @param list<int>                  $assignedTo id użytkowników z dostępem do pola
     * @param array<string, mixed>       $raw        pełny rekord z API
     */
    public function __construct(
        public ?string $key,
        public ?string $name,
        public ?string $displayName,
        public ?string $type,
        public ?int $fieldTypeId,
        public ?bool $required,
        public array $config,
        public array $options,
        public array $assignedTo,
        public array $raw = [],
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            key: Cast::string($row['key'] ?? null),
            name: Cast::string($row['name'] ?? null),
            displayName: Cast::string($row['displayName'] ?? null),
            type: Cast::string($row['type'] ?? null),
            fieldTypeId: Cast::int($row['fieldTypeId'] ?? null),
            required: Cast::bool($row['required'] ?? null),
            config: Cast::map($row['config'] ?? null),
            options: Cast::rows($row['options'] ?? null),
            assignedTo: Cast::intList($row['assignedTo'] ?? null),
            raw: $row,
        );
    }

    /**
     * Pełny, surowy rekord z API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->raw;
    }
}
