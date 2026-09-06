<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Zlecenie wygenerowania dokumentu
 * (`POST /v2/contractors/{contractorId}/documents`). Wymagane `documentTypeId`
 * (typy: `GeneratedDocuments::types()`; typ z `requiresTemplate` wymaga też
 * `templateId`). Pola formularza (`data`) wg `GeneratedDocuments::typeForm()`.
 */
final readonly class GeneratedDocumentInput implements Arrayable
{
    /**
     * @param array<string, mixed>|null $data             wartości pól formularza typu dokumentu
     * @param bool|null                 $fillFromPipeline uzupełnij dane z powiązanej szansy
     * @param bool|null                 $updatePipeline   zapisz wartość dokumentu w szansie
     */
    public function __construct(
        public ?int $documentTypeId = null,
        public ?int $templateId = null,
        public ?array $data = null,
        public ?int $salesPipelineId = null,
        public ?bool $fillFromPipeline = null,
        public ?bool $updatePipeline = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'documentTypeId' => $this->documentTypeId,
            'templateId' => $this->templateId,
            'data' => $this->data,
            'salesPipelineId' => $this->salesPipelineId,
            'fillFromPipeline' => $this->fillFromPipeline,
            'updatePipeline' => $this->updatePipeline,
        ]);
    }
}
