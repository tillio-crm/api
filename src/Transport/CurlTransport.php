<?php

declare(strict_types=1);

namespace TillioCrm\Api\Transport;

use TillioCrm\Api\Config;
use TillioCrm\Api\Exception\TransportException;

/**
 * Transport na cURL - jedyne miejsce w SDK, które w ogóle wie o sieci.
 *
 * Ta sama klasa obsługuje oba tryby: wprost do API v2 i przez proxy platformy -
 * różnicę niesie `Config` (base URL + nagłówki + `_connector`). Dlatego przejście
 * między trybami nie wymaga innej implementacji transportu ani zmiany w zasobach.
 *
 * `prepare()` jest publiczne i CZYSTE (bez sieci) - testy sprawdzają na nim, że
 * oba tryby budują właściwe URL-e i nagłówki, nie stawiając serwera.
 */
final class CurlTransport implements TransportInterface
{
    /**
     * Zamek protokołów - liczony raz na proces (odpowiedź `curl_version()` nie
     * zmienia się w trakcie jego życia).
     *
     * @var array<int, int|string>|null
     */
    private static ?array $protocolOptions = null;

    public function __construct(private readonly Config $config)
    {
    }

    public function send(TransportRequest $request): TransportResponse
    {
        $prepared = $this->prepare($request);

        $options = [
            CURLOPT_URL => $prepared['url'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FAILONERROR => false, // status 4xx/5xx interpretuje klient, nie cURL
            CURLOPT_CUSTOMREQUEST => $prepared['method'],
            CURLOPT_TIMEOUT_MS => (int) round($this->config->timeout * 1000),
            CURLOPT_CONNECTTIMEOUT_MS => (int) round($this->config->connectTimeout * 1000),
            CURLOPT_HTTPHEADER => $prepared['headers'],
        ] + self::protocolOptions();

        if ($this->config->caFile !== null) {
            // Windows CLI często nie ma skonfigurowanego `curl.cainfo` - bez tego
            // każde HTTPS pada na weryfikacji TLS. Wskazujemy bundle jawnie;
            // samej weryfikacji nie da się przez SDK wyłączyć.
            $options[CURLOPT_CAINFO] = $this->config->caFile;
        }

        if ($prepared['postFields'] !== null) {
            $options[CURLOPT_POSTFIELDS] = is_array($prepared['postFields'])
                ? $this->withCurlFiles($prepared['postFields'], $request->files)
                : $prepared['postFields'];
        }

        $responseHeaders = [];
        $options[CURLOPT_HEADERFUNCTION] = static function ($handle, string $line) use (&$responseHeaders): int {
            $length = strlen($line);
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }

            return $length;
        };

        $handle = curl_init();
        curl_setopt_array($handle, $options);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $errorNumber = curl_errno($handle);
        $errorMessage = $errorNumber !== 0 ? curl_error($handle) : '';
        curl_close($handle);

        if ($errorNumber !== 0) {
            throw new TransportException(
                sprintf('Błąd transportu (%s %s): %s', $prepared['method'], $prepared['url'], $errorMessage),
                $errorNumber,
            );
        }

        return new TransportResponse($status, is_string($body) ? $body : '', $responseHeaders);
    }

