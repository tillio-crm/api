<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Nowy typ dokumentu generatora (`POST /v2/document/types`). Wymagane `name`.
 *
 * Typ powstaje jako SZKIC (`draft = true`) - pełny cykl życia opisuje
 * {@see \TillioCrm\Api\Resources\GeneratedDocuments}: po założeniu wgraj plik
 * źródłowy (`uploadTypeSource()`), zdefiniuj formularz (`updateTypeForm()`)
 * i aktywuj (`activateType()`).
 *
 * Dostępne od wersji API 2.4.0.
 */
final readonly class DocumentTypeInput implements Arrayable
{
    /**
     * @param int|null          $categoryId     kategoria z `GeneratedDocuments::categories()`
     * @param int|null          $numerationId   schemat numeracji (`GeneratedDocuments::numerations()`)
     * @param int|null          $mailTemplateId szablon maila do wysyłki dokumentu
     * @param bool|null         $store          czy wygenerowane dokumenty zapisują się w CRM
     * @param int|null          $publishDays    dni publikacji online; 0 = bez publikacji
     *                                          (przy store=false zawsze 0 - zachowanie CRM)
     * @param list<string>|null $shareEmails    adresy, którym plik źródłowy jest udostępniany
     *                                          w Google Docs
     */
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?int $categoryId = null,
        public ?int $numerationId = null,
        public ?int $mailTemplateId = null,
        public ?bool $store = null,
        public ?int $publishDays = null,
        public ?array $shareEmails = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'name' => $this->name,
            'description' => $this->description,
            'categoryId' => $this->categoryId,
            'numerationId' => $this->numerationId,
            'mailTemplateId' => $this->mailTemplateId,
            'store' => $this->store,
            'publishDays' => $this->publishDays,
            'shareEmails' => $this->shareEmails,
        ]);
    }
}
