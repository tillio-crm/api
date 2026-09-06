<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

use TillioCrm\Api\ApiResponse;

/**
 * Zawartość jednego poziomu drzewa DMS kontrahenta
 * (`GET /v2/contractors/{contractorId}/dms`).
 *
 * To NIE jest stronicowana lista: przychodzi cały poziom naraz -
 * `directory` (null = poziom główny), podkatalogi i dokumenty.
 */
final readonly class DmsListing
{
    /**
     * @param list<DmsDirectory> $directories podkatalogi tego poziomu
     * @param list<DmsDocument>  $documents   dokumenty tego poziomu
     */
    public function __construct(
        public ?DmsDirectory $directory,
        public array $directories,
        public array $documents,
    ) {
    }

    public static function fromResponse(ApiResponse $response): self
    {
        /** @var array<string, mixed> $data */
        $data = $response->data();
        $directoryRow = Cast::mapOrNull($data['directory'] ?? null);

        return new self(
            directory: $directoryRow === null ? null : DmsDirectory::fromArray($directoryRow),
            directories: array_map(DmsDirectory::fromArray(...), Cast::rows($data['directories'] ?? null)),
            documents: array_map(DmsDocument::fromArray(...), Cast::rows($data['documents'] ?? null)),
        );
    }

    /**
     * Dokument o tej nazwie na tym poziomie (listing nie ma filtrów - szukanie
     * odbywa się lokalnie). To jedyna droga do `publicId` pliku.
     */
    public function findDocument(string $fileName): ?DmsDocument
    {
        foreach ($this->documents as $document) {
            if ($document->fileName === $fileName) {
                return $document;
            }
        }

        return null;
    }

    /**
     * Podkatalog o tej nazwie na tym poziomie - do idempotentnego zakładania
     * struktury (DMS nie ma duplicateCheck, powtórzony create zrobi drugi
     * katalog o tej samej nazwie).
     */
    public function findDirectory(string $name): ?DmsDirectory
    {
        foreach ($this->directories as $directory) {
            if ($directory->name === $name) {
                return $directory;
            }
        }

        return null;
    }
}
