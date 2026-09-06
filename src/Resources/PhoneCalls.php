<?php

declare(strict_types=1);

namespace TillioCrm\Api\Resources;

use TillioCrm\Api\Dto\PhoneCall;
use TillioCrm\Api\Dto\PhoneCallInput;
use TillioCrm\Api\Page;

/**
 * Polaczenia telefoniczne - rekordy rozmow z integracji VoIP, kazde z notatka
 * na kartotece. Wymaga API >= 2.10.0.
 */
final readonly class PhoneCalls extends Resource
{
    /**
     * `GET /v2/phone-calls` - strona listy.
     *
     * Filtry (komplet wg kontraktu): `contractorId`, `contactId`,
     * `startedAfter`/`startedBefore`, `id`, `provider`, `source`, `sourceId`,
     * `direction`, `status`, `ownNumber`, `remoteNumber`, `userId`,
     * `recordingCallId`, `sort`/`sortDir`, `page`/`limit`.
     *
     * @param array<string, mixed> $filters
     *
     * @return Page<PhoneCall>
     */
    public function list(array $filters = []): Page
    {
        return self::mapPage($this->client->get('v2/phone-calls', $filters), PhoneCall::fromArray(...));
    }

    /**
     * Pelny przebieg wszystkich stron (generator, wymuszone `sort=id`).
     *
     * @param array<string, mixed> $filters
     *
     * @return \Generator<int, PhoneCall>
     */
    public function iterate(array $filters = [], int $pageSize = 1000): \Generator
    {
        return $this->iterateMapped('v2/phone-calls', PhoneCall::fromArray(...), $filters, $pageSize);
    }

    /**
     * `GET /v2/phone-calls/{id}` - pojedyncze polaczenie.
     */
    public function get(int $id): PhoneCall
    {
        return PhoneCall::fromArray(self::single($this->client->get('v2/phone-calls/' . $id)));
    }

    /**
     * `POST /v2/phone-calls` - nowe polaczenie. Wymagane source, sourceId,
     * direction, status, remoteNumber, startedAt.
     *
     * @param PhoneCallInput|array<string, mixed> $input
     */
    public function create(PhoneCallInput|array $input): PhoneCall
    {
        return PhoneCall::fromArray(self::single($this->client->post('v2/phone-calls', self::payload($input))));
    }

    /**
     * `PUT /v2/phone-calls/{id}` - aktualizacja pol podanych w input.
     *
     * @param PhoneCallInput|array<string, mixed> $input
     */
    public function update(int $id, PhoneCallInput|array $input): PhoneCall
    {
        return PhoneCall::fromArray(self::single($this->client->put('v2/phone-calls/' . $id, self::payload($input))));
    }
}
