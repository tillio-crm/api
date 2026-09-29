<?php

declare(strict_types=1);

namespace TillioCrm\Api\Resources;

use TillioCrm\Api\Dto\Cast;
use TillioCrm\Api\Dto\NoteInput;
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
     *     // Paczka znanych szans z kontrahentami - jedno zapytanie zamiast GET na każdą.
     *     $page = $client->pipelineItems()->list(['id' => [6, 7, 9], 'include' => 'contractor']);
     *
     * Filtry (komplet wg kontraktu): `contractorId`, `contactId` (szanse
     * z tym kontaktem przypiętym), `pipelineStageId`,
     * `pipelineStatusId` (etapy i statusy z lejków: `dictionaries()->pipelineFunnels()`),
     * `pipelineFunnelId` (lejek), `ownerUserId`, `name`, `id`, `creatorUserId`,
     * `closerUserId`, `currency`, `probability`, `note`, `externalId`, `leadId`,
     * `updatedAfter`/`updatedBefore`, `createdAfter`/`createdBefore`,
     * `customField[klucz]`, `include=contractor` (kontrahent szansy osadzony
     * w `PipelineItem::$contractor`, dla całej strony jednym zapytaniem),
     * `sort`/`sortDir`, `page`/`limit`.
     *
     * Filtry po liczbie całkowitej (`id`, `contractorId`, `leadId`, `ownerUserId`,
     * `pipelineFunnelId`...) przyjmują od API 2.17.0 listę do 100 wartości:
     * `['id' => [6, 7, 9]]`. Id nieobecne w odpowiedzi = szansy już nie ma
     * (CRM kasuje szanse bez śladu, a szansa ze statusem "Usunięta" też znika
     * z listy) - to jedyny sposób na wykrycie usunięcia.
     *
     * `contactId` wymaga API >= 2.15.0, `pipelineFunnelId`, `include` i listy
     * wartości - API >= 2.17.0. Starsza instancja odrzuci nieznany parametr albo
     * listę błędem 400.
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
     * Pełny przebieg wszystkich stron (generator, wymuszone `sort=id`). Filtry
     * jak w {@see list()}, także `include=contractor` - kontrahenci dociągani
     * są wtedy jednym zapytaniem na stronę.
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
     *
     *     $item = $client->pipelineItems()->get(6, include: ['contractor']);
     *     $item->contractor?->taxId;
     *
     * @param list<string> $include dane powiązane (API >= 2.17.0): `contractor` -
     *                              kontrahent szansy w `PipelineItem::$contractor`.
     *                              Pusta lista = bez parametru (jak dotąd); nieznana
     *                              wartość albo starsza instancja = 400
     */
    public function get(int $id, array $include = []): PipelineItem
    {
        return PipelineItem::fromArray(self::single($this->client->get(
            'v2/pipeline/items/' . $id,
            $include === [] ? [] : ['include' => $include],
        )));
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

    /**
     * `POST /v2/pipeline/items/{pipelineItemId}/notes` - notatka pod szansą bez
     * podawania kontrahenta (API >= 2.17.0): API bierze go z szansy. Wynik jest
     * taki sam jak `notes()->create($contractorId, ...)` z `pipelineItemId` -
     * notatka wisi na kontrahencie szansy (`contractorId`) i jest przypięta do
     * szansy (w odczycie `Note::$pipelineId`); odczyt: `notes()->list(['pipelineId' => $id])`.
     *
     *     $client->pipelineItems()->createNote(6, new NoteInput(noteTypeId: 1, title: 'Konflikt blokady'));
     *
     * Wymagane `noteTypeId` i `title`. `contactIds` i `serviceId` muszą należeć
     * do kontrahenta szansy (inaczej 422 na tym polu); `pipelineItemId`
     * w `NoteInput` to 422 - szansę wskazuje ścieżka. Szansa nieistniejąca albo
     * usunięta = `NotFoundException` (404 `pipelineItem.notFound`). Wymaga
     * uprawnień do notatek (i do kontaktów przy `contactIds`).
     *
     * @param NoteInput|array<string, mixed> $input
     */
    public function createNote(int $pipelineItemId, NoteInput|array $input): WriteResult
    {
        return WriteResult::fromResponse(
            $this->client->post(sprintf('v2/pipeline/items/%d/notes', $pipelineItemId), self::payload($input)),
            'noteId',
        );
    }
}
