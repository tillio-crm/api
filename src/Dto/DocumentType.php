<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Typ dokumentu generatora (odczyt) - z listą szablonów HTML
 * (tylko typy z `requiresTemplate = true`).
 *
 * Pola cyklu życia typu zakładanego przez API (`active`, `draft`,
 * `numerationId`, `store`, `publishDays`, `shareEmails`, `sourceUploaded`,
 * `sourceMime`, `variables`, `formFields`, `templateVersion`) są dostępne
 * od wersji API 2.4.0 - na starszych instancjach zostają nullami/pustkami.
 * Cykl: szkic (`draft = true`) trzyma się, dopóki formularz nie pokryje
 * wszystkich zmiennych wykrytych w pliku źródłowym.
 */
final readonly class DocumentType
{
    /**
     * @param list<array<string, mixed>> $templates    szablony `{id, name, description}`
     * @param int|null                   $numerationId schemat numeracji dokumentów
     * @param int|null                   $publishDays  dni publikacji online; 0 = bez publikacji
     * @param list<string>               $shareEmails  adresy z dostępem do pliku źródłowego w Google Docs
     * @param string|null                $sourceMime   typ pliku w Google Docs (document/presentation)
     * @param list<string>               $variables    zmienne `{{...}}` wykryte w pliku źródłowym
     * @param list<array<string, mixed>> $formFields   definicja formularza - grupy pól
     * @param array<string, mixed>       $raw          pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $description,
        public ?int $categoryId,
        public ?string $categoryName,
        public ?bool $requiresTemplate,
        public array $templates,
        public ?int $numerationId = null,
        public ?int $mailTemplateId = null,
        public ?string $fileType = null,
        public ?bool $store = null,
        public ?int $publishDays = null,
        public ?bool $active = null,
        public ?bool $draft = null,
        public array $shareEmails = [],
        public ?bool $sourceUploaded = null,
        public ?string $sourceMime = null,
        public array $variables = [],
        public array $formFields = [],
        public ?int $templateVersion = null,
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
            description: Cast::string($row['description'] ?? null),
            categoryId: Cast::int($row['categoryId'] ?? null),
            categoryName: Cast::string($row['categoryName'] ?? null),
            requiresTemplate: Cast::bool($row['requiresTemplate'] ?? null),
            templates: Cast::rows($row['templates'] ?? null),
            numerationId: Cast::int($row['numerationId'] ?? null),
            mailTemplateId: Cast::int($row['mailTemplateId'] ?? null),
            fileType: Cast::string($row['fileType'] ?? null),
            store: Cast::bool($row['store'] ?? null),
            publishDays: Cast::int($row['publishDays'] ?? null),
            active: Cast::bool($row['active'] ?? null),
            draft: Cast::bool($row['draft'] ?? null),
            shareEmails: Cast::stringList($row['shareEmails'] ?? null),
            sourceUploaded: Cast::bool($row['sourceUploaded'] ?? null),
            sourceMime: Cast::string($row['sourceMime'] ?? null),
            variables: Cast::stringList($row['variables'] ?? null),
            formFields: Cast::rows($row['formFields'] ?? null),
            templateVersion: Cast::int($row['templateVersion'] ?? null),
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
