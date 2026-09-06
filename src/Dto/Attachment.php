<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Załącznik notatki albo zadania - kontrakt obu jest identyczny
 * (`commentId` występuje tylko przy zadaniach: załącznik dodany w komentarzu).
 *
 * `downloadUrl` to PODPISANY link magazynu plików ważny ~1 MINUTĘ, pobierany zwykłym
 * GET-em bez nagłówków Tillio ({@see \TillioCrm\Api\TillioClient::download()}).
 * Nie buforuj - po wygaśnięciu odczytaj listę załączników ponownie po świeży
 * link. `null` = środowisko bez podpisywania (pliku nie da się pobrać wcale),
 * nie "brak pliku".
 */
final readonly class Attachment
{
    /**
     * @param array<string, mixed> $raw pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $fileName,
        public ?string $mimeType,
        public ?int $sizeBytes,
        public ?int $creatorUserId,
        public ?int $commentId,
        public ?string $createdAt,
        public ?string $downloadUrl,
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
            fileName: Cast::string($row['fileName'] ?? null),
            mimeType: Cast::string($row['mimeType'] ?? null),
            sizeBytes: Cast::int($row['sizeBytes'] ?? null),
            creatorUserId: Cast::int($row['creatorUserId'] ?? null),
            commentId: Cast::int($row['commentId'] ?? null),
            createdAt: Cast::string($row['createdAt'] ?? null),
            downloadUrl: Cast::string($row['downloadUrl'] ?? null),
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
