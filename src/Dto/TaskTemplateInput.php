<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Nowy szablon zadania (`POST /v2/task/templates`). Wymagane `name`.
 *
 * Dostępne od wersji API 2.4.0.
 */
final readonly class TaskTemplateInput implements Arrayable
{
    /**
     * @param int|null                  $categoryId      kategoria z `Tasks::templateCategories()`;
     *                                                   null = poziom główny
     * @param string|null               $alias           skrót do wstawienia szablonu - z prefiksem `!`
     *                                                   lub bez (CRM znormalizuje); unikalny, max 31 znaków
     * @param list<int>|null            $assignedUserIds domyślni wykonawcy (`Users::list()`)
     * @param list<int>|null            $tagIds          domyślne tagi (`Dictionaries::taskTags()`)
     * @param int|null                  $eta             szacowany czas wykonania w minutach
     * @param int|null                  $dueInDays       termin w dniach od utworzenia zadania z szablonu
     * @param int|null                  $taskPriority    priorytet zadania: 0 standard, 1 wysoki, 2 najwyższy
     * @param array<string, mixed>|null $acl             ograniczenie widoczności
     *                                                   ({userIds, departmentIds, groupIds}); null = wszyscy
     * @param int|null                  $priority        kolejność szablonu na liście (wyższy = wyżej)
     * @param bool|null                 $active          false = szablon nieaktywny (widzą tylko
     *                                                   administratorzy szablonów)
     */
    public function __construct(
        public ?int $categoryId = null,
        public ?string $name = null,
        public ?string $alias = null,
        public ?string $title = null,
        public ?string $body = null,
        public ?array $assignedUserIds = null,
        public ?array $tagIds = null,
        public ?int $eta = null,
        public ?int $dueInDays = null,
        public ?int $taskPriority = null,
        public ?array $acl = null,
        public ?int $priority = null,
        public ?bool $active = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'categoryId' => $this->categoryId,
            'name' => $this->name,
            'alias' => $this->alias,
            'title' => $this->title,
            'body' => $this->body,
            'assignedUserIds' => $this->assignedUserIds,
            'tagIds' => $this->tagIds,
            'eta' => $this->eta,
            'dueInDays' => $this->dueInDays,
            'taskPriority' => $this->taskPriority,
            'acl' => $this->acl,
            'priority' => $this->priority,
            'active' => $this->active,
        ]);
    }
}
