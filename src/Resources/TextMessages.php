<?php

declare(strict_types=1);

namespace TillioCrm\Api\Resources;

use TillioCrm\Api\Dto\TextMessage;
use TillioCrm\Api\Dto\TextMessageInput;
use TillioCrm\Api\Page;

/**
 * Wiadomości SMS - rekordy z integracji bramek SMS, każdy z notatką na
 * kartotece. Wymaga API >= 2.10.0.
 */
final readonly class TextMessages extends Resource
{
    /**
     * `GET /v2/text-messages` - strona listy.
     *
     * Filtry (komplet wg kontraktu): `contractorId`, `contactId`,
     * `sentAfter`/`sentBefore`, `id`, `provider`, `source`, `sourceId`,
     * `direction`, `status`, `ownNumber`, `remoteNumber`, `body`, `userId`,
     * `sort`/`sortDir`, `page`/`limit`.
     *
     * @param array<string, mixed> $filters
     *
     * @return Page<TextMessage>
     */
    public function list(array $filters = []): Page
    {
        return self::mapPage($this->client->get('v2/text-messages', $filters), TextMessage::fromArray(...));
    }

    /**
     * Pełny przebieg wszystkich stron (generator, wymuszone `sort=id`).
     *
     * @param array<string, mixed> $filters
     *
     * @return \Generator<int, TextMessage>
     */
    public function iterate(array $filters = [], int $pageSize = 1000): \Generator
    {
        return $this->iterateMapped('v2/text-messages', TextMessage::fromArray(...), $filters, $pageSize);
    }

    /**
     * `GET /v2/text-messages/{id}` - pojedyncza wiadomość.
     */
    public function get(int $id): TextMessage
    {
        return TextMessage::fromArray(self::single($this->client->get('v2/text-messages/' . $id)));
    }

    /**
     * `POST /v2/text-messages` - nowa wiadomość. Wymagane source, sourceId,
     * direction, status, remoteNumber, body, sentAt.
     *
     * @param TextMessageInput|array<string, mixed> $input
     */
    public function create(TextMessageInput|array $input): TextMessage
    {
        return TextMessage::fromArray(self::single($this->client->post('v2/text-messages', self::payload($input))));
    }

    /**
     * `PUT /v2/text-messages/{id}` - aktualizacja pól podanych w input.
     *
     * @param TextMessageInput|array<string, mixed> $input
     */
    public function update(int $id, TextMessageInput|array $input): TextMessage
    {
        return TextMessage::fromArray(self::single($this->client->put('v2/text-messages/' . $id, self::payload($input))));
    }
}
