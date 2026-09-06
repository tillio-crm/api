<?php

declare(strict_types=1);

namespace TillioCrm\Api\Resources;

use TillioCrm\Api\Dto\CategoryInput;
use TillioCrm\Api\Dto\DocumentCategory;
use TillioCrm\Api\Dto\DocumentNumeration;
use TillioCrm\Api\Dto\DocumentType;
use TillioCrm\Api\Dto\DocumentTypeInput;
use TillioCrm\Api\Dto\GeneratedDocument;
use TillioCrm\Api\Dto\GeneratedDocumentInput;
use TillioCrm\Api\Dto\WriteResult;
use TillioCrm\Api\Transport\FileUpload;

/**
 * Generator dokumentów (oferty, umowy z szablonów) z publikacją online.
 * PDF pobiera się przez `downloadUrl` z {@see GeneratedDocument} - świeży
 * po `get()`, TTL ~1 minuta.
 *
 * Od API 2.4.0 typy dokumentów zakłada się w pełnym cyklu przez API:
 * `createType()` (szkic) -> `uploadTypeSource()` (plik źródłowy, wykrycie
 * zmiennych `{{...}}`) -> `updateTypeForm()` (formularz mapujący zmienne na
 * pola) -> `activateType()`. Szkic (`draft`) schodzi automatycznie, gdy
 * formularz pokryje wszystkie zmienne pliku źródłowego.
 */
