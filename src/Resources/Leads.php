<?php

declare(strict_types=1);

namespace TillioCrm\Api\Resources;

use TillioCrm\Api\Dto\Cast;
use TillioCrm\Api\Dto\Lead;
use TillioCrm\Api\Dto\LeadInput;
use TillioCrm\Api\Dto\NoteInput;
use TillioCrm\Api\Dto\UpsertResult;
use TillioCrm\Api\Dto\WriteOptions;
use TillioCrm\Api\Dto\WriteResult;
use TillioCrm\Api\Page;

/**
 * Leady. Od API 2.13.0 zapis działa jak u kontaktów: przed założeniem API
 * sprawdza, czy lead już istnieje, i przy trafieniu PODPINA dane do
 * istniejącego (200 w `create()`, status `attached` w `upsert()`).
 */
final readonly class Leads extends Resource
{
    /**
     * Lead nie ma pola `email` - warunek `email` z duplicateCheck API sprawdza
     * po liście `emails` z żądania, więc strażnik musi zajrzeć tam samo.
     */
    private const array DUPLICATE_CHECK_VALUES = ['email' => 'emails'];

    /**
     * `GET /v2/leads` - strona listy.
     *
     * Filtry (komplet wg kontraktu): `leadStatusId`, `leadStageId` (statusy i
     * etapy z procesów leadowych: `dictionaries()->leadProcesses()`), `ownerUserId`,
     * `title`, `taxId`, `id`, `statusChangeReasonId`, `categoryId`
     * (`dictionaries()->leadCategories()`), `leadTagId` (jeden tag na żądanie,
     * `dictionaries()->leadTags()`), `contractorSourceId`
     * (`dictionaries()->contractorSources()`), `priority`,
     * `creatorUserId`, `contractorId`, `contactId`, `salesPipelineId`, `companyName`,
     * `regon`, `domain`, `firstName`, `lastName`, `position`, `phone`,
     * `phoneAlternative`, `street`, `postCode`, `city`, `region`, `district`,
     * `country`, `updatedAfter`/`updatedBefore`, `createdAfter`/`createdBefore`,
     * `customField[klucz]`, `sort`/`sortDir`, `page`/`limit`.
     *
     * `leadTagId` i `district` wymagają API >= 2.15.0 - starsza instancja
     * odrzuci nieznany parametr błędem 400.
     *
     * @param array<string, mixed> $filters
     *
     * @return Page<Lead>
     */
    public function list(array $filters = []): Page
    {
        return self::mapPage($this->client->get('v2/leads', $filters), Lead::fromArray(...));
    }

    /**
     * Pełny przebieg wszystkich stron (generator, wymuszone `sort=id`).
     *
     * @param array<string, mixed> $filters
     *
     * @return \Generator<int, Lead>
     */
    public function iterate(array $filters = [], int $pageSize = 1000): \Generator
    {
        return $this->iterateMapped('v2/leads', Lead::fromArray(...), $filters, $pageSize);
    }

    /**
     * `GET /v2/leads/{id}` - pojedynczy lead (trasa nie przyjmuje parametrów).
     */
    public function get(int $id): Lead
    {
        return Lead::fromArray(self::single($this->client->get('v2/leads/' . $id)));
    }

    /**
     * `POST /v2/leads` - nowy lead (201) albo podpięcie danych do istniejącego
     * (200). Wymagane `title`.
     *
     * Od API 2.13.0 API samo szuka istniejącego leada - domyślnie po `email`
     * (którykolwiek adres z `emails`) i `phone`; `duplicateCheck` zmienia pola
     * i ich priorytet (`email`, `phone`, `taxId`, `domain`, `companyName`,
     * `custom:<klucz>`). Trafienie nadpisuje tylko `customField`: puste pola są
     * uzupełniane, adresy z `emails` dokładane, tagi z `leadTagIds` DOKŁADANE
     * (od API 2.15.0 - istniejące zostają), telefon trafia w wolny numer,
     * a pola tylko do tworzenia (`leadStatusId`, `createdAt`, `creatorUserId`)
     * wracają w `warnings`. Wynik: `created === false`,
     * `matchedBy()` i `id` istniejącego leada; gdy ten lead jest już skonwertowany,
     * `duplicate->raw['contractorId']` wskazuje kontrahenta - to już klient.
     *
     * Zawsze nowy lead: `new WriteOptions(allowDuplicates: true)`. Lead bez e-maila
     * i telefonu powstaje jak dotąd (API tylko ostrzega w `warnings`), chyba że
     * `requireDuplicateCheck: true` - wtedy KAŻDE pole listy, także domyślnej,
     * musi mieć wartość. Instancja starsza niż 2.13.0 odrzuci opcje zapisu błędem
     * walidacji.
     *
     * @param LeadInput|array<string, mixed>    $input
     * @param WriteOptions|array<string, mixed> $options
     *
     * @throws \TillioCrm\Api\Exception\IncompleteDuplicateCheckException zanim żądanie
     *                                                                    wyjdzie - pole z duplicateCheck bez wartości w payloadzie
     */
    public function create(LeadInput|array $input, WriteOptions|array $options = []): WriteResult
    {
        $payload = self::payload($input) + self::payload($options);
        self::assertDuplicateCheckUsable($payload, self::DUPLICATE_CHECK_VALUES);

        return WriteResult::fromResponse($this->client->post('v2/leads', $payload), 'leadId');
    }

    /**
     * `PUT /v2/leads/{id}` - aktualizacja pól podanych w input. `emails`
     * (od API 2.13.0) i `leadTagIds` (od API 2.15.0) to tu KOMPLETNE listy
     * docelowe: zastępują dotychczasowe, a `[]` czyści je do zera - inaczej niż
     * podpięcie w `create()`, które adresy i tagi dokłada.
     *
     * Zdjęcie kategorii wymaga jawnego nulla, więc tablicy zamiast DTO:
     * `update($id, ['categoryId' => null])` - w `LeadInput` null znaczy
     * "nie wysyłaj pola".
     *
     * `leadStatusId` tu nie przechodzi (422 `body.fieldNotUpdatable`) - status
     * zmienia {@see changeStatus()}.
     *
     * @param LeadInput|array<string, mixed> $input
     */
    public function update(int $id, LeadInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->put('v2/leads/' . $id, self::payload($input)), 'leadId');
    }

    /**
     * `POST /v2/leads/upsert` - paczka do 100 leadów (od API 2.13.0). HTTP
     * zawsze 200; statusy per item: `created|attached|failed` (attached = dane
     * podpięte do istniejącego leada wg reguł `create()`, przy skonwertowanym
     * leadzie wynik niesie też `contractorId`). `duplicateCheck`
     * i `requireDuplicateCheck` obowiązują całą paczkę; `allowDuplicates` nie ma
     * tu zastosowania - API odrzuca taką kopertę błędem 422.
     *
     * @param list<LeadInput|array<string, mixed>> $items
     * @param WriteOptions|array<string, mixed>    $options
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
            self::assertDuplicateCheckUsable($row + $opts, self::DUPLICATE_CHECK_VALUES);
            $rows[] = $row;
        }

        return UpsertResult::fromResponse($this->client->post('v2/leads/upsert', ['items' => $rows] + $opts));
    }

    /**
     * `POST /v2/leads/{id}/status` - zmiana statusu leada (od API 2.15.0).
     * W CRM to proces z historią, nie zwykły zapis pola, dlatego osobna trasa:
     * CRM dopisuje wpis do historii statusów, ustawia `closedAt` i grupę
     * statusów (`leadStageId`). Status z innego procesu leadowego też przejdzie -
     * lead trafia do jego grupy.
     *
     * `statusChangeReasonId` (`dictionaries()->leadStatusChangeReasons($leadStatusId)`)
     * i `note` (do 255 znaków) przyjmują WYŁĄCZNIE statusy kończące (`type` =
     * `qualified`/`disqualified` w `dictionaries()->leadProcesses()`); przy statusie
     * `default` oba dają 422. Powód z `noteRequired` bez `note` to też 422.
     *
     * Odpowiedź niesie leada po zmianie: `WriteResult::$data` (zmapujesz przez
     * `Lead::fromArray()`) i `WriteResult::$warnings`.
     */
    public function changeStatus(int $id, int $leadStatusId, ?int $statusChangeReasonId = null, ?string $note = null): WriteResult
    {
        $payload = Cast::withoutNulls([
            'leadStatusId' => $leadStatusId,
            'statusChangeReasonId' => $statusChangeReasonId,
            'note' => $note,
        ]);

        return WriteResult::fromResponse($this->client->post(sprintf('v2/leads/%d/status', $id), $payload), 'leadId');
    }

    /**
     * `POST /v2/leads/{leadId}/notes` - notatka pod leadem (od API 2.13.0).
     * Wymagane `noteTypeId` i `title`. Notatka należy do leada: `contractorId`
     * zostaje null także przy leadzie z kontrahentem, bo konwersja leada sama
     * przepina jego notatki. `contactIds`, `serviceId` i `pipelineItemId` nie są
     * tu obsługiwane (422). Wymaga uprawnień do notatek i do edycji leadów.
     *
     * @param NoteInput|array<string, mixed> $input
     */
    public function createNote(int $leadId, NoteInput|array $input): WriteResult
    {
        return WriteResult::fromResponse(
            $this->client->post(sprintf('v2/leads/%d/notes', $leadId), self::payload($input)),
            'noteId',
        );
    }
}
