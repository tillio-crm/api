<?php

declare(strict_types=1);

namespace TillioCrm\Api\Resources;

use TillioCrm\Api\Dto\Contact;
use TillioCrm\Api\Dto\ContactInput;
use TillioCrm\Api\Dto\NoteInput;
use TillioCrm\Api\Dto\UpsertResult;
use TillioCrm\Api\Dto\WriteOptions;
use TillioCrm\Api\Dto\WriteResult;
use TillioCrm\Api\Page;

/**
 * Osoby kontaktowe. W upsertach trafienie w duplikat oznacza PODPIĘCIE danych
 * do istniejącej osoby (status `attached`, nie `updated`).
 */
final readonly class Contacts extends Resource
{
    /**
     * `GET /v2/contacts` - strona listy.
     *
     * Filtry (komplet wg kontraktu): `contractorId`, `email`, `name`, `phone`,
     * `phoneAlternative`, `contactStatusId`, `id`, `firstName`, `lastName`,
     * `position`, `note`, `ownerUserId`, `externalId`,
     * `updatedAfter`/`updatedBefore`, `createdAfter`/`createdBefore`,
     * `customField[klucz]`, `sort`/`sortDir`, `page`/`limit`.
     *
     * @param array<string, mixed> $filters
     *
     * @return Page<Contact>
     */
    public function list(array $filters = []): Page
    {
        return self::mapPage($this->client->get('v2/contacts', $filters), Contact::fromArray(...));
    }

    /**
     * Pełny przebieg wszystkich stron (generator, wymuszone `sort=id`).
     *
     * @param array<string, mixed> $filters
     *
     * @return \Generator<int, Contact>
     */
    public function iterate(array $filters = [], int $pageSize = 1000): \Generator
    {
        return $this->iterateMapped('v2/contacts', Contact::fromArray(...), $filters, $pageSize);
    }

    /**
     * `GET /v2/contacts/{id}` - pojedyncza osoba (trasa nie przyjmuje parametrów).
     */
    public function get(int $id): Contact
    {
        return Contact::fromArray(self::single($this->client->get('v2/contacts/' . $id)));
    }

    /**
     * `POST /v2/contacts` - założenie osoby (201) albo trafienie w duplikat
     * (200 z istniejącym rekordem). Wymagane `firstName`.
     *
     * @param ContactInput|array<string, mixed> $input
     * @param WriteOptions|array<string, mixed> $options
     *
     * @throws \TillioCrm\Api\Exception\IncompleteDuplicateCheckException zanim żądanie
     *                                                                    wyjdzie - pole z duplicateCheck bez wartości w payloadzie
     */
    public function create(ContactInput|array $input, WriteOptions|array $options = []): WriteResult
    {
        $payload = self::payload($input) + self::payload($options);
        self::assertDuplicateCheckUsable($payload);

        return WriteResult::fromResponse($this->client->post('v2/contacts', $payload), 'contactId');
    }

    /**
     * `PUT /v2/contacts/{id}` - aktualizacja pól podanych w input.
     *
     * @param ContactInput|array<string, mixed> $input
     */
    public function update(int $id, ContactInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->put('v2/contacts/' . $id, self::payload($input)), 'contactId');
    }

    /**
     * `POST /v2/contacts/upsert` - paczka. HTTP zawsze 200; statusy per item:
     * `created|attached|failed` (attached = dane dopięte do istniejącej osoby).
     *
     * @param list<ContactInput|array<string, mixed>> $items
     * @param WriteOptions|array<string, mixed>       $options
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

        return UpsertResult::fromResponse($this->client->post('v2/contacts/upsert', ['items' => $rows] + $opts));
    }

    /**
     * `POST /v2/contacts/{contactId}/notes` - notatka pod osobą kontaktową.
     * Wymagane `noteTypeId` i `title`. Z `contractorId` (jeden z kontrahentów
     * kontaktu) notatka wisi na firmie i osobie; bez niego - domyślnie pod
     * kontrahentem głównym kontaktu (od API 2.11.0). Wymaga API >= 2.10.0.
     *
     * @param NoteInput|array<string, mixed> $input
     */
    public function createNote(int $contactId, NoteInput|array $input): WriteResult
    {
        return WriteResult::fromResponse(
            $this->client->post(sprintf('v2/contacts/%d/notes', $contactId), self::payload($input)),
            'noteId',
        );
    }
}
