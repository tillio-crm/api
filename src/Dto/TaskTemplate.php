<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Szablon zadania (odczyt) - nazwa, temat i treść zadania, domyślni wykonawcy
 * i tagi, szacowany czas i termin. `alias` wraca w postaci znormalizowanej
 * przez CRM (`!maly_snake`).
 *
 * Dostępne od wersji API 2.4.0.
 */
final readonly class TaskTemplate
{
    /**
     * @param list<int>                 $assignedUserIds domyślni wykonawcy zadania
     * @param list<int>                 $tagIds          domyślne tagi zadania
     * @param int|null                  $eta             szacowany czas wykonania w minutach
     * @param int|null                  $dueInDays       termin w dniach od utworzenia zadania
     * @param int|null                  $taskPriority    priorytet zadania: 0 standard, 1 wysoki, 2 najwyższy
     * @param array<string, mixed>|null $acl             ograniczenie widoczności szablonu; null = wszyscy
     * @param array<string, mixed>      $raw             pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?int $categoryId,
        public ?string $name,
        public ?string $alias,
        public ?string $title,
        public ?string $body,
        public array $assignedUserIds,
        public array $tagIds,
        public ?int $eta,
        public ?int $dueInDays,
        public ?int $taskPriority,
        public ?array $acl,
        public ?int $priority,
        public ?bool $active,
        public array $raw = [],
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            id: Cast::requiredInt($row['id'] ?? null),
            categoryId: Cast::int($row['categoryId'] ?? null),
            name: Cast::string($row['name'] ?? null),
            alias: Cast::string($row['alias'] ?? null),
            title: Cast::string($row['title'] ?? null),
            body: Cast::string($row['body'] ?? null),
            assignedUserIds: Cast::intList($row['assignedUserIds'] ?? null),
            tagIds: Cast::intList($row['tagIds'] ?? null),
            eta: Cast::int($row['eta'] ?? null),
            dueInDays: Cast::int($row['dueInDays'] ?? null),
            taskPriority: Cast::int($row['taskPriority'] ?? null),
            acl: Cast::mapOrNull($row['acl'] ?? null),
            priority: Cast::int($row['priority'] ?? null),
            active: Cast::bool($row['active'] ?? null),
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
