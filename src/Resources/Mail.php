<?php

declare(strict_types=1);

namespace TillioCrm\Api\Resources;

use TillioCrm\Api\Dto\CategoryInput;
use TillioCrm\Api\Dto\MailAccount;
use TillioCrm\Api\Dto\MailSendInput;
use TillioCrm\Api\Dto\MailTemplate;
use TillioCrm\Api\Dto\MailTemplateAttachment;
use TillioCrm\Api\Dto\MailTemplateInput;
use TillioCrm\Api\Dto\TemplateCategory;
use TillioCrm\Api\Dto\WriteResult;
use TillioCrm\Api\Exception\TransportException;
use TillioCrm\Api\Transport\FileUpload;

/**
 * Wysyłka maili z CRM: konta, szablony (z placeholderami), stopki, załączniki.
 */
final readonly class Mail extends Resource
{
    /**
     * `GET /v2/mail/accounts` - konta pocztowe dostępne do wysyłki.
     *
     * @return list<MailAccount>
     */
    public function accounts(): array
    {
        return self::mapList($this->client->get('v2/mail/accounts'), MailAccount::fromArray(...));
    }

    /**
     * `GET /v2/mail/templates` - lista szablonów (metadane, bez treści;
     * lista bez stronicowania, trasa nie przyjmuje parametrów).
     *
     * @return list<MailTemplate>
     */
    public function templates(): array
    {
        return self::mapList($this->client->get('v2/mail/templates'), MailTemplate::fromArray(...));
    }

    /**
     * `GET /v2/mail/templates/{id}` - pełny szablon z treścią HTML
     * (placeholdery `{nazwa}`) i adresatami.
     */
    public function getTemplate(int $id): MailTemplate
    {
        return MailTemplate::fromArray(self::single($this->client->get('v2/mail/templates/' . $id)));
    }

    /**
     * `GET /v2/mail/templates/{id}/attachments` - załączniki szablonu
     * (z linkami do pobrania). Wymaga API >= 2.7.0.
     *
     * @return list<MailTemplateAttachment>
     */
    public function templateAttachments(int $templateId): array
    {
        return self::mapList(
            $this->client->get(sprintf('v2/mail/templates/%d/attachments', $templateId)),
            MailTemplateAttachment::fromArray(...),
        );
    }

    /**
     * `POST /v2/mail/templates/{id}/attachments` - dopina plik do szablonu
     * (do 10 MB, multipart, bez retry). Wymaga API >= 2.7.0.
     */
    public function addTemplateAttachment(int $templateId, FileUpload $file): MailTemplateAttachment
    {
        return MailTemplateAttachment::fromArray(self::single(
            $this->client->postMultipart(sprintf('v2/mail/templates/%d/attachments', $templateId), [], ['file' => $file]),
        ));
    }

    /**
     * `DELETE /v2/mail/templates/{templateId}/attachments/{attachmentId}` -
     * usuwa załącznik szablonu. Wymaga API >= 2.7.0.
     */
    public function deleteTemplateAttachment(int $templateId, string $attachmentId): void
    {
        $this->client->delete(sprintf(
            'v2/mail/templates/%d/attachments/%s',
            $templateId,
            rawurlencode($attachmentId),
        ));
    }

    /**
     * `POST /v2/mail/templates` - nowy szablon maila (wymagane `name`
     * i `subject`). Wymaga API >= 2.4.0.
     *
     * @param MailTemplateInput|array<string, mixed> $input
     */
    public function createTemplate(MailTemplateInput|array $input): MailTemplate
    {
        return MailTemplate::fromArray(self::single(
            $this->client->post('v2/mail/templates', self::payload($input)),
        ));
    }

    /**
     * `GET /v2/mail/template/categories` - kategorie szablonów maili
     * (płaska lista, drzewo po `parentId`). Wymaga API >= 2.4.0.
     *
     * @return list<TemplateCategory>
     */
    public function templateCategories(): array
    {
        return self::mapList($this->client->get('v2/mail/template/categories'), TemplateCategory::fromArray(...));
    }

    /**
     * `POST /v2/mail/template/categories` - nowa kategoria szablonów
     * (wymagane `name`). Wymaga API >= 2.4.0.
     *
     * @param CategoryInput|array<string, mixed> $input
     */
    public function createTemplateCategory(CategoryInput|array $input): TemplateCategory
    {
        return TemplateCategory::fromArray(self::single(
            $this->client->post('v2/mail/template/categories', self::payload($input)),
        ));
    }

    /**
     * `PUT /v2/mail/template/categories/{id}` - edycja nazwy, rodzica
     * i priorytetu kategorii. Wymaga API >= 2.4.0.
     *
     * @param CategoryInput|array<string, mixed> $input
     */
    public function updateTemplateCategory(int $id, CategoryInput|array $input): TemplateCategory
    {
        return TemplateCategory::fromArray(self::single(
            $this->client->put('v2/mail/template/categories/' . $id, self::payload($input)),
        ));
    }

    /**
     * `POST /v2/mail/send` - wysyłka wiadomości.
     *
     * DWA WARIANTY TRANSPORTU, wybierane automatycznie:
     *  - bez załączników: zwykły JSON,
     *  - z załącznikami: `multipart/form-data` - cały payload wysyłki jedzie
     *    jako JSON w polu formularza `payload`, pliki w `attachments[]`
     *    (max 50 MB/plik). Wysyłka z plikami jest BEZ RETRY (powtórka po
     *    timeoutcie, który doszedł, to drugi mail u odbiorcy).
     *
     *     $client->mail()->send(
     *         new MailSendInput(accountId: 5, to: ['jan@acme.pl'], subject: 'Oferta', body: '<p>...</p>'),
     *         [FileUpload::fromPath('C:/oferta.pdf')],
     *     );
     *
     * @param MailSendInput|array<string, mixed> $input
     * @param list<FileUpload>                   $attachments pliki do załączenia
     *
     * @throws TransportException gdy payloadu wysyłki nie da się zakodować do JSON
     *                            (rzucane lokalnie, zanim żądanie wyjdzie)
     */
    public function send(MailSendInput|array $input, array $attachments = []): WriteResult
    {
        $payload = self::payload($input);

        if ($attachments === []) {
            return WriteResult::fromResponse($this->client->post('v2/mail/send', $payload), 'messageId');
        }

        // Wariant multipart: payload jako JSON w polu `payload`, pliki w `attachments[]`.
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new TransportException('Nie udało się zakodować payloadu wysyłki do JSON: ' . json_last_error_msg());
        }

        $files = [];
        foreach (array_values($attachments) as $index => $file) {
            $files[sprintf('attachments[%d]', $index)] = $file;
        }

        return WriteResult::fromResponse(
            $this->client->postMultipart('v2/mail/send', ['payload' => $encoded], $files),
            'messageId',
        );
    }
}
