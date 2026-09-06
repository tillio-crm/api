<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Szablon notatki kontrahenta (odczyt) - gotowa treść (tytuł + HTML),
 * z której użytkownik CRM tworzy notatkę jednym kliknięciem.
 *
 * `alias` wraca w postaci ZAPISANEJ przez CRM: prefiks `!`, małe litery,
 * spacje zamienione na `_` (tak wpisuje się go w edytorze notatki) - nie
 * porównuj go z wartością wysłaną.
 *
 * Dostępne od wersji API 2.4.0.
 */
final readonly class NoteTemplate
{
    /**
     * @param array<string, mixed>|null $acl ograniczenie widoczności szablonu
     *                                       ({userIds, departmentIds, groupIds}); null = wszyscy
     * @param array<string, mixed>      $raw pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?int $categoryId,
        public ?int $noteTypeId,
        public ?string $name,
        public ?string $alias,
        public ?string $title,
        public ?string $body,
        public ?array $acl,
        public ?int $priority,
        public ?bool $active,
        public ?string $updatedAt,
        public ?int $updatedByUserId,
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
            noteTypeId: Cast::int($row['noteTypeId'] ?? null),
            name: Cast::string($row['name'] ?? null),
            alias: Cast::string($row['alias'] ?? null),
            title: Cast::string($row['title'] ?? null),
            body: Cast::string($row['body'] ?? null),
            acl: Cast::mapOrNull($row['acl'] ?? null),
            priority: Cast::int($row['priority'] ?? null),
            active: Cast::bool($row['active'] ?? null),
            updatedAt: Cast::string($row['updatedAt'] ?? null),
            updatedByUserId: Cast::int($row['updatedByUserId'] ?? null),
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
