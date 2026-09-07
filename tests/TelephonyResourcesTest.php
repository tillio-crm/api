<?php

declare(strict_types=1);

namespace TillioCrm\Api\Tests;

use PHPUnit\Framework\TestCase;
use TillioCrm\Api\Dto\Contact;
use TillioCrm\Api\Dto\Contractor;
use TillioCrm\Api\Dto\PhoneCall;
use TillioCrm\Api\Dto\PhoneCallInput;
use TillioCrm\Api\Dto\PhoneLookupResult;
use TillioCrm\Api\Dto\TextMessage;
use TillioCrm\Api\Dto\TextMessageInput;
use TillioCrm\Api\Dto\TillioCallsIntegration;
use TillioCrm\Api\Tests\Support\FakeClock;
use TillioCrm\Api\Tests\Support\MockTransport;
use TillioCrm\Api\TillioClient;

/**
 * Telefonia (API >= 2.10.0): połączenia, SMS-y, wyszukanie po numerze oraz
 * integracja Tillio Calls (API >= 2.11.0). Ścieżki, query, payloady i mapowanie DTO.
 */
final class TelephonyResourcesTest extends TestCase
{
    private MockTransport $transport;

    protected function setUp(): void
    {
        $this->transport = new MockTransport();
    }

    private function client(): TillioClient
    {
        return new TillioClient([
            'apiKey' => 'Test:klucz',
            'tenantDomain' => 'firma.tillio.app',
            'tenantId' => 'firma-abc123',
            'rateLimits' => [],
            'transport' => $this->transport,
            'clock' => new FakeClock(),
        ]);
    }

    public function testPhoneCallsListMapsPageAndSendsQuery(): void
    {
        $this->transport->queueJson(200, '{"data":[{"id":5,"source":"tillio","sourceId":"CALL-1","direction":"inbound","status":"answered","contactId":3,"contractorIds":["7","8"],"noteIds":[11,12]}],"pagination":{"page":1,"limit":25,"total":1,"pages":1}}');

        $page = $this->client()->phoneCalls()->list(['contractorId' => 7]);

        $request = $this->transport->lastRequest();
        self::assertSame('GET', $request->method);
        self::assertSame('v2/phone-calls', $request->path);
        self::assertSame(['contractorId' => '7'], $request->query);

        $call = $page->first();
        self::assertInstanceOf(PhoneCall::class, $call);
        self::assertSame(5, $call->id);
        // id encji jest int, ale sourceId to string (identyfikator z systemu VoIP).
        self::assertSame('CALL-1', $call->sourceId);
        // Listy id znoszą stringi z API (kanon list<int>).
        self::assertSame([7, 8], $call->contractorIds);
        self::assertSame([11, 12], $call->noteIds);
    }

    public function testPhoneCallGet(): void
    {
        $this->transport->queueJson(200, '{"data":{"id":5,"source":"tillio","sourceId":"CALL-1","direction":"outbound","status":"missed"}}');

        $call = $this->client()->phoneCalls()->get(5);

        self::assertSame('GET', $this->transport->lastRequest()->method);
        self::assertSame('v2/phone-calls/5', $this->transport->lastRequest()->path);
        self::assertSame(5, $call->id);
        self::assertSame('outbound', $call->direction);
    }

    public function testPhoneCallCreateSendsPayloadAndReturnsObject(): void
    {
        $this->transport->queueJson(201, '{"data":{"id":9,"source":"tillio","sourceId":"CALL-9","direction":"inbound","status":"answered","remoteNumber":"+48601234567","duration":42}}');

        $call = $this->client()->phoneCalls()->create(new PhoneCallInput(
            source: 'tillio',
            sourceId: 'CALL-9',
            direction: 'inbound',
            status: 'answered',
            remoteNumber: '+48601234567',
            startedAt: '2026-09-01T10:00:00+02:00',
            duration: 42,
        ));

        $request = $this->transport->lastRequest();
        self::assertSame('POST', $request->method);
        self::assertSame('v2/phone-calls', $request->path);
        self::assertSame([
            'source' => 'tillio',
            'sourceId' => 'CALL-9',
            'direction' => 'inbound',
            'status' => 'answered',
            'remoteNumber' => '+48601234567',
            'startedAt' => '2026-09-01T10:00:00+02:00',
            'duration' => 42,
        ], $request->body);
        self::assertInstanceOf(PhoneCall::class, $call);
        self::assertSame(9, $call->id);
        self::assertSame(42, $call->duration);
    }

