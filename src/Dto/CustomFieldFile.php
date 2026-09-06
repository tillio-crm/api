<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Plik w polu niestandardowym typu FILE. Występuje w dwóch miejscach:
 * jako wartość klucza `customField[<klucz>]` przy odczycie rekordu (pole bez
 * pliku ma tam `null` zamiast tego obiektu) oraz jako odpowiedź końcówek
 * `CustomFields::getFieldFile()` / `uploadFieldFile()`.
 *
 * Wartości pól FILE NIE zapisuje się przez `customField` w PUT rekordu (core
 * je odrzuca) - służą do tego dedykowane metody {@see \TillioCrm\Api\Resources\CustomFields}.
 *
 * `downloadUrl` (podpisany link, TTL ~1 minuta) dokłada TYLKO końcówka pliku;
 * przy odczycie rekordu jest `null` - po plik idzie się wtedy przez
 * `getFieldFile()` (adres tej końcówki niesie `fileUrl`).
 *
 * Dostępne od wersji API 2.6.0.
 */
final readonly class CustomFieldFile
{
    /**
     * @param string|null          $storagePath ścieżka pliku w prywatnej przestrzeni plików instancji
     * @param string|null          $fileUrl     adres końcówki pliku tego pola (stąd bierze się link);
     *                                          null dla encji bez końcówki plikowej
     * @param string|null          $downloadUrl podpisany, tymczasowy link do pobrania (TTL ~1 min);
     *                                          null przy odczycie rekordu i w środowisku bez podpisywania
     * @param array<string, mixed> $raw         pełny rekord z API
     */
    public function __construct(
        public ?string $fileName,
        public ?string $mimeType,
        public ?int $sizeBytes,
        public ?string $storagePath,
        public ?string $fileUrl,
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
            fileName: Cast::string($row['fileName'] ?? null),
            mimeType: Cast::string($row['mimeType'] ?? null),
            sizeBytes: Cast::int($row['sizeBytes'] ?? null),
            storagePath: Cast::string($row['storagePath'] ?? null),
            fileUrl: Cast::string($row['fileUrl'] ?? null),
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
