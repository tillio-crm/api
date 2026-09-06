<?php

declare(strict_types=1);

namespace TillioCrm\Api\Resources;

use TillioCrm\Api\Dto\Cast;
use TillioCrm\Api\Dto\DictionaryEntry;
use TillioCrm\Api\Dto\DictionaryEntryInput;
use TillioCrm\Api\Dto\LeadProcess;
use TillioCrm\Api\Dto\LeadProcessInput;
use TillioCrm\Api\Dto\Permission;
use TillioCrm\Api\Dto\PipelineFunnel;
use TillioCrm\Api\Dto\PipelineFunnelInput;
use TillioCrm\Api\Dto\ProcessStageInput;
use TillioCrm\Api\Dto\TicketProcess;
use TillioCrm\Api\Dto\TicketProcessInput;
use TillioCrm\Api\Dto\UserRole;
use TillioCrm\Api\Dto\UserRoleInput;
use TillioCrm\Api\Dto\WriteResult;

/**
 * Słowniki systemowe - mapy `id → nazwa` (z kolorami) dla wszystkich pól `*Id`
 * API v2, plus struktury złożone: procesy zgłoszeń, lejki sprzedaży i procesy
 * leadowe z zagnieżdżonymi etapami.
 *
 * Proste słowniki zwracają listy BEZ stronicowania ({@see DictionaryEntry});
 * zapisy przyjmują {@see DictionaryEntryInput} (jeden typ, pola zależne od
 * słownika - patrz jego opis).
 */