    public function testPhoneCallUpdate(): void
    {
        $this->transport->queueJson(200, '{"data":{"id":5,"source":"tillio","sourceId":"CALL-1","status":"answered","duration":60}}');

        $call = $this->client()->phoneCalls()->update(5, new PhoneCallInput(status: 'answered', duration: 60));

        $request = $this->transport->lastRequest();
        self::assertSame('PUT', $request->method);
        self::assertSame('v2/phone-calls/5', $request->path);
        self::assertSame(['status' => 'answered', 'duration' => 60], $request->body);
        self::assertInstanceOf(PhoneCall::class, $call);
        self::assertSame(60, $call->duration);
    }

    public function testTextMessagesListMapsPageAndSendsQuery(): void
    {
        $this->transport->queueJson(200, '{"data":[{"id":3,"source":"smsapi","sourceId":"SMS-1","direction":"inbound","status":"received","body":"Dzień dobry","contractorIds":["7"],"noteIds":["21"]}],"pagination":{"page":1,"limit":25,"total":1,"pages":1}}');

        $page = $this->client()->textMessages()->list(['contractorId' => 7]);

        $request = $this->transport->lastRequest();
        self::assertSame('GET', $request->method);
        self::assertSame('v2/text-messages', $request->path);
        self::assertSame(['contractorId' => '7'], $request->query);

        $message = $page->first();
        self::assertInstanceOf(TextMessage::class, $message);
        self::assertSame(3, $message->id);
        self::assertSame('SMS-1', $message->sourceId);
        self::assertSame([7], $message->contractorIds);
        self::assertSame([21], $message->noteIds);
    }

    public function testTextMessageGet(): void
    {
        $this->transport->queueJson(200, '{"data":{"id":3,"source":"smsapi","sourceId":"SMS-1","direction":"outbound","status":"sent","body":"Do usłyszenia"}}');

        $message = $this->client()->textMessages()->get(3);

        self::assertSame('v2/text-messages/3', $this->transport->lastRequest()->path);
        self::assertSame(3, $message->id);
        self::assertSame('Do usłyszenia', $message->body);
    }

    public function testTextMessageCreateSendsPayloadAndReturnsObject(): void
    {
        $this->transport->queueJson(201, '{"data":{"id":8,"source":"smsapi","sourceId":"SMS-8","direction":"outbound","status":"sent","remoteNumber":"+48601234567","body":"Potwierdzamy termin"}}');

        $message = $this->client()->textMessages()->create(new TextMessageInput(
            source: 'smsapi',
            sourceId: 'SMS-8',
            direction: 'outbound',
            status: 'sent',
            remoteNumber: '+48601234567',
            body: 'Potwierdzamy termin',
            sentAt: '2026-09-01T10:00:00+02:00',
        ));

        $request = $this->transport->lastRequest();
        self::assertSame('POST', $request->method);
        self::assertSame('v2/text-messages', $request->path);
        self::assertSame([
            'source' => 'smsapi',
            'sourceId' => 'SMS-8',
            'direction' => 'outbound',
            'status' => 'sent',
            'remoteNumber' => '+48601234567',
            'body' => 'Potwierdzamy termin',
            'sentAt' => '2026-09-01T10:00:00+02:00',
        ], $request->body);
        self::assertInstanceOf(TextMessage::class, $message);
        self::assertSame(8, $message->id);
    }

    public function testTextMessageUpdate(): void
    {
        $this->transport->queueJson(200, '{"data":{"id":8,"source":"smsapi","sourceId":"SMS-8","status":"delivered"}}');

        $message = $this->client()->textMessages()->update(8, new TextMessageInput(status: 'delivered'));

        $request = $this->transport->lastRequest();
        self::assertSame('PUT', $request->method);
        self::assertSame('v2/text-messages/8', $request->path);
        self::assertSame(['status' => 'delivered'], $request->body);
        self::assertSame('delivered', $message->status);
    }

    public function testLookupPhoneSendsNumberQueryAndMapsResult(): void
    {
        $this->transport->queueJson(200, '{"data":{"number":"+48601234567","contacts":[{"id":3,"firstName":"Jan","lastName":"Kowalski","phone":"+48601234567","contractorIds":["7"]}],"contractors":[{"id":7,"name":"Acme","phone":"+48601234567"}]}}');

        $result = $this->client()->lookup()->phone('+48601234567');

        $request = $this->transport->lastRequest();
        self::assertSame('GET', $request->method);
        self::assertSame('v2/lookup/phone', $request->path);
        self::assertSame(['number' => '+48601234567'], $request->query);

        self::assertInstanceOf(PhoneLookupResult::class, $result);
        self::assertSame('+48601234567', $result->number);
        self::assertCount(1, $result->contacts);
        self::assertSame(3, $result->contacts[0]->id);
        self::assertSame('Jan', $result->contacts[0]->firstName);
        self::assertSame([7], $result->contacts[0]->contractorIds);
        self::assertCount(1, $result->contractors);
        self::assertSame(7, $result->contractors[0]->id);
        self::assertSame('Acme', $result->contractors[0]->name);
    }