final readonly class GeneratedDocuments extends Resource
{
    /**
     * `GET /v2/document/types` - typy dokumentów z szablonami
     * (typ z `requiresTemplate` wymaga `templateId` przy generowaniu).
     *
     * `$includeInactive = true` (API >= 2.4.0) pokazuje też szkice i typy
     * nieaktywne; parametr nie jest wtedy wysyłany na starszych wartościach,
     * więc domyślne wywołanie działa też na API 2.0.4.
     *
     * @return list<DocumentType>
     */
    public function types(bool $includeInactive = false): array
    {
        return self::mapList(
            $this->client->get('v2/document/types', $includeInactive ? ['includeInactive' => true] : []),
            DocumentType::fromArray(...),
        );
    }

    /**
     * `GET /v2/document/categories` - kategorie typów dokumentów (płaskie
     * drzewo po `parentId`; kategorie `system` są tylko do odczytu).
     * Wymaga API >= 2.4.0.
     *
     * @return list<DocumentCategory>
     */
    public function categories(): array
    {
        return self::mapList($this->client->get('v2/document/categories'), DocumentCategory::fromArray(...));
    }

    /**
     * `POST /v2/document/categories` - nowa kategoria (wymagane `name`).
     * Wymaga API >= 2.4.0.
     *
     * @param CategoryInput|array<string, mixed> $input
     */
    public function createCategory(CategoryInput|array $input): DocumentCategory
    {
        return DocumentCategory::fromArray(self::single(
            $this->client->post('v2/document/categories', self::payload($input)),
        ));
    }

    /**
     * `PUT /v2/document/categories/{id}` - edycja nazwy, rodzica i priorytetu
     * (kategorii `system` nie da się edytować). Wymaga API >= 2.4.0.
     *
     * @param CategoryInput|array<string, mixed> $input
     */
    public function updateCategory(int $id, CategoryInput|array $input): DocumentCategory
    {
        return DocumentCategory::fromArray(self::single(
            $this->client->put('v2/document/categories/' . $id, self::payload($input)),
        ));
    }

    /**
     * `GET /v2/document/numerations` - schematy numeracji dokumentów
     * (do wyboru `numerationId` przy zakładaniu typu). Wymaga API >= 2.4.0.
     *
     * @return list<DocumentNumeration>
     */
    public function numerations(): array
    {
        return self::mapList($this->client->get('v2/document/numerations'), DocumentNumeration::fromArray(...));
    }

    /**
     * `POST /v2/document/types` - nowy typ dokumentu jako SZKIC (wymagane
     * `name`). Dalszy cykl: {@see uploadTypeSource()}, {@see updateTypeForm()},
     * {@see activateType()}. Wymaga API >= 2.4.0.
     *
     * @param DocumentTypeInput|array<string, mixed> $input
     */
    public function createType(DocumentTypeInput|array $input): DocumentType
    {
        return DocumentType::fromArray(self::single(
            $this->client->post('v2/document/types', self::payload($input)),
        ));
    }

    /**
     * `POST /v2/document/types/{id}/source` - upload pliku źródłowego typu
     * (multipart, pole `file`; szablon Google Docs). API wykrywa w pliku
     * zmienne `{{...}}` i zwraca je w wyniku. BEZ RETRY jak każdy upload.
     *
     * Wymaga API >= 2.4.0.
     *
     * @return array<string, mixed> `{id, variables, draft, templateVersion, sourceMime}`
     */
    public function uploadTypeSource(int $typeId, FileUpload $file): array
    {
        return self::single($this->client->postMultipart(
            sprintf('v2/document/types/%d/source', $typeId),
            [],
            ['file' => $file],
        ));
    }

    /**
     * `PUT /v2/document/types/{id}/form` - definicja formularza typu: grupy
     * pól mapujące zmienne pliku źródłowego (wymagane `formFields`; opcjonalny
     * `formHtml` generuje panel CRM - API może pominąć). Gdy formularz pokryje
     * wszystkie zmienne, `draft` schodzi automatycznie. Wymaga API >= 2.4.0.
     *
     * @param array{formFields: list<array<string, mixed>>, formHtml?: string|null} $input
     *
     * @return array<string, mixed> `{id, draft, templateVersion, variables, missingVariables}`
     */
    public function updateTypeForm(int $typeId, array $input): array
    {
        return self::single($this->client->put(sprintf('v2/document/types/%d/form', $typeId), $input));
    }

    /**
     * `POST /v2/document/types/{id}/activate` - aktywacja typu (tylko typ,
     * który wyszedł ze szkicu, generuje dokumenty). Wymaga API >= 2.4.0.
     */
    public function activateType(int $typeId): DocumentType
    {
        return DocumentType::fromArray(self::single(
            $this->client->post(sprintf('v2/document/types/%d/activate', $typeId), []),
        ));
    }

    /**
     * `GET /v2/document/types/{id}/form` - definicja formularza typu dokumentu:
     * pola do wypełnienia w `data` przy generowaniu. Kształt zależy od typu,
     * dlatego surowa mapa.
     *
     * `contractorId` jest WYMAGANE - API wstępnie wypełnia formularz danymi kartoteki.
     * Typ z `requiresTemplate` przyjmuje też `templateId`.
     *
     * @return array<string, mixed>
     */
    public function typeForm(int $typeId, int $contractorId, ?int $templateId = null): array
    {
        return self::single($this->client->get(
            sprintf('v2/document/types/%d/form', $typeId),
            ['contractorId' => $contractorId, 'templateId' => $templateId],
        ));
    }

    /**
     * `GET /v2/contractors/{contractorId}/documents` - dokumenty kontrahenta
     * (lista bez stronicowania).
     *
     * Filtry (komplet wg kontraktu): `salesPipelineId` (dokumenty podpięte pod
     * konkretną szansę sprzedaży).
     *
     * @param array<string, mixed> $filters
     *
     * @return list<GeneratedDocument>
     */
    public function list(int $contractorId, array $filters = []): array
    {
        return self::mapList(
            $this->client->get(sprintf('v2/contractors/%d/documents', $contractorId), $filters),
            GeneratedDocument::fromArray(...),
        );
    }

    /**
     * `GET /v2/documents/{id}` - pojedynczy dokument ze ŚWIEŻYM `downloadUrl`.
     */
    public function get(int $id): GeneratedDocument
    {
        return GeneratedDocument::fromArray(self::single($this->client->get('v2/documents/' . $id)));
    }

    /**
     * `POST /v2/contractors/{contractorId}/documents` - wygenerowanie dokumentu.
     * Wymagane `documentTypeId`; pola `data` wg {@see typeForm()}.
     *
     * @param GeneratedDocumentInput|array<string, mixed> $input
     */
    public function create(int $contractorId, GeneratedDocumentInput|array $input): WriteResult
    {
        return WriteResult::fromResponse(
            $this->client->post(sprintf('v2/contractors/%d/documents', $contractorId), self::payload($input)),
            'documentId',
        );
    }

    /**
     * `POST /v2/documents/{id}/regenerate` - ponowne wygenerowanie (np. po
     * poprawce danych); `data` nadpisuje pola formularza.
     *
     * @param GeneratedDocumentInput|array<string, mixed> $input
     */
    public function regenerate(int $id, GeneratedDocumentInput|array $input = []): WriteResult
    {
        return WriteResult::fromResponse(
            $this->client->post(sprintf('v2/documents/%d/regenerate', $id), self::payload($input)),
            'documentId',
        );
    }
}
