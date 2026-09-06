<?php

declare(strict_types=1);

namespace TillioCrm\Api\Resources;

use TillioCrm\Api\Dto\WikiBase;
use TillioCrm\Api\Dto\WikiBaseInput;
use TillioCrm\Api\Dto\WikiCategory;
use TillioCrm\Api\Dto\WikiEntry;
use TillioCrm\Api\Dto\WikiEntryInput;
use TillioCrm\Api\Dto\WriteResult;
use TillioCrm\Api\Page;

/**
 * Baza wiedzy wiki: bazy → kategorie → wpisy (artykuły i procedury).
 * Jedna z dwóch encji z DELETE w API v2 (druga to adresy kontrahenta).
 */
final readonly class Wiki extends Resource
{
    /**
     * `GET /v2/wiki/bases` - bazy wiedzy (lista bez stronicowania).
     *
     * Filtry (komplet wg kontraktu): `type` (`article`/`procedure`), `archived`.
     *
     * @param array<string, mixed> $filters
     *
     * @return list<WikiBase>
     */
    public function bases(array $filters = []): array
    {
        return self::mapList($this->client->get('v2/wiki/bases', $filters), WikiBase::fromArray(...));
    }

    /**
     * `POST /v2/wiki/bases` - nowa baza (wymagane `name`).
     *
     * @param WikiBaseInput|array<string, mixed> $input
     */
    public function createBase(WikiBaseInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->post('v2/wiki/bases', self::payload($input)), 'baseId');
    }

    /**
     * `PUT /v2/wiki/bases/{id}` - aktualizacja bazy.
     *
     * @param WikiBaseInput|array<string, mixed> $input
     */
    public function updateBase(int $id, WikiBaseInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->put('v2/wiki/bases/' . $id, self::payload($input)), 'baseId');
    }

    /**
     * `GET /v2/wiki/bases/{id}/categories` - kategorie bazy.
     *
     * @return list<WikiCategory>
     */
    public function categories(int $baseId): array
    {
        return self::mapList(
            $this->client->get(sprintf('v2/wiki/bases/%d/categories', $baseId)),
            WikiCategory::fromArray(...),
        );
    }

    /**
     * `POST /v2/wiki/bases/{id}/categories` - nowa kategoria (wymagane `name`).
     *
     * @param array<string, mixed> $input
     */
    public function createCategory(int $baseId, array $input): WriteResult
    {
        return WriteResult::fromResponse(
            $this->client->post(sprintf('v2/wiki/bases/%d/categories', $baseId), $input),
            'categoryId',
        );
    }

    /**
     * `PUT /v2/wiki/categories/{id}` - aktualizacja kategorii.
     *
     * @param array<string, mixed> $input
     */
    public function updateCategory(int $id, array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->put('v2/wiki/categories/' . $id, $input), 'categoryId');
    }

    /**
     * `GET /v2/wiki/entries` - strona listy wpisów.
     *
     * Filtry (komplet wg kontraktu): `baseId`, `categoryId`, `published`,
     * `archived`, `search` (pełnotekstowe), `page`/`limit`. Trasa nie ma `sort`.
     *
     * @param array<string, mixed> $filters
     *
     * @return Page<WikiEntry>
     */
    public function entries(array $filters = []): Page
    {
        return self::mapPage($this->client->get('v2/wiki/entries', $filters), WikiEntry::fromArray(...));
    }

    /**
     * Pełny przebieg wszystkich stron wpisów. BEZ wymuszania `sort=id` - trasa
     * nie przyjmuje parametru `sort`.
     *
     * @param array<string, mixed> $filters
     *
     * @return \Generator<int, WikiEntry>
     */
    public function iterateEntries(array $filters = [], int $pageSize = 1000): \Generator
    {
        return $this->iterateMapped('v2/wiki/entries', WikiEntry::fromArray(...), $filters, $pageSize, stableSort: null);
    }

    /**
     * `GET /v2/wiki/entries/{id}` - pojedynczy wpis z pełną treścią.
     */
    public function getEntry(int $id): WikiEntry
    {
        return WikiEntry::fromArray(self::single($this->client->get('v2/wiki/entries/' . $id)));
    }

    /**
     * `POST /v2/wiki/entries` - nowy wpis (wymagane `categoryId` i `title`).
     *
     * @param WikiEntryInput|array<string, mixed> $input
     */
    public function createEntry(WikiEntryInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->post('v2/wiki/entries', self::payload($input)), 'entryId');
    }

    /**
     * `PUT /v2/wiki/entries/{id}` - aktualizacja wpisu.
     *
     * @param WikiEntryInput|array<string, mixed> $input
     */
    public function updateEntry(int $id, WikiEntryInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->put('v2/wiki/entries/' . $id, self::payload($input)), 'entryId');
    }

    /**
     * `DELETE /v2/wiki/entries/{id}` - usunięcie wpisu (nieodwracalne).
     */
    public function deleteEntry(int $id): void
    {
        $this->client->delete('v2/wiki/entries/' . $id);
    }
}