    /**
     * Od API 2.12.0 lookup oddaje pełne rekordy kontaktu i kontrahenta, więc
     * mapujemy je na te same DTO co reszta SDK - identyfikacja dzwoniącego ma
     * komplet danych bez dopytywania o kartotekę.
     */
    public function testLookupPhoneMapsFullContactAndContractorRecords(): void
    {
        $this->transport->queueJson(200, '{"data":{"number":"+48601234567","contacts":[{"id":3,"firstName":"Jan","lastName":"Kowalski","name":"Jan Kowalski","position":"Prezes","email":"jan@acme.pl","phone":"+48601234567","contactStatusId":1,"ownerUserId":12,"contractorId":7,"contractorIds":["7"],"url":"https://firma.tillio.app/crm/contractors/7/#/modal=contact-read/contactId:3","customField":{"contact_text_1":"VIP"},"active":true}],"contractors":[{"id":7,"name":"Acme","phone":"+48601234567","taxId":"1234567890","url":"https://firma.tillio.app/crm/contractors/7"}]}}');

        $result = $this->client()->lookup()->phone('+48601234567');

        $contact = $result->contacts[0];
        self::assertInstanceOf(Contact::class, $contact);
        self::assertSame('jan@acme.pl', $contact->email);
        self::assertSame('Prezes', $contact->position);
        self::assertSame(12, $contact->ownerUserId);
        self::assertSame(['contact_text_1' => 'VIP'], $contact->customField);
        self::assertSame('https://firma.tillio.app/crm/contractors/7/#/modal=contact-read/contactId:3', $contact->url);
        // `active` jest tylko w odpowiedzi lookupu, nie w kontrakcie kontaktu - zostaje w raw.
        self::assertTrue($contact->raw['active']);

        $contractor = $result->contractors[0];
        self::assertInstanceOf(Contractor::class, $contractor);
        self::assertSame('1234567890', $contractor->taxId);
        self::assertSame('https://firma.tillio.app/crm/contractors/7', $contractor->url);
    }

    public function testTillioCallsReadDoesNotExposeApiKey(): void
    {
        $this->transport->queueJson(200, '{"data":{"registered":true,"apiUrl":"https://calls.example","hasApiKey":true,"providerId":4,"configId":9}}');

        $integration = $this->client()->integrations()->tillioCalls();

        $request = $this->transport->lastRequest();
        self::assertSame('GET', $request->method);
        self::assertSame('v2/integrations/tillio-calls', $request->path);

        self::assertInstanceOf(TillioCallsIntegration::class, $integration);
        self::assertTrue($integration->registered);
        self::assertSame('https://calls.example', $integration->apiUrl);
        // Klucz nigdy nie wraca - jest tylko flaga.
        self::assertTrue($integration->hasApiKey);
        self::assertArrayNotHasKey('apiKey', $integration->raw);
    }

    public function testRegisterTillioCallsPutsBothFields(): void
    {
        $this->transport->queueJson(200, '{"data":{"registered":true,"apiUrl":"https://calls.example","hasApiKey":true}}');

        $integration = $this->client()->integrations()->registerTillioCalls('https://calls.example', 'k');

        $request = $this->transport->lastRequest();
        self::assertSame('PUT', $request->method);
        self::assertSame('v2/integrations/tillio-calls', $request->path);
        self::assertSame(['apiUrl' => 'https://calls.example', 'apiKey' => 'k'], $request->body);
        self::assertTrue($integration->hasApiKey);
    }

    public function testDeleteTillioCallsSendsDeleteAndTakesEmptyBody(): void
    {
        // 204 bez treści - klient nie może się zakrztusić pustym body.
        $this->transport->queueJson(204, '');

        $this->client()->integrations()->deleteTillioCalls();

        $request = $this->transport->lastRequest();
        self::assertSame('DELETE', $request->method);
        self::assertSame('v2/integrations/tillio-calls', $request->path);
    }
}
