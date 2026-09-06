<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Szablon wiadomości (odczyt). Lista (`GET /v2/mail/templates`) niesie tylko
 * metadane; pełną treść (`body` HTML z placeholderami `{nazwa}` + adresaci)
 * zwraca `GET /v2/mail/templates/{id}`.
 */
final readonly class MailTemplate
{
    /**
     * @param list<string>         $to  adresaci z szablonu
     * @param list<string>         $cc  kopia
     * @param list<string>         $bcc ukryta kopia
     * @param array<string, mixed> $raw pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $subject,
        public ?int $categoryId,
        public ?string $body,
        public array $to,
        public array $cc,
        public array $bcc,
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
            name: Cast::string($row['name'] ?? null),
            subject: Cast::string($row['subject'] ?? null),
            categoryId: Cast::int($row['categoryId'] ?? null),
            body: Cast::string($row['body'] ?? null),
            to: Cast::stringList($row['to'] ?? null),
            cc: Cast::stringList($row['cc'] ?? null),
            bcc: Cast::stringList($row['bcc'] ?? null),
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
