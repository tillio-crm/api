<?php

declare(strict_types=1);

namespace TillioCrm\Api\Transport;

/**
 * Żądanie w postaci niezależnej od trybu połączenia.
 *
 * Ścieżka jest ZAWSZE względna wobec base URL transportu (np. `v2/contractors`) -
 * transport dokleja przedrostek, więc różnica między trybem bezpośrednim a proxy
 * to zmiana bazy, a nie przepisywanie zasobów. Wyjątek: `absolute` = true, wtedy
 * `path` jest PEŁNYM adresem (podpisane URL-e plików wskazują wprost na storage
 * i nie wolno do nich dokładać ani bazy, ani nagłówków uwierzytelniających).
 */
final readonly class TransportRequest
{
    /**
     * @param string                                $method   metoda HTTP (GET/POST/PUT/DELETE)
     * @param string                                $path     ścieżka względna `v2/...` (albo pełny URL przy absolute)
     * @param array<string, mixed>                  $query    pary do query stringa (także zagnieżdżone, np. `customField[klucz]`)
     * @param array<string, mixed>|list<mixed>|null $body     ciało JSON albo pola formularza przy multipart; null przy GET
     * @param array<string, FileUpload>             $files    pliki multipart: nazwa pola => plik (pusta tablica = zwykły JSON)
     * @param bool                                  $absolute `path` jest pełnym adresem - bez bazy i bez nagłówków auth
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public ?array $body = null,
        public array $files = [],
        public bool $absolute = false,
    ) {
    }
}
