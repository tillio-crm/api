<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Wysyłka wiadomości (`POST /v2/mail/send`). Konto wskazuje `accountId` ALBO
 * `account` (adres e-mail). Treść wprost (`subject` + `body`) albo z szablonu
 * (`templateId` + `variables` na placeholdery `{nazwa}`).
 *
 * Załączniki NIE są polem tego DTO - podaje się je osobnym parametrem
 * `Mail::send()` (z plikami żądanie jedzie jako multipart).
 */
final readonly class MailSendInput implements Arrayable
{
    /**
     * @param list<string>|null         $to            adresaci
     * @param list<string>|null         $cc            kopia
     * @param list<string>|null         $bcc           ukryta kopia
     * @param array<string, mixed>|null $variables     wartości placeholderów szablonu
     * @param bool|null                 $includeFooter dołącz stopkę
     * @param int|null                  $footerUserId  stopka tego użytkownika
     * @param string|null               $sendAt        wysyłka odroczona (ISO 8601)
     */
    public function __construct(
        public ?int $accountId = null,
        public ?string $account = null,
        public ?array $to = null,
        public ?array $cc = null,
        public ?array $bcc = null,
        public ?string $subject = null,
        public ?string $body = null,
        public ?int $templateId = null,
        public ?array $variables = null,
        public ?bool $includeFooter = null,
        public ?int $footerUserId = null,
        public ?string $sendAt = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'accountId' => $this->accountId,
            'account' => $this->account,
            'to' => $this->to,
            'cc' => $this->cc,
            'bcc' => $this->bcc,
            'subject' => $this->subject,
            'body' => $this->body,
            'templateId' => $this->templateId,
            'variables' => $this->variables,
            'includeFooter' => $this->includeFooter,
            'footerUserId' => $this->footerUserId,
            'sendAt' => $this->sendAt,
        ]);
    }
}
