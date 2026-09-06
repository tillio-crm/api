<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Nowy szablon maila (`POST /v2/mail/templates`). Wymagane `name` i `subject`.
 * Odpowiedź wraca w formacie odczytu szablonu ({@see MailTemplate}).
 *
 * Dostępne od wersji API 2.4.0.
 */
final readonly class MailTemplateInput implements Arrayable
{
    /**
     * @param string|null               $body       treść HTML z placeholderami `{nazwa}`
     * @param int|null                  $categoryId kategoria z `Mail::templateCategories()`;
     *                                              null = poziom główny
     * @param string|null               $alias      unikalny alias szablonu, max 31 znaków
     *                                              (CRM znormalizuje do `!maly_snake`)
     * @param list<string>|null         $to         domyślni adresaci
     * @param list<string>|null         $cc         domyślna kopia
     * @param list<string>|null         $bcc        domyślna ukryta kopia
     * @param array<string, mixed>|null $acl        widoczność szablonu
     *                                              ({userIds, departmentIds, groupIds}); null = wszyscy
     * @param int|null                  $priority   kolejność na liście (wyższy = wyżej)
     * @param bool|null                 $default    true = szablon domyślny (flaga schodzi
     *                                              z poprzedniego domyślnego)
     */
    public function __construct(
        public ?string $name = null,
        public ?string $subject = null,
        public ?string $body = null,
        public ?int $categoryId = null,
        public ?string $alias = null,
        public ?array $to = null,
        public ?array $cc = null,
        public ?array $bcc = null,
        public ?array $acl = null,
        public ?int $priority = null,
        public ?bool $default = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'name' => $this->name,
            'subject' => $this->subject,
            'body' => $this->body,
            'categoryId' => $this->categoryId,
            'alias' => $this->alias,
            'to' => $this->to,
            'cc' => $this->cc,
            'bcc' => $this->bcc,
            'acl' => $this->acl,
            'priority' => $this->priority,
            'default' => $this->default,
        ]);
    }
}
