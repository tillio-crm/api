<?php

declare(strict_types=1);

namespace TillioCrm\Api;

/**
 * KOMPLETNA mapa tras API v2 (230 tras, kontrakt 2.11.0) na metody SDK -
 * kręgosłup gwarancji
 * pokrycia 100% tras. Test `RouteCoverageTest` pilnuje, żeby każdy wpis
 * wskazywał istniejącą, publiczną metodę, a po pobraniu specyfikacji instancji
 * (`GET /v2/openapi.json`) porównuje mapę 1:1 z kontraktem - nowa trasa w API
 * albo literówka w ścieżce wychodzi jako czerwony test, a nie jako dziura
 * w SDK odkryta przez klienta.
 *
 * Klucz: `METODA /v2/ścieżka` dokładnie jak w openapi (z `{parametrami}`).
 * Wartość: w pełni kwalifikowana metoda SDK `Klasa::metoda`.
 */
final class RouteMap
{
    private const string CLIENT = TillioClient::class;
    private const string R = 'TillioCrm\\Api\\Resources\\';

    /** @var array<string, string> trasa openapi => metoda SDK */
    public const array MAP = [
        // --- Systemowe (fasada TillioClient) --------------------------------------
        'GET /v2/health' => self::CLIENT . '::health',
        'GET /v2/whoami' => self::CLIENT . '::whoami',
        'GET /v2/selfcheck' => self::CLIENT . '::selfcheck',
        'GET /v2/modules' => self::CLIENT . '::modules',

        // --- Kontrahenci + adresy (adres to LISTA adresów z typeId) ---------------
        'GET /v2/contractors' => self::R . 'Contractors::list',
        'POST /v2/contractors' => self::R . 'Contractors::create',
        'GET /v2/contractors/{id}' => self::R . 'Contractors::get',
        'PUT /v2/contractors/{id}' => self::R . 'Contractors::update',
        'POST /v2/contractors/upsert' => self::R . 'Contractors::upsert',
        'GET /v2/contractors/{id}/addresses' => self::R . 'Contractors::addresses',
        'POST /v2/contractors/{id}/addresses' => self::R . 'Contractors::addAddress',
        'PUT /v2/addresses/{id}' => self::R . 'Contractors::updateAddress',
        'DELETE /v2/addresses/{id}' => self::R . 'Contractors::deleteAddress',

        // --- Kontakty --------------------------------------------------------------
        'GET /v2/contacts' => self::R . 'Contacts::list',
        'POST /v2/contacts' => self::R . 'Contacts::create',
        'GET /v2/contacts/{id}' => self::R . 'Contacts::get',
        'PUT /v2/contacts/{id}' => self::R . 'Contacts::update',
        'POST /v2/contacts/upsert' => self::R . 'Contacts::upsert',

        // --- Pola niestandardowe (definicje + provisioning) ------------------------
        'GET /v2/{entity}/custom-fields' => self::R . 'CustomFields::list',
        'POST /v2/custom-fields' => self::R . 'CustomFields::create',
        'PUT /v2/{entity}/custom-fields/{key}' => self::R . 'CustomFields::update',
        // Pliki pól typu FILE (API >= 2.6.0) - patrz RouteCoverageTest::AHEAD_OF_CONTRACT.
        'GET /v2/{entity}/{id}/custom-fields/{key}/file' => self::R . 'CustomFields::getFieldFile',
        'POST /v2/{entity}/{id}/custom-fields/{key}/file' => self::R . 'CustomFields::uploadFieldFile',
        'DELETE /v2/{entity}/{id}/custom-fields/{key}/file' => self::R . 'CustomFields::deleteFieldFile',

        // --- Produkty, grupy, magazyny, stany, zamówienia --------------------------
        'GET /v2/products' => self::R . 'Products::list',
        'POST /v2/products' => self::R . 'Products::create',
        'GET /v2/products/{id}' => self::R . 'Products::get',
        'PUT /v2/products/{id}' => self::R . 'Products::update',
        'GET /v2/product/groups' => self::R . 'ProductGroups::list',
        'POST /v2/product/groups' => self::R . 'ProductGroups::create',
        'GET /v2/product/groups/{id}' => self::R . 'ProductGroups::get',
        'PUT /v2/product/groups/{id}' => self::R . 'ProductGroups::update',
        'GET /v2/warehouses' => self::R . 'Warehouses::list',
        'POST /v2/warehouses' => self::R . 'Warehouses::create',
        'GET /v2/warehouses/{id}' => self::R . 'Warehouses::get',
        'PUT /v2/warehouses/{id}' => self::R . 'Warehouses::update',
        'GET /v2/stocks' => self::R . 'Stocks::list',
        'PUT /v2/warehouses/{warehouseId}/stocks/{productId}' => self::R . 'Stocks::update',
        'GET /v2/orders' => self::R . 'Orders::list',
        'GET /v2/orders/{id}' => self::R . 'Orders::get',
        'PUT /v2/orders/{id}' => self::R . 'Orders::update',
        'POST /v2/contractors/{contractorId}/orders' => self::R . 'Orders::create',

        // --- Notatki + załączniki (upload multipart) -------------------------------
        'GET /v2/notes' => self::R . 'Notes::list',
        'GET /v2/notes/{id}' => self::R . 'Notes::get',
        'PUT /v2/notes/{id}' => self::R . 'Notes::update',
        'POST /v2/contractors/{contractorId}/notes' => self::R . 'Notes::create',
        'GET /v2/notes/{id}/attachments' => self::R . 'Notes::attachments',
        'POST /v2/notes/{id}/attachments' => self::R . 'Notes::addAttachment',
        'POST /v2/note/templates' => self::R . 'Notes::createTemplate',
        'GET /v2/note/template/categories' => self::R . 'Notes::templateCategories',
        'POST /v2/note/template/categories' => self::R . 'Notes::createTemplateCategory',
        'PUT /v2/note/template/categories/{id}' => self::R . 'Notes::updateTemplateCategory',
        // Kontakty przy notatce (>= 2.8.0), notatka pod kontaktem (>= 2.10.0).
        'GET /v2/notes/{id}/contacts' => self::R . 'Notes::contacts',
        'POST /v2/notes/{id}/contacts' => self::R . 'Notes::addContact',
        'DELETE /v2/notes/{id}/contacts/{contactId}' => self::R . 'Notes::removeContact',
        'POST /v2/contacts/{contactId}/notes' => self::R . 'Contacts::createNote',

        // --- Zgłoszenia ------------------------------------------------------------
        'GET /v2/tickets' => self::R . 'Tickets::list',
        'POST /v2/tickets' => self::R . 'Tickets::create',
        'GET /v2/tickets/{id}' => self::R . 'Tickets::get',
        'PUT /v2/tickets/{id}' => self::R . 'Tickets::update',
        'GET /v2/tickets/{id}/messages' => self::R . 'Tickets::messages',
        'POST /v2/tickets/{id}/messages' => self::R . 'Tickets::addMessage',

        // --- Leady -----------------------------------------------------------------
        'GET /v2/leads' => self::R . 'Leads::list',
        'POST /v2/leads' => self::R . 'Leads::create',
        'GET /v2/leads/{id}' => self::R . 'Leads::get',
        'PUT /v2/leads/{id}' => self::R . 'Leads::update',

        // --- Zadania + komentarze + załączniki (upload multipart) ------------------
        'GET /v2/tasks' => self::R . 'Tasks::list',
        'POST /v2/tasks' => self::R . 'Tasks::create',
        'GET /v2/tasks/{id}' => self::R . 'Tasks::get',
        'PUT /v2/tasks/{id}' => self::R . 'Tasks::update',
        'GET /v2/tasks/{id}/comments' => self::R . 'Tasks::comments',
        'POST /v2/tasks/{id}/comments' => self::R . 'Tasks::addComment',
        'PUT /v2/tasks/{id}/comments/{commentId}' => self::R . 'Tasks::updateComment',
        'GET /v2/tasks/{id}/attachments' => self::R . 'Tasks::attachments',
        'POST /v2/tasks/{id}/attachments' => self::R . 'Tasks::addAttachment',
        'POST /v2/task/templates' => self::R . 'Tasks::createTemplate',
        'GET /v2/task/template/categories' => self::R . 'Tasks::templateCategories',
        'POST /v2/task/template/categories' => self::R . 'Tasks::createTemplateCategory',
        'PUT /v2/task/template/categories/{id}' => self::R . 'Tasks::updateTemplateCategory',

        // --- Projekty --------------------------------------------------------------
        'GET /v2/projects' => self::R . 'Projects::list',
        'POST /v2/projects' => self::R . 'Projects::create',
        'GET /v2/projects/{id}' => self::R . 'Projects::get',
        'PUT /v2/projects/{id}' => self::R . 'Projects::update',

        // --- Szanse sprzedaży (pipeline) -------------------------------------------
        'GET /v2/pipeline/items' => self::R . 'PipelineItems::list',
        'POST /v2/pipeline/items' => self::R . 'PipelineItems::create',
        'GET /v2/pipeline/items/{id}' => self::R . 'PipelineItems::get',
        'PUT /v2/pipeline/items/{id}' => self::R . 'PipelineItems::update',

        // --- Usługi + katalog usług ------------------------------------------------
        'GET /v2/services' => self::R . 'Services::list',
        'POST /v2/services' => self::R . 'Services::create',
        'GET /v2/services/{id}' => self::R . 'Services::get',
        'PUT /v2/services/{id}' => self::R . 'Services::update',
        'GET /v2/service/catalog' => self::R . 'ServiceCatalog::list',
        'POST /v2/service/catalog' => self::R . 'ServiceCatalog::create',
        'PUT /v2/service/catalog/{id}' => self::R . 'ServiceCatalog::update',
        'GET /v2/service/catalog/groups' => self::R . 'ServiceCatalog::groups',
        'POST /v2/service/catalog/groups' => self::R . 'ServiceCatalog::createGroup',
        'PUT /v2/service/catalog/groups/{id}' => self::R . 'ServiceCatalog::updateGroup',

        // --- Użytkownicy (POST nieponawialny: hasło startowe jednorazowe) ----------
        'GET /v2/users' => self::R . 'Users::list',
        'POST /v2/users' => self::R . 'Users::create',

        // --- DMS (upload multipart, download podpisanym URL-em) --------------------
        'GET /v2/contractors/{contractorId}/dms' => self::R . 'Dms::listing',
        'POST /v2/contractors/{contractorId}/dms/directories' => self::R . 'Dms::createDirectory',
        'POST /v2/contractors/{contractorId}/dms/documents' => self::R . 'Dms::uploadDocument',
        'GET /v2/dms/documents/{publicId}' => self::R . 'Dms::getDocument',
        'PUT /v2/dms/documents/{publicId}' => self::R . 'Dms::renameDocument',

        // --- Generator dokumentów --------------------------------------------------
        'GET /v2/document/types' => self::R . 'GeneratedDocuments::types',
        'GET /v2/document/types/{id}/form' => self::R . 'GeneratedDocuments::typeForm',
        'POST /v2/document/types' => self::R . 'GeneratedDocuments::createType',
        'POST /v2/document/types/{id}/source' => self::R . 'GeneratedDocuments::uploadTypeSource',
        'PUT /v2/document/types/{id}/form' => self::R . 'GeneratedDocuments::updateTypeForm',
        'POST /v2/document/types/{id}/activate' => self::R . 'GeneratedDocuments::activateType',
        'GET /v2/document/categories' => self::R . 'GeneratedDocuments::categories',
        'POST /v2/document/categories' => self::R . 'GeneratedDocuments::createCategory',
        'PUT /v2/document/categories/{id}' => self::R . 'GeneratedDocuments::updateCategory',
        'GET /v2/document/numerations' => self::R . 'GeneratedDocuments::numerations',
        'GET /v2/contractors/{contractorId}/documents' => self::R . 'GeneratedDocuments::list',
        'POST /v2/contractors/{contractorId}/documents' => self::R . 'GeneratedDocuments::create',
        'GET /v2/documents/{id}' => self::R . 'GeneratedDocuments::get',
        'POST /v2/documents/{id}/regenerate' => self::R . 'GeneratedDocuments::regenerate',

        // --- Poczta ----------------------------------------------------------------
        'GET /v2/mail/accounts' => self::R . 'Mail::accounts',
        'GET /v2/mail/templates' => self::R . 'Mail::templates',
        'GET /v2/mail/templates/{id}' => self::R . 'Mail::getTemplate',
        'POST /v2/mail/templates' => self::R . 'Mail::createTemplate',
        'GET /v2/mail/template/categories' => self::R . 'Mail::templateCategories',
        'POST /v2/mail/template/categories' => self::R . 'Mail::createTemplateCategory',
        'PUT /v2/mail/template/categories/{id}' => self::R . 'Mail::updateTemplateCategory',
        // Załączniki szablonów maili (>= 2.7.0).
        'GET /v2/mail/templates/{id}/attachments' => self::R . 'Mail::templateAttachments',
        'POST /v2/mail/templates/{id}/attachments' => self::R . 'Mail::addTemplateAttachment',
        'DELETE /v2/mail/templates/{id}/attachments/{attachmentId}' => self::R . 'Mail::deleteTemplateAttachment',
        'POST /v2/mail/send' => self::R . 'Mail::send',

        // --- Wiki ------------------------------------------------------------------
        'GET /v2/wiki/bases' => self::R . 'Wiki::bases',
        'POST /v2/wiki/bases' => self::R . 'Wiki::createBase',
        'PUT /v2/wiki/bases/{id}' => self::R . 'Wiki::updateBase',
        'GET /v2/wiki/bases/{id}/categories' => self::R . 'Wiki::categories',
        'POST /v2/wiki/bases/{id}/categories' => self::R . 'Wiki::createCategory',
        'PUT /v2/wiki/categories/{id}' => self::R . 'Wiki::updateCategory',
        'GET /v2/wiki/entries' => self::R . 'Wiki::entries',
        'POST /v2/wiki/entries' => self::R . 'Wiki::createEntry',
        'GET /v2/wiki/entries/{id}' => self::R . 'Wiki::getEntry',
        'PUT /v2/wiki/entries/{id}' => self::R . 'Wiki::updateEntry',
        'DELETE /v2/wiki/entries/{id}' => self::R . 'Wiki::deleteEntry',

        // --- Kalendarze (>= 2.2.0; wydarzenia >= 2.3.0) ----------------------------
        'GET /v2/calendars' => self::R . 'Calendars::list',
        'GET /v2/calendars/{id}' => self::R . 'Calendars::get',
        'GET /v2/calendars/{id}/events' => self::R . 'Calendars::events',
        'POST /v2/calendars/{id}/events' => self::R . 'Calendars::createEvent',
        'GET /v2/calendar/types' => self::R . 'Dictionaries::calendarTypes',

        // --- Telefonia: połączenia, SMS, lookup (>= 2.10.0), integracja (>= 2.11.0) -
        'GET /v2/phone-calls' => self::R . 'PhoneCalls::list',
        'GET /v2/phone-calls/{id}' => self::R . 'PhoneCalls::get',
        'POST /v2/phone-calls' => self::R . 'PhoneCalls::create',
        'PUT /v2/phone-calls/{id}' => self::R . 'PhoneCalls::update',
        'GET /v2/text-messages' => self::R . 'TextMessages::list',
        'GET /v2/text-messages/{id}' => self::R . 'TextMessages::get',
        'POST /v2/text-messages' => self::R . 'TextMessages::create',
        'PUT /v2/text-messages/{id}' => self::R . 'TextMessages::update',
        'GET /v2/lookup/phone' => self::R . 'Lookup::phone',
        'GET /v2/integrations/tillio-calls' => self::R . 'Integrations::tillioCalls',
        'PUT /v2/integrations/tillio-calls' => self::R . 'Integrations::registerTillioCalls',
        'DELETE /v2/integrations/tillio-calls' => self::R . 'Integrations::deleteTillioCalls',

        // --- Słowniki (z kolorami; lejki/procesy z zagnieżdżonymi etapami) ---------
        'GET /v2/contractor/types' => self::R . 'Dictionaries::contractorTypes',
        'GET /v2/contractor/statuses' => self::R . 'Dictionaries::contractorStatuses',
        'POST /v2/contractor/statuses' => self::R . 'Dictionaries::createContractorStatus',
        'PUT /v2/contractor/statuses/{id}' => self::R . 'Dictionaries::updateContractorStatus',
        'GET /v2/contractor/sources' => self::R . 'Dictionaries::contractorSources',
        'POST /v2/contractor/sources' => self::R . 'Dictionaries::createContractorSource',
        'PUT /v2/contractor/sources/{id}' => self::R . 'Dictionaries::updateContractorSource',
        'GET /v2/contractor/priorities' => self::R . 'Dictionaries::contractorPriorities',
        'POST /v2/contractor/priorities' => self::R . 'Dictionaries::createContractorPriority',
        'PUT /v2/contractor/priorities/{id}' => self::R . 'Dictionaries::updateContractorPriority',
        'GET /v2/contractor/industries' => self::R . 'Dictionaries::contractorIndustries',
        'POST /v2/contractor/industries' => self::R . 'Dictionaries::createContractorIndustry',
        'PUT /v2/contractor/industries/{id}' => self::R . 'Dictionaries::updateContractorIndustry',
        'GET /v2/contractor/legal-forms' => self::R . 'Dictionaries::contractorLegalForms',
        'POST /v2/contractor/legal-forms' => self::R . 'Dictionaries::createContractorLegalForm',
        'PUT /v2/contractor/legal-forms/{id}' => self::R . 'Dictionaries::updateContractorLegalForm',
        'GET /v2/contractor/payment-types' => self::R . 'Dictionaries::contractorPaymentTypes',
        'POST /v2/contractor/payment-types' => self::R . 'Dictionaries::createContractorPaymentType',
        'PUT /v2/contractor/payment-types/{id}' => self::R . 'Dictionaries::updateContractorPaymentType',
        'GET /v2/address/types' => self::R . 'Dictionaries::addressTypes',
        'POST /v2/address/types' => self::R . 'Dictionaries::createAddressType',
        'PUT /v2/address/types/{id}' => self::R . 'Dictionaries::updateAddressType',
        'GET /v2/note/types' => self::R . 'Dictionaries::noteTypes',
        'POST /v2/note/types' => self::R . 'Dictionaries::createNoteType',
        'PUT /v2/note/types/{id}' => self::R . 'Dictionaries::updateNoteType',
        'GET /v2/order/statuses' => self::R . 'Dictionaries::orderStatuses',
        'POST /v2/order/statuses' => self::R . 'Dictionaries::createOrderStatus',
        'PUT /v2/order/statuses/{id}' => self::R . 'Dictionaries::updateOrderStatus',
        'GET /v2/project/statuses' => self::R . 'Dictionaries::projectStatuses',
        'POST /v2/project/statuses' => self::R . 'Dictionaries::createProjectStatus',
        'PUT /v2/project/statuses/{id}' => self::R . 'Dictionaries::updateProjectStatus',
        'GET /v2/user/statuses' => self::R . 'Dictionaries::userStatuses',
        'GET /v2/ticket/statuses' => self::R . 'Dictionaries::ticketStatuses',
        'POST /v2/ticket/statuses' => self::R . 'Dictionaries::createTicketStatus',
        'PUT /v2/ticket/statuses/{id}' => self::R . 'Dictionaries::updateTicketStatus',
        'GET /v2/ticket/sources' => self::R . 'Dictionaries::ticketSources',
        'GET /v2/ticket/processes' => self::R . 'Dictionaries::ticketProcesses',
        'POST /v2/ticket/processes' => self::R . 'Dictionaries::createTicketProcess',
        'PUT /v2/ticket/processes/{id}' => self::R . 'Dictionaries::updateTicketProcess',
        'POST /v2/ticket/processes/{id}/stages' => self::R . 'Dictionaries::createTicketStage',
        'PUT /v2/ticket/stages/{id}' => self::R . 'Dictionaries::updateTicketStage',
        'GET /v2/task/statuses' => self::R . 'Dictionaries::taskStatuses',
        'POST /v2/task/statuses' => self::R . 'Dictionaries::createTaskStatus',
        'PUT /v2/task/statuses/{id}' => self::R . 'Dictionaries::updateTaskStatus',
        'GET /v2/task/tags' => self::R . 'Dictionaries::taskTags',
        'POST /v2/task/tags' => self::R . 'Dictionaries::createTaskTag',
        'PUT /v2/task/tags/{id}' => self::R . 'Dictionaries::updateTaskTag',
        'GET /v2/user/roles' => self::R . 'Dictionaries::userRoles',
        'POST /v2/user/roles' => self::R . 'Dictionaries::createUserRole',
        'PUT /v2/user/roles/{id}' => self::R . 'Dictionaries::updateUserRole',
        'GET /v2/user/permissions' => self::R . 'Dictionaries::userPermissions',
        'GET /v2/user/departments' => self::R . 'Dictionaries::userDepartments',
        'POST /v2/user/departments' => self::R . 'Dictionaries::createUserDepartment',
        'PUT /v2/user/departments/{id}' => self::R . 'Dictionaries::updateUserDepartment',
        'GET /v2/service/statuses' => self::R . 'Dictionaries::serviceStatuses',
        'GET /v2/service/billing-periods' => self::R . 'Dictionaries::serviceBillingPeriods',
        'GET /v2/service/payment-terms' => self::R . 'Dictionaries::servicePaymentTerms',
        'POST /v2/service/payment-terms' => self::R . 'Dictionaries::createServicePaymentTerm',
        'PUT /v2/service/payment-terms/{id}' => self::R . 'Dictionaries::updateServicePaymentTerm',
        'GET /v2/service/invoice-types' => self::R . 'Dictionaries::serviceInvoiceTypes',
        'POST /v2/service/invoice-types' => self::R . 'Dictionaries::createServiceInvoiceType',
        'PUT /v2/service/invoice-types/{id}' => self::R . 'Dictionaries::updateServiceInvoiceType',
        'GET /v2/pipeline/funnels' => self::R . 'Dictionaries::pipelineFunnels',
        'POST /v2/pipeline/funnels' => self::R . 'Dictionaries::createPipelineFunnel',
        'PUT /v2/pipeline/funnels/{id}' => self::R . 'Dictionaries::updatePipelineFunnel',
        'POST /v2/pipeline/funnels/{id}/stages' => self::R . 'Dictionaries::createPipelineStage',
        'PUT /v2/pipeline/stages/{id}' => self::R . 'Dictionaries::updatePipelineStage',
        'GET /v2/lead/statuses' => self::R . 'Dictionaries::leadProcesses',
        'PUT /v2/lead/statuses/{id}' => self::R . 'Dictionaries::updateLeadStatus',
        'POST /v2/lead/processes' => self::R . 'Dictionaries::createLeadProcess',
        'PUT /v2/lead/processes/{id}' => self::R . 'Dictionaries::updateLeadProcess',
        'POST /v2/lead/processes/{id}/statuses' => self::R . 'Dictionaries::createLeadStatus',
        'GET /v2/currencies' => self::R . 'Dictionaries::currencies',
    ];
}
