<?php

declare(strict_types=1);

namespace TillioCrm\Api\Transport;

use TillioCrm\Api\Exception\ConfigurationException;

/**
 * Plik do wysyłki multipart - czyste dane, bez dotykania sieci ani dysku.
 *
 * DLACZEGO osobny typ zamiast ścieżki-stringa: końcówki uploadu (DMS, załączniki
 * notatek i zadań) przyjmują WYŁĄCZNIE `multipart/form-data`, a plik bywa i na
 * dysku, i w pamięci (wygenerowany PDF, treść z innego API). Ten obiekt niesie
 * jedno albo drugie, a na CURLFile/CURLStringFile zamienia go dopiero transport.
 */
final readonly class FileUpload
{
    private function __construct(
        public ?string $path,
        public ?string $content,
        public string $fileName,
        public ?string $mimeType,
    ) {
    }

    /**
     * Plik z dysku. Nazwa pliku domyślnie z ostatniego segmentu ścieżki.
     *
     * @throws ConfigurationException gdy ścieżka nie wskazuje istniejącego pliku
     */
    public static function fromPath(string $path, ?string $fileName = null, ?string $mimeType = null): self
    {
        if (!is_file($path)) {
            throw new ConfigurationException(sprintf('Plik do wysyłki nie istnieje: %s', $path));
        }

        return new self($path, null, $fileName ?? basename($path), $mimeType);
    }

    /**
     * Plik z treści w pamięci (np. PDF wygenerowany w locie) - bez zapisywania
     * na dysk tylko po to, żeby curl miał co przeczytać.
     */
    public static function fromString(string $content, string $fileName, ?string $mimeType = null): self
    {
        if ($fileName === '') {
            throw new ConfigurationException('Plik z pamięci wymaga niepustej nazwy - API zapisuje ją w CRM.');
        }

        return new self(null, $content, $fileName, $mimeType);
    }
}
