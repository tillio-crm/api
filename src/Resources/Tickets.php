<?php

declare(strict_types=1);

namespace TillioCrm\Api\Resources;

use TillioCrm\Api\Dto\Ticket;
use TillioCrm\Api\Dto\TicketInput;
use TillioCrm\Api\Dto\TicketMessage;
use TillioCrm\Api\Dto\TicketMessageInput;
use TillioCrm\Api\Dto\WriteResult;
use TillioCrm\Api\Page;

/**
 * Zgłoszenia + wątek wiadomości.
 */
final readonly class Tickets extends Resource
{
    /**
     * `GET /v2/tickets` - strona listy.
     *
     * Filtry (komplet wg kontraktu): `contractorId`, `ticketStatusId` (statusy:
     * `dictionaries()->ticketStatuses()`), `ticketStageId` (etapy z procesów
     * zgłoszeń: `dictionaries()->ticketProcesses()`), `ticketSourceId`
     * (`dictionaries()->ticketSources()`), `ownerUserId`, `open`, `archived`,
     * `title`, `id`, `priority`, `creatorUserId`, `lastResponseUserId`, `email`,
     * `relatedTicketId`, `uuId`, `updatedAfter`/`updatedBefore`,
     * `createdAfter`/`createdBefore`, `customField[klucz]`, `sort`/`sortDir`,
     * `page`/`limit`.
     *
     * @param array<string, mixed> $filters
     *
     * @return Page<Ticket>
     */
    public function list(array $filters = []): Page
    {
        return self::mapPage($this->client->get('v2/tickets', $filters), Ticket::fromArray(...));
    }

    /**
     * Pełny przebieg wszystkich stron (generator, wymuszone `sort=id`).
     *
     * @param array<string, mixed> $filters
     *
     * @return \Generator<int, Ticket>
     */
    public function iterate(array $filters = [], int $pageSize = 1000): \Generator
    {
        return $this->iterateMapped('v2/tickets', Ticket::fromArray(...), $filters, $pageSize);
    }

    /**
     * `GET /v2/tickets/{id}` - pojedyncze zgłoszenie (trasa nie przyjmuje parametrów).
     */
    public function get(int $id): Ticket
    {
        return Ticket::fromArray(self::single($this->client->get('v2/tickets/' . $id)));
    }

    /**
     * `POST /v2/tickets` - nowe zgłoszenie. Wymagane `title`; `email` pozwala
     * powiązać zgłoszenie z nadawcą spoza CRM.
     *
     * @param TicketInput|array<string, mixed> $input
     */
    public function create(TicketInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->post('v2/tickets', self::payload($input)), 'ticketId');
    }

    /**
     * `PUT /v2/tickets/{id}` - aktualizacja pól podanych w input (status/etap
     * zgłoszenia zmienia proces obsługi, nie ten zapis - patrz {@see TicketInput}).
     *
     * @param TicketInput|array<string, mixed> $input
     */
    public function update(int $id, TicketInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->put('v2/tickets/' . $id, self::payload($input)), 'ticketId');
    }

    /**
     * `GET /v2/tickets/{id}/messages` - wątek wiadomości zgłoszenia
     * (lista bez stronicowania, trasa nie przyjmuje parametrów).
     *
     * @return list<TicketMessage>
     */
    public function messages(int $ticketId): array
    {
        return self::mapList(
            $this->client->get(sprintf('v2/tickets/%d/messages', $ticketId)),
            TicketMessage::fromArray(...),
        );
    }

    /**
     * `POST /v2/tickets/{id}/messages` - nowa wiadomość w zgłoszeniu.
     * Wymagane `text`; `visibility` = `public`/`internal`.
     *
     * @param TicketMessageInput|array<string, mixed> $input
     */
    public function addMessage(int $ticketId, TicketMessageInput|array $input): WriteResult
    {
        return WriteResult::fromResponse(
            $this->client->post(sprintf('v2/tickets/%d/messages', $ticketId), self::payload($input)),
            'messageId',
        );
    }
}
