<?php

declare(strict_types=1);

namespace TillioCrm\Api\Resources;

use TillioCrm\Api\Dto\Cast;
use TillioCrm\Api\Dto\PipelineItem;
use TillioCrm\Api\Dto\PipelineItemInput;
use TillioCrm\Api\Dto\WriteResult;
use TillioCrm\Api\Page;

/**
 * Szanse sprzedaży (pozycje lejków). Lejki i ich etapy:
 * `Dictionaries::pipelineFunnels()`.
 */
final readonly class PipelineItems extends Resource
{
    /**
     * `GET /v2/pipeline/items` - strona listy.
     *
     * Filtry (komplet wg kontraktu): `contractorId`, `contactId` (szanse
     * z tym kontaktem przypiętym), `pipelineStageId`,
     * `pipelineStatusId` (etapy i statusy z lejków: `dictionaries()->pipelineFunnels()`),
     * `ownerUserId`, `name`, `id`, `creatorUserId`, `closerUserId`, `currency`,
     * `probability`, `note`, `externalId`, `leadId`, `updatedAfter`/`updatedBefore`,
     * `createdAfter`/`createdBefore`, `customField[klucz]`, `sort`/`sortDir`,
     * `page`/`limit`.
     *
     * `contactId` wymaga API >= 2.15.0 - starsza instancja odrzuci nieznany
     * parametr błędem 400.
     *
     * @param array<string, mixed> $filters
     *
     * @return Page<PipelineItem>
     */
    public function list(array $filters = []): Page
    {
        return self::mapPage($this->client->get('v2/pipeline/items', $filters), PipelineItem::fromArray(...));
    }

    /**
     * Pełny przebieg wszystkich stron (generator, wymuszone `sort=id`).
     *
     * @param array<string, mixed> $filters
     *
     * @return \Generator<int, PipelineItem>
     */
    public function iterate(array $filters = [], int $pageSize = 1000): \Generator
    {
        return $this->iterateMapped('v2/pipeline/items', PipelineItem::fromArray(...), $filters, $pageSize);
    }

    /**
     * `GET /v2/pipeline/items/{id}` - pojedyncza szansa.
     */
    public function get(int $id): PipelineItem
    {
        return PipelineItem::fromArray(self::single($this->client->get('v2/pipeline/items/' . $id)));
    }

    /**
     * `POST /v2/pipeline/items` - nowa szansa. Wymagane `name`,
     * `pipelineStageId` i `contractorId`.
     *
     * @param PipelineItemInput|array<string, mixed> $input
     */
    public function create(PipelineItemInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->post('v2/pipeline/items', self::payload($input)), 'pipelineItemId');
    }

    /**
     * `PUT /v2/pipeline/items/{id}` - aktualizacja pól podanych w input.
     * `contactIds` (od API 2.15.0) to tu KOMPLETNA lista docelowa: zastępuje
     * dotychczasową, a `[]` odpina wszystkie kontakty.
     *
     * Etapu ani statusu ten zapis nie przyjmuje (422 `body.fieldNotUpdatable`) -
     * od nich są {@see changeStage()} i {@see changeStatus()}.
     *
     * @param PipelineItemInput|array<string, mixed> $input
     */
    public function update(int $id, PipelineItemInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->put('v2/pipeline/items/' . $id, self::payload($input)), 'pipelineItemId');
    }

    /**
     * `POST /v2/pipeline/items/{id}/stage` - przesunięcie szansy na inny etap
     * (od API 2.15.0). Osobna trasa, bo CRM prowadzi historię etapów (czas na
     * każdym), dobiera prawdopodobieństwo z nowego etapu, gdy nie było zmienione
     * ręcznie, i przy zmianie lejka przepina przypisania pól niestandardowych.
     * Etap z INNEGO lejka jest dozwolony - szansa przechodzi do tego lejka.
     *
     * Odmowy: etap spoza `dictionaries()->pipelineFunnels()` = 422; lejek etapu
     * docelowego zamknięty widocznością (`acl`) dla użytkownika klucza = 403;
     * etap wymagający pól, których szansa nie ma = 422 `body.requiredFieldsMissing`
     * z listą brakujących pól (te niestandardowe jako `customField[<klucz>]`).
     * Szansa już zamknięta wymaga uprawnienia `salesPipeline.canEditClosed`.
     *
     * Odpowiedź niesie szansę po zmianie w `WriteResult::$data`;
     * `warnings.ownerStageGroupAccess` ostrzega, że właściciel straci do niej
     * dostęp w nowym lejku.
     */
    public function changeStage(int $id, int $pipelineStageId): WriteResult
    {
        return WriteResult::fromResponse(
            $this->client->post(sprintf('v2/pipeline/items/%d/stage', $id), ['pipelineStageId' => $pipelineStageId]),
            'pipelineItemId',
        );
    }

    /**
     * `POST /v2/pipeline/items/{id}/status` - zamknięcie albo ponowne otwarcie
     * szansy (od API 2.15.0): `1` = aktywna (ponowne otwarcie), `2` = stracona,
     * `3` = wygrana. Osobna trasa, bo przy zamknięciu CRM zapisuje datę
     * faktycznego zakończenia (`realCloseDate`), osobę zamykającą
     * (`closerUserId`), powód i notatkę, a przy ponownym otwarciu datę zeruje.
     *
     * `statusChangeReasonId`
     * (`dictionaries()->pipelineStatusChangeReasons($pipelineStatusId, $funnelId)`)
     * i `note` (do 255 znaków) przyjmują WYŁĄCZNIE statusy 2 i 3; powód musi
     * należeć do tego statusu i do lejka szansy albo być wspólny. Powód lub
     * notatka przy ponownym otwarciu = 422, tak samo powód z `noteRequired`
     * bez `note`.
     */
    public function changeStatus(int $id, int $pipelineStatusId, ?int $statusChangeReasonId = null, ?string $note = null): WriteResult
    {
        $payload = Cast::withoutNulls([
            'pipelineStatusId' => $pipelineStatusId,
            'statusChangeReasonId' => $statusChangeReasonId,
            'note' => $note,
        ]);

        return WriteResult::fromResponse(
            $this->client->post(sprintf('v2/pipeline/items/%d/status', $id), $payload),
            'pipelineItemId',
        );
    }
}
