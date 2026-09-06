<?php

declare(strict_types=1);

namespace TillioCrm\Api\Resources;

use TillioCrm\Api\Dto\Address;
use TillioCrm\Api\Dto\AddressInput;
use TillioCrm\Api\Dto\Contractor;
use TillioCrm\Api\Dto\ContractorInput;
use TillioCrm\Api\Dto\UpsertResult;
use TillioCrm\Api\Dto\WriteOptions;
use TillioCrm\Api\Dto\WriteResult;
use TillioCrm\Api\Page;

/**
 * Kontrahenci - kartoteki firm/osób + ich adresy.
 *
 * Zapis dedupuje się przez `WriteOptions::duplicateCheck` (taxId, email,
 * `custom:<klucz>`...) - POST z trafieniem w duplikat zwraca 200 z ISTNIEJĄCYM
 * rekordem (patrz {@see WriteResult}). Przy tworzeniu wymagany niepusty `alias`.
 */
final readonly class Contractors extends Resource
{
    /**
     * `GET /v2/contractors` - strona listy.
     *
     *     $page = $client->contractors()->list(['taxId' => '0000000000', 'limit' => 25]);
     *
     * Filtry (komplet wg kontraktu): `name`, `taxId` (porównanie po wartości
     * znormalizowanej), `phone`, `id`, `alias`, `fullName`, `regon`, `pesel`,
     * `email`, `domain`, `country`, `externalId`, `note`, `contractorTypeId`
     * (`dictionaries()->contractorTypes()`), `contractorStatusId`
     * (`dictionaries()->contractorStatuses()`), `industryId`
     * (`dictionaries()->contractorIndustries()`), `contractorSourceId`
     * (`dictionaries()->contractorSources()`), `contractorPriorityId`
     * (`dictionaries()->contractorPriorities()`), `paymentTypeId`
     * (`dictionaries()->contractorPaymentTypes()`), `legalFormId`
     * (`dictionaries()->contractorLegalForms()`), `ownerUserId`, `parentId`,
     * `employeesCount`, `revenueCurrency`, `updatedAfter`/`updatedBefore`,
     * `createdAfter`/`createdBefore`, `customField[klucz]`, `include=address`
     * (dokleja adresy do rekordów), `sort`/`sortDir`, `page`/`limit`.
     *
     * @param array<string, mixed> $filters
     *
     * @return Page<Contractor>
     */
    public function list(array $filters = []): Page
    {
        return self::mapPage($this->client->get('v2/contractors', $filters), Contractor::fromArray(...));
    }

    /**
     * Pełny przebieg wszystkich stron (generator, wymuszone `sort=id` -
     * patrz {@see \TillioCrm\Api\TillioClient::iterateAll()}).
     *
     * @param array<string, mixed> $filters
     *
     * @return \Generator<int, Contractor>
     */
    public function iterate(array $filters = [], int $pageSize = 1000): \Generator
    {
        return $this->iterateMapped('v2/contractors', Contractor::fromArray(...), $filters, $pageSize);
    }

    /**
     * `GET /v2/contractors/{id}` - pojedyncza kartoteka (trasa nie przyjmuje
     * parametrów; adresy pobiera się przez {@see addresses()}).
     */
    public function get(int $id): Contractor
    {
        return Contractor::fromArray(self::single($this->client->get('v2/contractors/' . $id)));
    }

    /**
     * `POST /v2/contractors` - założenie kartoteki (201) albo trafienie
     * w duplikat (200 z istniejącym rekordem, bez zmiany danych).
     *
     *     $result = $client->contractors()->create(
     *         new ContractorInput(name: 'Acme', alias: 'acme', contractorTypeId: 1, taxId: '0000000000'),
     *         new WriteOptions(duplicateCheck: ['taxId']),
     *     );
     *     $result->created; $result->id; $result->matchedBy();
     *
     * @param ContractorInput|array<string, mixed> $input
     * @param WriteOptions|array<string, mixed>    $options
     *
     * @throws \TillioCrm\Api\Exception\IncompleteDuplicateCheckException zanim żądanie
     *                                                                    wyjdzie - pole z duplicateCheck bez wartości w payloadzie
     */
    public function create(ContractorInput|array $input, WriteOptions|array $options = []): WriteResult
    {
        $payload = self::payload($input) + self::payload($options);
        self::assertDuplicateCheckUsable($payload);

        return WriteResult::fromResponse($this->client->post('v2/contractors', $payload), 'contractorId');
    }

    /**
     * `PUT /v2/contractors/{id}` - aktualizacja pól podanych w input
     * (pola pominięte zostają bez zmian). Jawny null (czyszczenie pola,
     * np. `externalId`) wymaga fallbacku tablicowego.
     *
     * @param ContractorInput|array<string, mixed> $input
     */
    public function update(int $id, ContractorInput|array $input): WriteResult
    {
        return WriteResult::fromResponse(
            $this->client->put('v2/contractors/' . $id, self::payload($input)),
            'contractorId',
        );
    }

    /**
     * `POST /v2/contractors/upsert` - paczka "utwórz albo zaktualizuj".
     *
     * HTTP jest ZAWSZE 200 - wynik per item siedzi w {@see UpsertResult}
     * (`created|updated|failed`); sprawdzaj `hasFailures()`.
     *
     * @param list<ContractorInput|array<string, mixed>> $items
     * @param WriteOptions|array<string, mixed>          $options `duplicateCheck` obowiązuje
     *                                                            KAŻDY item - SDK pilnuje, żeby
     *                                                            wskazane pola miały wartości
     *
     * @throws \TillioCrm\Api\Exception\IncompleteDuplicateCheckException zanim żądanie
     *                                                                    wyjdzie - item z polem duplicateCheck bez wartości
     */
    public function upsert(array $items, WriteOptions|array $options = []): UpsertResult
    {
        $opts = self::payload($options);
        $rows = [];
        foreach ($items as $item) {
            $row = self::payload($item);
            self::assertDuplicateCheckUsable($row + $opts);
            $rows[] = $row;
        }

        return UpsertResult::fromResponse($this->client->post('v2/contractors/upsert', ['items' => $rows] + $opts));
    }

    /**
     * `GET /v2/contractors/{id}/addresses` - wszystkie adresy kartoteki
     * (lista bez stronicowania).
     *
     * @return list<Address>
     */
    public function addresses(int $contractorId): array
    {
        return self::mapList(
            $this->client->get(sprintf('v2/contractors/%d/addresses', $contractorId)),
            Address::fromArray(...),
        );
    }

    /**
     * `POST /v2/contractors/{id}/addresses` - dodanie adresu. Wymagane
     * `addressTypeId` (typy z `GET /v2/address/types`).
     *
     * @param AddressInput|array<string, mixed> $input
     */
    public function addAddress(int $contractorId, AddressInput|array $input): WriteResult
    {
        return WriteResult::fromResponse(
            $this->client->post(sprintf('v2/contractors/%d/addresses', $contractorId), self::payload($input)),
            'addressId',
        );
    }

    /**
     * `PUT /v2/addresses/{id}` - aktualizacja adresu (id z listy adresów).
     *
     * @param AddressInput|array<string, mixed> $input
     */
    public function updateAddress(int $addressId, AddressInput|array $input): WriteResult
    {
        return WriteResult::fromResponse(
            $this->client->put('v2/addresses/' . $addressId, self::payload($input)),
            'addressId',
        );
    }

    /**
     * `DELETE /v2/addresses/{id}` - usunięcie adresu.
     */
    public function deleteAddress(int $addressId): void
    {
        $this->client->delete('v2/addresses/' . $addressId);
    }
}
