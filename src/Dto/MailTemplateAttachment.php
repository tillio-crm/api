<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Załącznik szablonu maila - CRM dokłada go do każdej wysyłki z tym szablonem.
 * downloadUrl podpisany, TTL ~1 min. Dostępne od wersji API 2.7.0.
 */
final readonly class MailTemplateAttachment
{
    /**
     * @param array<string, mixed> $raw pełny rekord z API
     */
    public function __construct(
        public ?string $id,
        public ?string $fileName,
        public ?string $mimeType,
        public ?int $sizeBytes,
        public ?string $storagePath,
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
            id: Cast::string($row['id'] ?? null),
            fileName: Cast::string($row['fileName'] ?? null),
            mimeType: Cast::string($row['mimeType'] ?? null),
            sizeBytes: Cast::int($row['sizeBytes'] ?? null),
            storagePath: Cast::string($row['storagePath'] ?? null),
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
