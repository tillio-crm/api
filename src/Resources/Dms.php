<?php

declare(strict_types=1);

namespace TillioCrm\Api\Resources;

use TillioCrm\Api\Dto\DmsDirectory;
use TillioCrm\Api\Dto\DmsDocument;
use TillioCrm\Api\Dto\DmsListing;
use TillioCrm\Api\Dto\WriteResult;
use TillioCrm\Api\Transport\FileUpload;

/**
 * Repozytorium plików kontrahenta (DMS).
 *
 * PLIKI ADRESUJE SIĘ `publicId` (string), NIGDY `id` - szczegóły
 * w {@see DmsDocument}. Pobieranie pliku idzie poza API: `getDocument()` oddaje
 * świeży `downloadUrl` (podpisany link, TTL ~1 minuta) do pobrania przez
 * {@see \TillioCrm\Api\TillioClient::download()} - działa to także w trybie
 * proxy, bo przez proxy jedzie tylko JSON z linkiem, a plik prosto ze storage'u.
 */
final readonly class Dms extends Resource
{
    /**
     * `GET /v2/contractors/{contractorId}/dms` - zawartość JEDNEGO poziomu
     * drzewa (bez stronicowania; `$directoryId === null` = poziom główny).
     * Katalog spoza tego kontrahenta to 404, nie pusta lista.
     */
    public function listing(int $contractorId, ?int $directoryId = null): DmsListing
    {
        return DmsListing::fromResponse(
            $this->client->get(sprintf('v2/contractors/%d/dms', $contractorId), ['directoryId' => $directoryId]),
        );
    }

    /**
     * `POST /v2/contractors/{contractorId}/dms/directories` - nowy katalog
     * (wymagane `name`; `parentId` = katalog nadrzędny).
     *
     * DMS nie ma duplicateCheck - powtórzone wywołanie zrobi DRUGI katalog
     * o tej samej nazwie. Idempotentne zakładanie: {@see ensureDirectory()}.
     */
    public function createDirectory(int $contractorId, string $name, ?int $parentId = null): WriteResult
    {
        $payload = ['name' => $name];
        if ($parentId !== null) {
            $payload['parentId'] = $parentId;
        }

        return WriteResult::fromResponse(
            $this->client->post(sprintf('v2/contractors/%d/dms/directories', $contractorId), $payload),
            'directoryId',
        );
    }

    /**
     * Idempotentne założenie katalogu: najpierw szukanie po nazwie na poziomie,
     * dopiero potem POST.
     */
    public function ensureDirectory(int $contractorId, string $name, ?int $parentId = null): DmsDirectory
    {
        $existing = $this->listing($contractorId, $parentId)->findDirectory($name);
        if ($existing !== null) {
            return $existing;
        }

        $created = $this->createDirectory($contractorId, $name, $parentId);

        return DmsDirectory::fromArray($created->data);
    }

    /**
     * `POST /v2/contractors/{contractorId}/dms/documents` - upload pliku
     * (multipart, pole `file`, limit 128 MB; `directoryId` = katalog docelowy,
     * brak = poziom główny). BEZ RETRY: powtórka po timeoutcie, który doszedł,
     * zostawiłaby w CRM drugi plik.
     *
     *     $doc = $client->dms()->uploadDocument(42, FileUpload::fromPath('C:/umowa.pdf'), directoryId: 7);
     */
    public function uploadDocument(int $contractorId, FileUpload $file, ?int $directoryId = null): DmsDocument
    {
        $fields = [];
        if ($directoryId !== null) {
            $fields['directoryId'] = $directoryId;
        }

        $response = $this->client->postMultipart(
            sprintf('v2/contractors/%d/dms/documents', $contractorId),
            $fields,
            ['file' => $file],
        );

        return DmsDocument::fromArray(self::single($response));
    }

    /**
     * `GET /v2/dms/documents/{publicId}` - metadane + ŚWIEŻY `downloadUrl`
     * (TTL ~1 minuta; nie buforuj linku, po wygaśnięciu zawołaj ponownie).
     *
     * `$publicId` bierze się WYŁĄCZNIE z listingu/uploadu (pole `publicId`) -
     * numeryczne `id` rekordu w tej ścieżce nie działa (404 albo cudzy plik).
     */
    public function getDocument(string $publicId): DmsDocument
    {
        return DmsDocument::fromArray(self::single($this->client->get('v2/dms/documents/' . rawurlencode($publicId))));
    }

    /**
     * `PUT /v2/dms/documents/{publicId}` - zmiana nazwy to JEDYNA edycja
     * dostępna przez API.
     *
     * PODAWAJ NAZWĘ BEZ ROZSZERZENIA: CRM zawsze dokleja rozszerzenie ze starej
     * nazwy, więc `raport.pdf` skończy jako `raport.pdf.pdf` (potwierdzone na
     * żywym API). Duplikat w katalogu dostaje sufiks - nazwa w odpowiedzi bywa
     * inna niż wysłana i to ONA jest prawdziwa.
     */
    public function renameDocument(string $publicId, string $fileName): DmsDocument
    {
        return DmsDocument::fromArray(self::single(
            $this->client->put('v2/dms/documents/' . rawurlencode($publicId), ['fileName' => $fileName]),
        ));
    }
}
