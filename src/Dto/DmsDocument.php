<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Dokument DMS (odczyt).
 *
 * ADRESOWANIE: rekord niesie DWA identyfikatory, ale
 * w ścieżkach API (`/v2/dms/documents/{publicId}`) działa WYŁĄCZNIE `publicId`
 * (string, nieciągły). `id` to numer porządkowy rekordu w CRM - referencja,
 * której użycie w ścieżce da 404 albo trafi w CUDZY plik. Dokumenty sprzed
 * wprowadzenia `publicId` mogą go nie mieć - są wtedy nieadresowalne
 * (zgłoś, nie podstawiaj `id`).
 *
 * `downloadUrl` to podpisany link magazynu plików ważny ~1 MINUTĘ (pobieranie:
 * {@see \TillioCrm\Api\TillioClient::download()}); po wygaśnięciu odczytaj
 * metadane ponownie (`Dms::getDocument()`). `null` = środowisko bez
 * podpisywania, nie brak pliku.
 */
final readonly class DmsDocument
{
    /**
     * @param array<string, mixed> $raw pełny rekord z API
     */
    public function __construct(
        public ?string $publicId,
        public int $id,
        public ?int $directoryId,
        public ?string $fileName,
        public ?string $mimeType,
        public ?int $sizeBytes,
        public ?int $dmsStatusId,
        public ?int $dmsTypeId,
        public ?string $title,
        public ?string $description,
        public ?int $creatorUserId,
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
            publicId: Cast::nonEmptyString($row['publicId'] ?? null),
            id: Cast::requiredInt($row['id'] ?? null),
            directoryId: Cast::int($row['directoryId'] ?? null),
            fileName: Cast::string($row['fileName'] ?? null),
            mimeType: Cast::string($row['mimeType'] ?? null),
            sizeBytes: Cast::int($row['sizeBytes'] ?? null),
            dmsStatusId: Cast::int($row['dmsStatusId'] ?? null),
            dmsTypeId: Cast::int($row['dmsTypeId'] ?? null),
            title: Cast::string($row['title'] ?? null),
            description: Cast::string($row['description'] ?? null),
            creatorUserId: Cast::int($row['creatorUserId'] ?? null),
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