    /**
     * Buduje kompletne żądanie HTTP z żądania logicznego - URL, nagłówki i payload.
     * Czysta funkcja (poza rozwiązaniem tokenu z konfiguracji): bez sieci i bez
     * uchwytów curl, więc testowalna wprost.
     *
     * Pliki NIE są tu jeszcze zamieniane na CURLFile - `prepare()` oddaje pola
     * formularza, a obiekty curl dokłada `send()` (testy nie muszą dotykać dysku).
     *
     * @return array{method: non-empty-string, url: non-empty-string, headers: list<string>, postFields: array<string, string>|string|null}
     */
    public function prepare(TransportRequest $request): array
    {
        $method = strtoupper($request->method);
        if ($method === '') {
            throw new TransportException('Pusta metoda HTTP w żądaniu.');
        }

        if ($request->absolute) {
            if ($request->path === '') {
                throw new TransportException('Pusty absolutny URL żądania (podpisany link do pliku?).');
            }
            // Podpisany URL storage'u: żadnej bazy, żadnych nagłówków Tillio -
            // token w nagłówku wyciekłby do obcego hosta, a podpis i tak jest w URL-u.
            return [
                'method' => $method,
                'url' => $request->path,
                'headers' => [],
                'postFields' => null,
            ];
        }

        $url = $this->config->baseUrl() . '/' . ltrim($request->path, '/');

        // `_connector` (parametr platformy) dokłada transport, nie zasoby - zasoby
        // nie wiedzą, którym trybem działa klient. http_build_query serializuje
        // zagnieżdżenia jako `customField[klucz]=wartość` - dokładnie tej składni
        // oczekuje v2 (kropka w nazwie parametru ginie w PHP, stąd nawiasy).
        $query = $this->config->extraQuery() + $request->query;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $headers = [];
        foreach ($this->config->headers() as $name => $value) {
            $headers[] = $name . ': ' . $value;
        }
        $headers[] = 'Accept: application/json';

        $postFields = null;
        if ($request->files !== []) {
            // Multipart: curl sam ustawi `Content-Type: multipart/form-data` z boundary,
            // gdy dostanie tablicę - ręczny nagłówek by to zepsuł (brak boundary).
            $postFields = [];
            foreach ($request->body ?? [] as $name => $value) {
                if (!is_scalar($value)) {
                    throw new TransportException(sprintf(
                        'Pole formularza multipart "%s" musi być skalarem, dostano %s.',
                        (string) $name,
                        get_debug_type($value),
                    ));
                }
                $postFields[(string) $name] = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
            }
        } elseif ($request->body !== null) {
            $headers[] = 'Content-Type: application/json';
            $payload = json_encode($request->body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($payload === false) {
                throw new TransportException('Nie udało się zakodować ciała żądania do JSON: ' . json_last_error_msg());
            }
            $postFields = $payload;
        }

        return [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'postFields' => $postFields,
        ];
    }

    /**
     * Ogranicza cURL do HTTP(S) - również dla ewentualnych przekierowań.
     *
     * Domyślnie libcurl dopuszcza wszystko, co ma wkompilowane (`file`, `ftp`,
     * `dict`, `scp`...). SDK wysyła wyłącznie HTTP(S), więc reszta jest tu tylko
     * powierzchnią ataku: przy adresie z zewnątrz (`download()`) `file://` czytałoby
     * lokalne pliki procesu. Filtr adresu stoi wyżej, w `TillioClient::download()`;
     * ten zamek jest drugą warstwą - obowiązuje KAŻDE żądanie tego transportu,
     * niezależnie od tego, którędy adres do niego trafił.
     *
     * `CURLOPT_PROTOCOLS_STR` wymaga libcurl >= 7.85; na starszych ta sama blokada
     * idzie wycofywaną już bitmaską, żeby nie zostawić luki na wersjach LTS.
     *
     * @return array<int, int|string>
     */
    private static function protocolOptions(): array
    {
        if (self::$protocolOptions !== null) {
            return self::$protocolOptions;
        }

        $version = curl_version();
        $number = is_array($version) && is_int($version['version_number'] ?? null)
            ? $version['version_number']
            : 0;

        return self::$protocolOptions = $number >= 0x075500
            ? [
                CURLOPT_PROTOCOLS_STR => 'http,https',
                CURLOPT_REDIR_PROTOCOLS_STR => 'http,https',
            ]
            : [
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            ];
    }

    /**
     * Dokłada obiekty plików curl do pól formularza - dopiero na progu wysyłki.
     *
     * @param array<string, string>     $fields
     * @param array<string, FileUpload> $files
     *
     * @return array<string, mixed>
     */
    private function withCurlFiles(array $fields, array $files): array
    {
        $merged = $fields;
        foreach ($files as $field => $file) {
            if ($file->path !== null) {
                $merged[$field] = new \CURLFile($file->path, $file->mimeType ?? 'application/octet-stream', $file->fileName);
            } else {
                $merged[$field] = new \CURLStringFile((string) $file->content, $file->fileName, $file->mimeType ?? 'application/octet-stream');
            }
        }

        return $merged;
    }
}
