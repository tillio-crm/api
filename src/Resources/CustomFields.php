<?php

declare(strict_types=1);

namespace TillioCrm\Api\Resources;

use TillioCrm\Api\Dto\CustomFieldDefinition;
use TillioCrm\Api\Dto\CustomFieldFile;
use TillioCrm\Api\Dto\CustomFieldInput;
use TillioCrm\Api\Dto\WriteResult;
use TillioCrm\Api\Transport\FileUpload;

/**
 * Definicje pól niestandardowych + provisioning + pliki pól typu FILE.
 *
 * Wartości pól żyją na rekordach encji (`customField` w odczycie i zapisie
 * kontrahenta/kontaktu/...); tu zarządza się DEFINICJAMI. Założenie pola wymaga
 * klucza API o uprawnieniach administratora. Pułapki definicji opisuje
 * {@see CustomFieldInput} - zwłaszcza obowiązkowe `editableBy`.
 *
 * Pola typu FILE mają OSOBNĄ ścieżkę zapisu i odczytu pliku
 * ({@see getFieldFile()}, {@see uploadFieldFile()}, {@see deleteFieldFile()}) -
 * ich wartości nie przechodzą przez `customField` w PUT rekordu (patrz
 * {@see CustomFieldFile}). Dostępne od wersji API 2.6.0.
 */
final readonly class CustomFields extends Resource
{
    /**
     * `GET /v2/{entity}/custom-fields` - definicje pól encji
     * (np. `contractor`, `contact`).
     *
     * Zakładanie pól rób idempotentnie: najpierw ta lista, twórz tylko
     * brakujące (etykieta jest unikalna w encji).
     *
     * @return list<CustomFieldDefinition>
     */
    public function list(string $entity): array
    {
        return self::mapList(
            $this->client->get(sprintf('v2/%s/custom-fields', rawurlencode($entity))),
            CustomFieldDefinition::fromArray(...),
        );
    }

    /**
     * `POST /v2/custom-fields` - nowa definicja pola. Wymagane `entity`, `name`,
     * `type`; klucz pola nadaje CRM - odczytaj go z wyniku (`data.key`)
     * i zapisz po swojej stronie.
     *
     *     $result = $client->customFields()->create(new CustomFieldInput(
     *         entity: 'contractor', name: 'ERP ID', type: 'string',
     *         editableBy: ['userIds' => [7]],
     *     ));
     *     $key = $result->data['key'];
     *
     * @param CustomFieldInput|array<string, mixed> $input
     */
    public function create(CustomFieldInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->post('v2/custom-fields', self::payload($input)), 'id');
    }

    /**
     * `PUT /v2/{entity}/custom-fields/{key}` - zmiana PRZYPISANIA pola do
     * użytkowników (wymagane `assignedTo`; `allowUnassign` pozwala odpiąć).
     * Innych właściwości definicji nie da się zmienić - pole z błędną definicją
     * trzeba założyć od nowa.
     *
     * @param array{assignedTo: list<int>, allowUnassign?: bool} $input
     */
    public function update(string $entity, string $key, array $input): WriteResult
    {
        return WriteResult::fromResponse(
            $this->client->put(sprintf('v2/%s/custom-fields/%s', rawurlencode($entity), rawurlencode($key)), $input),
            'id',
        );
    }

    /**
     * `GET /v2/{entity}/{id}/custom-fields/{key}/file` - plik z pola typu FILE
     * wskazanego rekordu (metadane + ŚWIEŻY `downloadUrl`, TTL ~1 min).
     * `null` = pole istnieje, ale nie ma w nim pliku.
     *
     * Encje z polami plikowymi: `contractor`, `contact`, `note`, `lead`,
     * `ticket`, `service`, `project`, `pipeline` (zadania NIE mają tej końcówki).
     * Plik pobiera się z `->downloadUrl` przez {@see \TillioCrm\Api\TillioClient::download()}.
     *
     * Wymaga API >= 2.6.0.
     */
    public function getFieldFile(string $entity, int $id, string $key): ?CustomFieldFile
    {
        $response = $this->client->get(sprintf(
            'v2/%s/%d/custom-fields/%s/file',
            rawurlencode($entity),
            $id,
            rawurlencode($key),
        ));

        // `data: null` = pole bez pliku; obiekt = plik. Czytamy body wprost,
        // bo `data()` zwija null do pustej tablicy i nie odróżniłby tych stanów.
        $data = $response->body['data'] ?? null;

        return is_array($data) && $data !== [] ? CustomFieldFile::fromArray($data) : null;
    }

    /**
     * `POST /v2/{entity}/{id}/custom-fields/{key}/file` - wgranie pliku do pola
     * typu FILE (multipart, pole `file`). Pole mieści JEDEN plik, więc kolejny
     * upload ZASTĘPUJE poprzedni. Limit 25 MB (twardy limit CRM dla pól
     * niestandardowych, niższy niż 128 MB załączników).
     *
     * BEZ RETRY (jak każdy upload): powtórka po timeoucie, który doszedł,
     * nadpisałaby świeżo wgrany plik. Pole musi być przypisane do podtypu
     * rekordu (typ notatki, proces zgłoszenia...) - inaczej 422 `customField.notAssigned`.
     *
     * Wymaga API >= 2.6.0.
     */
    public function uploadFieldFile(string $entity, int $id, string $key, FileUpload $file): CustomFieldFile
    {
        return CustomFieldFile::fromArray(self::single($this->client->postMultipart(
            sprintf('v2/%s/%d/custom-fields/%s/file', rawurlencode($entity), $id, rawurlencode($key)),
            [],
            ['file' => $file],
        )));
    }

    /**
     * `DELETE /v2/{entity}/{id}/custom-fields/{key}/file` - czyści pole typu
     * FILE: kasuje plik z przestrzeni plików instancji i zeruje wartość pola.
     *
     * Wymaga API >= 2.6.0.
     */
    public function deleteFieldFile(string $entity, int $id, string $key): void
    {
        $this->client->delete(sprintf(
            'v2/%s/%d/custom-fields/%s/file',
            rawurlencode($entity),
            $id,
            rawurlencode($key),
        ));
    }
}
