<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Nowy szablon notatki (`POST /v2/note/templates`). Wymagane `noteTypeId`,
 * `name` i `title`.
 *
 * Dostępne od wersji API 2.4.0.
 */
final readonly class NoteTemplateInput implements Arrayable
{
    /**
     * @param int|null                  $categoryId kategoria z `Notes::templateCategories()`;
     *                                              null = poziom główny
     * @param int|null                  $noteTypeId typ notatki (`Dictionaries::noteTypes()`)
     * @param string|null               $alias      skrót do wywołania w edytorze (CRM
     *                                              znormalizuje go do `!malelitery_z_podkresleniami`)
     * @param string|null               $body       treść notatki - surowy HTML (WYSIWYG w CRM)
     * @param array<string, mixed>|null $acl        ograniczenie widoczności
     *                                              ({userIds, departmentIds, groupIds}); null = wszyscy
     * @param int|null                  $priority   kolejność na liście szablonów (wyższy = wyżej)
     */
    public function __construct(
        public ?int $categoryId = null,
        public ?int $noteTypeId = null,
        public ?string $name = null,
        public ?string $alias = null,
        public ?string $title = null,
        public ?string $body = null,
        public ?array $acl = null,
        public ?int $priority = null,
        public ?bool $active = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'categoryId' => $this->categoryId,
            'noteTypeId' => $this->noteTypeId,
            'name' => $this->name,
            'alias' => $this->alias,
            'title' => $this->title,
            'body' => $this->body,
            'acl' => $this->acl,
            'priority' => $this->priority,
            'active' => $this->active,
        ]);
    }
}
