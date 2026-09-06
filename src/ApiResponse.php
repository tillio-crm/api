<?php

declare(strict_types=1);

namespace TillioCrm\Api;

/**
 * Udana odpowiedź v2 po zdekodowaniu. Kontrakt: `{data, pagination}` przy listach,
 * `{data, info}` przy zapisach; endpointy systemowe (health, whoami) odpowiadają
 * PŁASKO, bez koperty - wtedy czyta się `->body`.
 *
 * Status trzymamy, bo przy zapisach NIESIE ZNACZENIE: 201 = utworzono,
 * 200 = znaleziono duplikat i zwrócono istniejący rekord.
 */
final readonly class ApiResponse
{
    /**
     * @param int                   $status  kod HTTP (2xx/3xx - błędy stają się wyjątkami wcześniej)
     * @param array<string, mixed>  $body    pełne zdekodowane body
     * @param array<string, string> $headers nagłówki odpowiedzi, nazwy małymi literami
     */
    public function __construct(
        public int $status,
        public array $body,
        public array $headers = [],
    ) {
    }

    /**
     * Zawartość klucza `data` - rekord albo lista rekordów.
     *
     * @return array<string, mixed>|list<mixed>
     */
    public function data(): array
    {
        $data = $this->body['data'] ?? [];

        return is_array($data) ? $data : [];
    }

    /**
     * Zawartość klucza `info` - metadane zapisu (`created`, `ids`, `duplicate`, `warnings`).
     *
     * @return array<string, mixed>
     */
    public function info(): array
    {
        $info = $this->body['info'] ?? [];

        return is_array($info) ? $info : [];
    }

    /**
     * Zawartość klucza `pagination` (`{page, limit, total, pages}`).
     *
     * @return array<string, mixed>
     */
    public function pagination(): array
    {
        $pagination = $this->body['pagination'] ?? [];

        return is_array($pagination) ? $pagination : [];
    }

    /**
     * Nagłówek odpowiedzi po nazwie (bez rozróżniania wielkości liter) albo null.
     */
    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
