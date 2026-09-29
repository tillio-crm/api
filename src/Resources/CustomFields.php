<?php

declare(strict_types=1);

namespace TillioCrm\Api\Resources;

use TillioCrm\Api\Dto\CustomFieldDefinition;
use TillioCrm\Api\Dto\CustomFieldFile;
use TillioCrm\Api\Dto\CustomFieldInput;
use TillioCrm\Api\Dto\CustomFieldOptionInput;
use TillioCrm\Api\Dto\CustomFieldUpdateInput;
use TillioCrm\Api\Dto\CustomFieldUpdateResult;
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
     * `PUT /v2/{entity}/custom-fields/{key}` - zmiana istniejącego pola:
     * PRZYPISANIE do podtypów rekordów (`assignedTo`, encje `note`, `ticket`,
     * `service`, `lead`, `pipeline`; patrz {@see CustomFieldInput::$assignedTo})
     * i od API 2.17.0 dopisanie opcji selecta (`options`, każda encja) - osobno
     * albo razem. Samo dopisanie opcji ma wygodniejszą, typowaną metodę
     * {@see appendOptions()}.
     *
     * `assignedTo` to KOMPLETNA lista docelowa albo `'all'` (wszystkie podtypy
     * istniejące w chwili zapisu, API >= 2.17.0). Usunięcie podtypu KASUJE wartości
     * pola w jego rekordach, więc wymaga `allowUnassign: true` (wyłącznie bool: od
     * API 2.14.0 napis "false" albo 1/0 to 422, wcześniej napis był brany za zgodę).
     * Od API 2.17.0 `assignedTo` nie jest wymagane, o ile jest `options`; input bez
     * obu to 422 `body.required`, a `assignedTo` przy encji bez podtypów - 422.
     * Innych właściwości definicji nie da się zmienić - pole z błędną definicją
     * trzeba założyć od nowa.
     *
     * Odpowiedź w `WriteResult::$data`: `key`, `assignedTo` (tylko encje
     * z podtypami) i `options` (tylko SELECT/MULTISELECT, od API 2.17.0) -
     * typowany widok: `CustomFieldUpdateResult::fromArray($result->data)`.
     * `WriteResult::$id` jest tu zawsze null (definicję adresuje klucz).
     *
     * @param CustomFieldUpdateInput|array{assignedTo?: list<int>|'all', allowUnassign?: bool, options?: list<string|array{name: string, color?: string}>} $input
     */
    public function update(string $entity, string $key, CustomFieldUpdateInput|array $input): WriteResult
    {
        return WriteResult::fromResponse(
            $this->client->put(self::fieldPath($entity, $key), self::payload($input)),
            'id',
        );
    }

    /**
     * `PUT /v2/{entity}/custom-fields/{key}` z samym `options` - dopisanie opcji
     * do pola SELECT albo MULTISELECT dowolnej encji (API >= 2.17.0).
     *
     *     $field = $client->customFields()->appendOptions('lead', 'leads_select_3', [
     *         'Facebook Lead Ads',
     *         new CustomFieldOptionInput('Polecenie', '#00aa00'),
     *     ]);
     *     $value = $field->optionValue('Polecenie');   // id opcji do customField
     *
     * TYLKO dopisuje: nazwy już obecne w polu API pomija, istniejące opcje zostają
     * bez zmian (id, kolor, kolejność). Tę samą pełną listę oczekiwanych opcji
     * można więc wysyłać przy każdej synchronizacji - powtórka niczego nie zmienia.
     * Usunięcia ani zmiany nazwy API nie robi (CRM skasowałby przy tym wartości
     * w rekordach) - to operacja w panelu. Pole innego typu = 422 na `options`,
     * pusta lista = 422. Wymaga klucza API superadmina; instancja starsza niż
     * 2.17.0 odrzuci `options` błędem 422.
     *
     * @param list<string|CustomFieldOptionInput> $options nazwy opcji albo opcje z kolorem
     *
     * @return CustomFieldUpdateResult komplet opcji pola po zapisie (`options`) z ich id
     */
    public function appendOptions(string $entity, string $key, array $options): CustomFieldUpdateResult
    {
        return CustomFieldUpdateResult::fromArray(self::single($this->client->put(
            self::fieldPath($entity, $key),
            (new CustomFieldUpdateInput(options: $options))->toArray(),
        )));
    }

    private static function fieldPath(string $entity, string $key): string
    {
        return sprintf('v2/%s/custom-fields/%s', rawurlencode($entity), rawurlencode($key));
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
