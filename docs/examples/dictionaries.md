# Słowniki

Mapy `id → nazwa` (z kolorami) dla wszystkich pól `*Id` API. Proste słowniki
zwracają `list<DictionaryEntry>`; procesy zgłoszeń, lejki i procesy leadowe
mają struktury zagnieżdżone.

```php
use TillioCrm\Api\Dto\DictionaryEntryInput;
use TillioCrm\Api\Dto\PipelineFunnelInput;
use TillioCrm\Api\Dto\ProcessStageInput;

$d = $client->dictionaries();

// --- Proste słowniki (odczyt + zapis tym samym wzorcem) ------------------------
$d->contractorTypes();        // typy kontrahentów (tylko odczyt)
$d->contractorStatuses();     // + createContractorStatus / updateContractorStatus
$d->contractorSources();      //   zapis analogicznie parami create*/update*
$d->contractorPriorities();   //   dla każdego słownika z zapisem
$d->contractorIndustries();
$d->contractorLegalForms();
$d->contractorPaymentTypes();
$d->addressTypes();           // typy adresów (1 = podstawowy)
$d->noteTypes();
$d->orderStatuses();
$d->projectStatuses();
$d->taskStatuses();
$d->taskTags();               // tag bez '#' - CRM doda sam
$d->ticketStatuses();
$d->ticketSources();          // tylko odczyt
$d->userStatuses();           // tylko odczyt
$d->userDepartments();
$d->serviceStatuses();        // tylko odczyt
$d->serviceBillingPeriods();  // tylko odczyt
$d->servicePaymentTerms();    // zapis polem days (0-365), nie name
$d->serviceInvoiceTypes();
$d->calendarTypes();          // rodzaje kalendarzy: Tillio, Microsoft, Google
                              // (tylko odczyt; wymaga API >= 2.2.0)
$d->currencies();             // list<string>: ['PLN', 'EUR', ...]

$d->createContractorStatus(new DictionaryEntryInput(name: 'Kluczowy', color: '#e57373'));
$d->createServicePaymentTerm(new DictionaryEntryInput(days: 14, isDefault: true));

// --- Role i uprawnienia --------------------------------------------------------
foreach ($d->userRoles() as $role) {
    $role->name;
    $role->permissions;       // klucze, np. 'contractor.canEdit'
}
$d->userPermissions();        // pełny słownik uprawnień do budowy ról
$d->createUserRole(['name' => 'Handlowiec zewnętrzny', 'permissions' => ['contractor.canEdit']]);

// --- Procesy zgłoszeń (z etapami i SLA) ----------------------------------------
foreach ($d->ticketProcesses() as $process) {
    $process->resolutionTimeMinutes;      // SLA (0 = brak)
    foreach ($process->stages as $stage) {
        $stage->name;
    }
}
$d->createTicketStage(3, new ProcessStageInput(name: 'Weryfikacja', color: '#2196f3'));

// --- Lejki sprzedaży (etapy z probability) -------------------------------------
foreach ($d->pipelineFunnels() as $funnel) {
    foreach ($funnel->stages as $stage) {
        $stage->probability;
    }
}
$d->createPipelineFunnel(new PipelineFunnelInput(
    name: 'Lejek partnerski',
    stages: [new ProcessStageInput(name: 'Kontakt', probability: 10)],
));

// --- Procesy leadowe (statusy z type: default|qualified|disqualified) ----------
foreach ($d->leadProcesses() as $process) {
    foreach ($process->statuses as $status) {
        $status->type;
    }
}
```
