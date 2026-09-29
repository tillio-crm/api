<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Pole niestandardowe po zmianie (`PUT /v2/{entity}/custom-fields/{key}`) - wynik
 * `customFields()->appendOptions()`. Z `customFields()->update()` zbudujesz go z danych
 * odpowiedzi: `CustomFieldUpdateResult::fromArray($result->data)`.
 *
 * `null` znaczy "nie dotyczy tego pola", nie "puste": `assignedTo` przychodzi tylko dla
 * encji z podtypami (note, ticket, service, lead, pipeline), `options` tylko dla pól
 * SELECT i MULTISELECT (od API 2.17.0).
 */
final readonly class CustomFieldUpdateResult
{
    /**
     * @param string                       $key        klucz pola
     * @param list<int>|null               $assignedTo przypisania do podtypów po zapisie
     * @param list<CustomFieldOption>|null $options    KOMPLET opcji po zapisie (istniejące i dopisane),
     *                                                 w kolejności z CRM
     * @param array<string, mixed>         $raw        pełne `data` odpowiedzi
     */
    public function __construct(
        public string $key,
        public ?array $assignedTo,
        public ?array $options,
        public array $raw = [],
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            key: Cast::requiredString($row['key'] ?? null),
            assignedTo: is_array($row['assignedTo'] ?? null) ? Cast::intList($row['assignedTo']) : null,
            options: is_array($row['options'] ?? null)
                ? array_map(CustomFieldOption::fromArray(...), Cast::rows($row['options']))
                : null,
            raw: $row,
        );
    }

    /**
     * Id opcji o podanej nazwie (do zapisu i filtra `customField`) albo null, gdy pole
     * takiej opcji nie ma. Nazwę porównuje dokładnie, po obcięciu spacji na końcach -
     * tak samo, jak API rozpoznaje opcje już obecne przy dopisywaniu.
     */
    public function optionValue(string $name): ?int
    {
        $name = trim($name);
        foreach ($this->options ?? [] as $option) {
            if ($option->name === $name) {
                return $option->value;
            }
        }

        return null;
    }

    /**
     * Pełne, surowe `data` odpowiedzi.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->raw;
    }
}
