<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Nowa definicja pola niestandardowego (`POST /v2/custom-fields`). Wymagane
 * `entity`, `name`, `type`; założenie wymaga klucza API o uprawnieniach
 * administratora.
 *
 * PUŁAPKI (potwierdzone na żywym API):
 *  - ZAWSZE podawaj `editableBy` z użytkownikiem, na którym działa klucz API -
 *    pole założone bez tego przyjmuje odczyt, ale KAŻDY zapis wartości kończy
 *    się 422, także na rekordach własnych integracji, a definicji nie da się
 *    potem poprawić (trzeba założyć nowe pole),
 *  - wyszukiwanie duplikatów (`duplicateCheck: ["custom:<key>"]`) działa tylko
 *    dla typów INT/STR/VARCHAR - pole typu wyboru odpadnie przy zapisie,
 *  - etykieta (`name`) jest unikalna w encji - zakładanie rób idempotentnie:
 *    najpierw odczytaj listę pól, twórz tylko brakujące,
 *  - klucz pola nadaje CRM (wraca w odpowiedzi) - nie da się go narzucić.
 */
final readonly class CustomFieldInput implements Arrayable
{
    /**
     * @param string|null               $entity     encja pola (np. `contractor`)
     * @param list<string>|null         $options    opcje pól wyboru
     * @param array<string, mixed>|null $config     konfiguracja typu
     * @param list<int>|null            $assignedTo id użytkowników z dostępem
     * @param array{userIds?: list<int>, departmentIds?: list<int>, groupIds?: list<int>}|null $editableBy
     *                                              ACL pola: kto widzi i edytuje wartości
     *                                              (jednolity kształt ACL API; null = wszyscy) -
     *                                              podaj ZAWSZE (patrz opis klasy)
     */
    public function __construct(
        public ?string $entity = null,
        public ?string $name = null,
        public ?string $type = null,
        public ?array $options = null,
        public ?array $config = null,
        public ?bool $required = null,
        public ?array $assignedTo = null,
        public ?array $editableBy = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'entity' => $this->entity,
            'name' => $this->name,
            'type' => $this->type,
            'options' => $this->options,
            'config' => $this->config,
            'required' => $this->required,
            'assignedTo' => $this->assignedTo,
            'editableBy' => $this->editableBy,
        ]);
    }
}
