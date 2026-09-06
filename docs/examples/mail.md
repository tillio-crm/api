# Poczta

```php
use TillioCrm\Api\Dto\CategoryInput;
use TillioCrm\Api\Dto\MailSendInput;
use TillioCrm\Api\Dto\MailTemplateInput;
use TillioCrm\Api\Transport\FileUpload;

// Konta dostępne do wysyłki
$accounts = $client->mail()->accounts();

// Szablony: lista niesie metadane, pełną treść zwraca getTemplate($id)
$templates = $client->mail()->templates();
$template = $client->mail()->getTemplate($templates[0]->id);
$template->body;    // HTML z placeholderami {nazwa}

// Wysyłka wprost (konto przez accountId albo account = adres e-mail)
$client->mail()->send(new MailSendInput(
    accountId: $accounts[0]->id,
    to: ['odbiorca@przyklad.example'],
    subject: 'Oferta',
    body: '<p>Dzień dobry...</p>',
    includeFooter: true,
));

// Wysyłka z szablonu + zmienne na placeholdery
$client->mail()->send(new MailSendInput(
    accountId: $accounts[0]->id,
    to: ['odbiorca@przyklad.example'],
    templateId: $templates[0]->id,
    variables: ['companyName' => 'Przykładowa Firma'],
));

// Wysyłka z załącznikami: SDK sam przełącza na multipart (payload jako JSON
// w polu formularza, pliki w attachments[], max 50 MB/plik) i WYŁĄCZA retry -
// powtórka po timeoutcie, który doszedł, to drugi mail u odbiorcy.
$client->mail()->send(
    new MailSendInput(accountId: $accounts[0]->id, to: ['odbiorca@przyklad.example'], subject: 'Oferta', body: 'W załączniku.'),
    [FileUpload::fromPath('/sciezka/oferta.pdf')],
);

// Wysyłka odroczona
$client->mail()->send(new MailSendInput(
    accountId: $accounts[0]->id,
    to: ['odbiorca@przyklad.example'],
    subject: 'Przypomnienie',
    body: 'Termin mija jutro.',
    sendAt: '2026-09-01T08:00:00+02:00',
));

// --- Tworzenie szablonów (wymaga API >= 2.4.0) ---------------------------------
// Kategorie szablonów: płaska lista, drzewo składa się po parentId
$categories = $client->mail()->templateCategories();
$category = $client->mail()->createTemplateCategory(new CategoryInput(name: 'Oferty'));

// Nowy szablon (name i subject wymagane); alias max 31 znaków,
// CRM znormalizuje go do formy !maly_snake
$template = $client->mail()->createTemplate(new MailTemplateInput(
    name: 'Oferta standardowa',
    subject: 'Oferta dla {companyName}',
    body: '<p>Dzień dobry, w załączeniu oferta dla {companyName}.</p>',
    categoryId: $category->id,
    alias: 'oferta_standardowa',
    cc: ['biuro@przyklad.example'],    // domyślna kopia
));
```