final readonly class Dictionaries extends Resource
{
    /**
     * Lista wpisów prostego słownika.
     *
     * @return list<DictionaryEntry>
     */
    private function entries(string $path): array
    {
        return self::mapList($this->client->get($path), DictionaryEntry::fromArray(...));
    }

    /**
     * Dodanie wpisu do prostego słownika.
     *
     * @param DictionaryEntryInput|array<string, mixed> $input
     */
    private function createEntry(string $path, DictionaryEntryInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->post($path, self::payload($input)), 'id');
    }

    /**
     * Aktualizacja wpisu prostego słownika.
     *
     * @param DictionaryEntryInput|array<string, mixed> $input
     */
    private function updateEntry(string $path, int $id, DictionaryEntryInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->put($path . '/' . $id, self::payload($input)), 'id');
    }

    // --- Kontrahenci ---------------------------------------------------------------

    /**
     * `GET /v2/contractor/types` - typy kontrahentów (tylko odczyt).
     *
     * @return list<DictionaryEntry>
     */
    public function contractorTypes(): array
    {
        return $this->entries('v2/contractor/types');
    }

    /**
     * `GET /v2/contractor/statuses` - statusy kontrahentów.
     *
     * @return list<DictionaryEntry>
     */
    public function contractorStatuses(): array
    {
        return $this->entries('v2/contractor/statuses');
    }

    /**
     * `POST /v2/contractor/statuses` - nowy status kontrahenta (wymagane `name`).
     *
     * @param DictionaryEntryInput|array<string, mixed> $input
     */
    public function createContractorStatus(DictionaryEntryInput|array $input): WriteResult
    {
        return $this->createEntry('v2/contractor/statuses', $input);
    }

    /**
     * `PUT /v2/contractor/statuses/{id}` - aktualizacja statusu kontrahenta.
     *
     * @param DictionaryEntryInput|array<string, mixed> $input
     */
    public function updateContractorStatus(int $id, DictionaryEntryInput|array $input): WriteResult
    {
        return $this->updateEntry('v2/contractor/statuses', $id, $input);
    }

    /**
     * `GET /v2/contractor/sources` - źródła pozyskania.
     *
     * @return list<DictionaryEntry>
     */
    public function contractorSources(): array
    {
        return $this->entries('v2/contractor/sources');
    }

    /**
     * `POST /v2/contractor/sources` - nowe źródło pozyskania.
     *
     * @param DictionaryEntryInput|array<string, mixed> $input
     */
    public function createContractorSource(DictionaryEntryInput|array $input): WriteResult
    {
        return $this->createEntry('v2/contractor/sources', $input);
    }

    /**
     * `PUT /v2/contractor/sources/{id}` - aktualizacja źródła pozyskania.
     *
     * @param DictionaryEntryInput|array<string, mixed> $input
     */
    public function updateContractorSource(int $id, DictionaryEntryInput|array $input): WriteResult
    {
        return $this->updateEntry('v2/contractor/sources', $id, $input);
    }

    /**
     * `GET /v2/contractor/priorities` - priorytety kontrahentów.
     *
     * @return list<DictionaryEntry>
     */
    public function contractorPriorities(): array
    {
        return $this->entries('v2/contractor/priorities');
    }

    /**
     * `POST /v2/contractor/priorities` - nowy priorytet.
     *
     * @param DictionaryEntryInput|array<string, mixed> $input
     */
    public function createContractorPriority(DictionaryEntryInput|array $input): WriteResult
    {
        return $this->createEntry('v2/contractor/priorities', $input);
    }

    /**
     * `PUT /v2/contractor/priorities/{id}` - aktualizacja priorytetu.
     *
     * @param DictionaryEntryInput|array<string, mixed> $input
     */
    public function updateContractorPriority(int $id, DictionaryEntryInput|array $input): WriteResult
    {
        return $this->updateEntry('v2/contractor/priorities', $id, $input);
    }

    /**
     * `GET /v2/contractor/industries` - branże.
     *
     * @return list<DictionaryEntry>
     */
    public function contractorIndustries(): array
    {
        return $this->entries('v2/contractor/industries');
    }

    /**
     * `POST /v2/contractor/industries` - nowa branża.
     *
     * @param DictionaryEntryInput|array<string, mixed> $input
     */
    public function createContractorIndustry(DictionaryEntryInput|array $input): WriteResult
    {
        return $this->createEntry('v2/contractor/industries', $input);
    }

    /**
     * `PUT /v2/contractor/industries/{id}` - aktualizacja branży.
     *
     * @param DictionaryEntryInput|array<string, mixed> $input
     */
    public function updateContractorIndustry(int $id, DictionaryEntryInput|array $input): WriteResult
    {
        return $this->updateEntry('v2/contractor/industries', $id, $input);
    }

    /**
     * `GET /v2/contractor/legal-forms` - formy prawne.
     *
     * @return list<DictionaryEntry>
     */
    public function contractorLegalForms(): array
    {
        return $this->entries('v2/contractor/legal-forms');
    }

    /**
     * `POST /v2/contractor/legal-forms` - nowa forma prawna.
     *
     * @param DictionaryEntryInput|array<string, mixed> $input
     */
    public function createContractorLegalForm(DictionaryEntryInput|array $input): WriteResult
    {
        return $this->createEntry('v2/contractor/legal-forms', $input);
    }

    /**
     * `PUT /v2/contractor/legal-forms/{id}` - aktualizacja formy prawnej.
     *
     * @param DictionaryEntryInput|array<string, mixed> $input
     */
    public function updateContractorLegalForm(int $id, DictionaryEntryInput|array $input): WriteResult
    {
        return $this->updateEntry('v2/contractor/legal-forms', $id, $input);
    }

    /**
     * `GET /v2/contractor/payment-types` - typy płatności.
     *
     * @return list<DictionaryEntry>
     */
    public function contractorPaymentTypes(): array
    {
        return $this->entries('v2/contractor/payment-types');
    }

    /**
     * `POST /v2/contractor/payment-types` - nowy typ płatności.
     *
     * @param DictionaryEntryInput|array<string, mixed> $input
     */
    public function createContractorPaymentType(DictionaryEntryInput|array $input): WriteResult
    {
        return $this->createEntry('v2/contractor/payment-types', $input);
    }

    /**
     * `PUT /v2/contractor/payment-types/{id}` - aktualizacja typu płatności.
     *
     * @param DictionaryEntryInput|array<string, mixed> $input
     */
    public function updateContractorPaymentType(int $id, DictionaryEntryInput|array $input): WriteResult
    {
        return $this->updateEntry('v2/contractor/payment-types', $id, $input);
    }

    // --- Adresy, notatki, zamówienia, projekty -------------------------------------

    /**
     * `GET /v2/address/types` - typy adresów (1 = podstawowy).
     *
     * @return list<DictionaryEntry>
     */
    public function addressTypes(): array
    {
        return $this->entries('v2/address/types');
    }

    /**
     * `POST /v2/address/types` - nowy typ adresu.
     *
     * @param DictionaryEntryInput|array<string, mixed> $input
     */
    public function createAddressType(DictionaryEntryInput|array $input): WriteResult
    {
        return $this->createEntry('v2/address/types', $input);
    }

    /**
     * `PUT /v2/address/types/{id}` - aktualizacja typu adresu.
     *
     * @param DictionaryEntryInput|array<string, mixed> $input
     */
    public function updateAddressType(int $id, DictionaryEntryInput|array $input): WriteResult
    {
        return $this->updateEntry('v2/address/types', $id, $input);
    }

    /**
     * `GET /v2/note/types` - typy notatek.
     *
     * @return list<DictionaryEntry>
     */
    public function noteTypes(): array
    {
        return $this->entries('v2/note/types');
    }

    /**
     * `POST /v2/note/types` - nowy typ notatki.
     *
     * @param DictionaryEntryInput|array<string, mixed> $input
     */
    public function createNoteType(DictionaryEntryInput|array $input): WriteResult
    {
        return $this->createEntry('v2/note/types', $input);
    }

    /**
     * `PUT /v2/note/types/{id}` - aktualizacja typu notatki.
     *
     * @param DictionaryEntryInput|array<string, mixed> $input
     */
    public function updateNoteType(int $id, DictionaryEntryInput|array $input): WriteResult
    {
        return $this->updateEntry('v2/note/types', $id, $input);
    }

    /**
     * `GET /v2/order/statuses` - statusy zamówień.
     *
     * @return list<DictionaryEntry>
     */
    public function orderStatuses(): array
    {
        return $this->entries('v2/order/statuses');
    }

    /**
     * `POST /v2/order/statuses` - nowy status zamówienia.
     *
     * @param DictionaryEntryInput|array<string, mixed> $input
     */
    public function createOrderStatus(DictionaryEntryInput|array $input): WriteResult
    {
        return $this->createEntry('v2/order/statuses', $input);
    }

    /**
     * `PUT /v2/order/statuses/{id}` - aktualizacja statusu zamówienia.
     *
     * @param DictionaryEntryInput|array<string, mixed> $input
     */
    public function updateOrderStatus(int $id, DictionaryEntryInput|array $input): WriteResult
    {
        return $this->updateEntry('v2/order/statuses', $id, $input);
    }

    /**
     * `GET /v2/project/statuses` - statusy projektów.
     *
     * @return list<DictionaryEntry>
     */
    public function projectStatuses(): array
    {
        return $this->entries('v2/project/statuses');
    }

    /**
     * `POST /v2/project/statuses` - nowy status projektu.
     *
     * @param DictionaryEntryInput|array<string, mixed> $input
     */
    public function createProjectStatus(DictionaryEntryInput|array $input): WriteResult
    {
        return $this->createEntry('v2/project/statuses', $input);
    }

    /**
     * `PUT /v2/project/statuses/{id}` - aktualizacja statusu projektu.
     *
     * @param DictionaryEntryInput|array<string, mixed> $input
     */
    public function updateProjectStatus(int $id, DictionaryEntryInput|array $input): WriteResult
    {
        return $this->updateEntry('v2/project/statuses', $id, $input);
    }

    // --- Zgłoszenia ----------------------------------------------------------------

    /**
     * `GET /v2/ticket/statuses` - statusy zgłoszeń.
     *
     * @return list<DictionaryEntry>
     */
    public function ticketStatuses(): array
    {
        return $this->entries('v2/ticket/statuses');
    }

    /**
     * `POST /v2/ticket/statuses` - nowy status zgłoszenia.
     *
     * @param DictionaryEntryInput|array<string, mixed> $input
     */
    public function createTicketStatus(DictionaryEntryInput|array $input): WriteResult
    {
        return $this->createEntry('v2/ticket/statuses', $input);
    }

    /**
     * `PUT /v2/ticket/statuses/{id}` - aktualizacja statusu zgłoszenia.
     *
     * @param DictionaryEntryInput|array<string, mixed> $input
     */
    public function updateTicketStatus(int $id, DictionaryEntryInput|array $input): WriteResult
    {
        return $this->updateEntry('v2/ticket/statuses', $id, $input);
    }

    /**
     * `GET /v2/ticket/sources` - źródła zgłoszeń (tylko odczyt).
     *
     * @return list<DictionaryEntry>
     */
    public function ticketSources(): array
    {
        return $this->entries('v2/ticket/sources');
    }

    /**
     * `GET /v2/ticket/processes` - procesy obsługi zgłoszeń z etapami
     * (`resolutionTimeMinutes` = SLA).
     *
     * @return list<TicketProcess>
     */
    public function ticketProcesses(): array
    {
        return self::mapList($this->client->get('v2/ticket/processes'), TicketProcess::fromArray(...));
    }

    /**
     * `POST /v2/ticket/processes` - nowy proces obsługi (wymagane `name`;
     * etapy można podać od razu).
     *
     * @param TicketProcessInput|array<string, mixed> $input
     */
    public function createTicketProcess(TicketProcessInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->post('v2/ticket/processes', self::payload($input)), 'id');
    }

    /**
     * `PUT /v2/ticket/processes/{id}` - aktualizacja procesu obsługi.
     *
     * @param TicketProcessInput|array<string, mixed> $input
     */
    public function updateTicketProcess(int $id, TicketProcessInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->put('v2/ticket/processes/' . $id, self::payload($input)), 'id');
    }

    /**
     * `POST /v2/ticket/processes/{id}/stages` - nowy etap w procesie obsługi.
     *
     * @param ProcessStageInput|array<string, mixed> $input
     */
    public function createTicketStage(int $processId, ProcessStageInput|array $input): WriteResult
    {
        return WriteResult::fromResponse(
            $this->client->post(sprintf('v2/ticket/processes/%d/stages', $processId), self::payload($input)),
            'id',
        );
    }

    /**
     * `PUT /v2/ticket/stages/{id}` - aktualizacja etapu procesu obsługi.
     *
     * @param ProcessStageInput|array<string, mixed> $input
     */
    public function updateTicketStage(int $stageId, ProcessStageInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->put('v2/ticket/stages/' . $stageId, self::payload($input)), 'id');
    }

    // --- Zadania -------------------------------------------------------------------

    /**
     * `GET /v2/task/statuses` - statusy zadań.
     *
     * @return list<DictionaryEntry>
     */
    public function taskStatuses(): array
    {
        return $this->entries('v2/task/statuses');
    }

    /**
     * `POST /v2/task/statuses` - nowy status zadania.
     *
     * @param DictionaryEntryInput|array<string, mixed> $input
     */
    public function createTaskStatus(DictionaryEntryInput|array $input): WriteResult
    {
        return $this->createEntry('v2/task/statuses', $input);
    }

    /**
     * `PUT /v2/task/statuses/{id}` - aktualizacja statusu zadania.
     *
     * @param DictionaryEntryInput|array<string, mixed> $input
     */
    public function updateTaskStatus(int $id, DictionaryEntryInput|array $input): WriteResult
    {
        return $this->updateEntry('v2/task/statuses', $id, $input);
    }

    /**
     * `GET /v2/task/tags` - tagi zadań.
     *
     * @return list<DictionaryEntry>
     */
    public function taskTags(): array
    {
        return $this->entries('v2/task/tags');
    }

    /**
     * `POST /v2/task/tags` - nowy tag (bez `#` na początku - CRM doda go sam).
     *
     * @param DictionaryEntryInput|array<string, mixed> $input
     */
    public function createTaskTag(DictionaryEntryInput|array $input): WriteResult
    {
        return $this->createEntry('v2/task/tags', $input);
    }

    /**
     * `PUT /v2/task/tags/{id}` - aktualizacja tagu.
     *
     * @param DictionaryEntryInput|array<string, mixed> $input
     */
    public function updateTaskTag(int $id, DictionaryEntryInput|array $input): WriteResult
    {
        return $this->updateEntry('v2/task/tags', $id, $input);
    }

    // --- Użytkownicy ---------------------------------------------------------------

    /**
     * `GET /v2/user/statuses` - statusy użytkowników (tylko odczyt; konta
     * NIEAKTYWNE nie liczą się do limitu licencji).
     *
     * @return list<DictionaryEntry>
     */
    public function userStatuses(): array
    {
        return $this->entries('v2/user/statuses');
    }

    /**
     * `GET /v2/user/roles` - role z listami kluczy uprawnień.
     *
     * @return list<UserRole>
     */
    public function userRoles(): array
    {
        return self::mapList($this->client->get('v2/user/roles'), UserRole::fromArray(...));
    }

    /**
     * `POST /v2/user/roles` - nowa rola (wymagane `name`, max 32 znaki).
     *
     * @param UserRoleInput|array<string, mixed> $input
     */
    public function createUserRole(UserRoleInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->post('v2/user/roles', self::payload($input)), 'id');
    }

    /**
     * `PUT /v2/user/roles/{id}` - aktualizacja roli.
     *
     * @param UserRoleInput|array<string, mixed> $input
     */
    public function updateUserRole(int $id, UserRoleInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->put('v2/user/roles/' . $id, self::payload($input)), 'id');
    }

    /**
     * `GET /v2/user/permissions` - słownik uprawnień (klucze do ról).
     *
     * @return list<Permission>
     */
    public function userPermissions(): array
    {
        return self::mapList($this->client->get('v2/user/permissions'), Permission::fromArray(...));
    }

    /**
     * `GET /v2/user/departments` - działy.
     *
     * @return list<DictionaryEntry>
     */
    public function userDepartments(): array
    {
        return $this->entries('v2/user/departments');
    }

    /**
     * `POST /v2/user/departments` - nowy dział (wymagane `name`).
     *
     * @param DictionaryEntryInput|array<string, mixed> $input
     */
    public function createUserDepartment(DictionaryEntryInput|array $input): WriteResult
    {
        return $this->createEntry('v2/user/departments', $input);
    }

    /**
     * `PUT /v2/user/departments/{id}` - aktualizacja działu.
     *
     * @param DictionaryEntryInput|array<string, mixed> $input
     */
    public function updateUserDepartment(int $id, DictionaryEntryInput|array $input): WriteResult
    {
        return $this->updateEntry('v2/user/departments', $id, $input);
    }

    // --- Usługi --------------------------------------------------------------------

    /**
     * `GET /v2/service/statuses` - statusy usług (tylko odczyt).
     *
     * @return list<DictionaryEntry>
     */
    public function serviceStatuses(): array
    {
        return $this->entries('v2/service/statuses');
    }

    /**
     * `GET /v2/service/billing-periods` - okresy rozliczeniowe (tylko odczyt).
     *
     * @return list<DictionaryEntry>
     */
    public function serviceBillingPeriods(): array
    {
        return $this->entries('v2/service/billing-periods');
    }

    /**
     * `GET /v2/service/payment-terms` - terminy płatności.
     *
     * @return list<DictionaryEntry>
     */
    public function servicePaymentTerms(): array
    {
        return $this->entries('v2/service/payment-terms');
    }

    /**
     * `POST /v2/service/payment-terms` - nowy termin (wymagane `days`, 0-365).
     *
     * @param DictionaryEntryInput|array<string, mixed> $input
     */
    public function createServicePaymentTerm(DictionaryEntryInput|array $input): WriteResult
    {
        return $this->createEntry('v2/service/payment-terms', $input);
    }

    /**
     * `PUT /v2/service/payment-terms/{id}` - aktualizacja terminu płatności.
     *
     * @param DictionaryEntryInput|array<string, mixed> $input
     */
    public function updateServicePaymentTerm(int $id, DictionaryEntryInput|array $input): WriteResult
    {
        return $this->updateEntry('v2/service/payment-terms', $id, $input);
    }

    /**
     * `GET /v2/service/invoice-types` - typy faktur usług.
     *
     * @return list<DictionaryEntry>
     */
    public function serviceInvoiceTypes(): array
    {
        return $this->entries('v2/service/invoice-types');
    }

    /**
     * `POST /v2/service/invoice-types` - nowy typ faktury.
     *
     * @param DictionaryEntryInput|array<string, mixed> $input
     */
    public function createServiceInvoiceType(DictionaryEntryInput|array $input): WriteResult
    {
        return $this->createEntry('v2/service/invoice-types', $input);
    }

    /**
     * `PUT /v2/service/invoice-types/{id}` - aktualizacja typu faktury.
     *
     * @param DictionaryEntryInput|array<string, mixed> $input
     */
    public function updateServiceInvoiceType(int $id, DictionaryEntryInput|array $input): WriteResult
    {
        return $this->updateEntry('v2/service/invoice-types', $id, $input);
    }

    // --- Lejki sprzedaży -----------------------------------------------------------

    /**
     * `GET /v2/pipeline/funnels` - lejki z etapami (`probability` per etap).
     *
     * @return list<PipelineFunnel>
     */
    public function pipelineFunnels(): array
    {
        return self::mapList($this->client->get('v2/pipeline/funnels'), PipelineFunnel::fromArray(...));
    }

    /**
     * `POST /v2/pipeline/funnels` - nowy lejek (wymagane `name`).
     *
     * @param PipelineFunnelInput|array<string, mixed> $input
     */
    public function createPipelineFunnel(PipelineFunnelInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->post('v2/pipeline/funnels', self::payload($input)), 'id');
    }

    /**
     * `PUT /v2/pipeline/funnels/{id}` - aktualizacja lejka.
     *
     * @param PipelineFunnelInput|array<string, mixed> $input
     */
    public function updatePipelineFunnel(int $id, PipelineFunnelInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->put('v2/pipeline/funnels/' . $id, self::payload($input)), 'id');
    }

    /**
     * `POST /v2/pipeline/funnels/{id}/stages` - nowy etap lejka.
     *
     * @param ProcessStageInput|array<string, mixed> $input
     */
    public function createPipelineStage(int $funnelId, ProcessStageInput|array $input): WriteResult
    {
        return WriteResult::fromResponse(
            $this->client->post(sprintf('v2/pipeline/funnels/%d/stages', $funnelId), self::payload($input)),
            'id',
        );
    }

    /**
     * `PUT /v2/pipeline/stages/{id}` - aktualizacja etapu lejka.
     *
     * @param ProcessStageInput|array<string, mixed> $input
     */
    public function updatePipelineStage(int $stageId, ProcessStageInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->put('v2/pipeline/stages/' . $stageId, self::payload($input)), 'id');
    }

    // --- Procesy leadowe -----------------------------------------------------------

    /**
     * `GET /v2/lead/statuses` - procesy leadowe z zagnieżdżonymi statusami
     * (`type`: `default|qualified|disqualified`). Trasa nazywa się po statusach,
     * ale zwraca PROCESY - stąd nazwa metody po typie wyniku.
     *
     * @return list<LeadProcess>
     */
    public function leadProcesses(): array
    {
        return self::mapList($this->client->get('v2/lead/statuses'), LeadProcess::fromArray(...));
    }

    /**
     * `POST /v2/lead/processes` - nowy proces leadowy (wymagane `name`).
     *
     * @param LeadProcessInput|array<string, mixed> $input
     */
    public function createLeadProcess(LeadProcessInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->post('v2/lead/processes', self::payload($input)), 'id');
    }

    /**
     * `PUT /v2/lead/processes/{id}` - aktualizacja procesu leadowego.
     *
     * @param LeadProcessInput|array<string, mixed> $input
     */
    public function updateLeadProcess(int $id, LeadProcessInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->put('v2/lead/processes/' . $id, self::payload($input)), 'id');
    }

    /**
     * `POST /v2/lead/processes/{id}/statuses` - nowy status w procesie leadowym.
     *
     * @param ProcessStageInput|array<string, mixed> $input
     */
    public function createLeadStatus(int $processId, ProcessStageInput|array $input): WriteResult
    {
        return WriteResult::fromResponse(
            $this->client->post(sprintf('v2/lead/processes/%d/statuses', $processId), self::payload($input)),
            'id',
        );
    }

    /**
     * `PUT /v2/lead/statuses/{id}` - aktualizacja statusu leadowego.
     *
     * @param ProcessStageInput|array<string, mixed> $input
     */
    public function updateLeadStatus(int $statusId, ProcessStageInput|array $input): WriteResult
    {
        return WriteResult::fromResponse($this->client->put('v2/lead/statuses/' . $statusId, self::payload($input)), 'id');
    }

    // --- Pozostałe -----------------------------------------------------------------

    /**
     * `GET /v2/calendar/types` - rodzaje kalendarzy (Tillio, Microsoft,
     * Google; tylko odczyt). Wymaga API >= 2.2.0.
     *
     * @return list<DictionaryEntry>
     */
    public function calendarTypes(): array
    {
        return $this->entries('v2/calendar/types');
    }

    /**
     * `GET /v2/currencies` - kody walut instancji.
     *
     * @return list<string>
     */
    public function currencies(): array
    {
        return Cast::stringList($this->client->get('v2/currencies')->data());
    }
}
