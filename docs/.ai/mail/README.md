# Playbook: Poczta (mail)

Realizacja polecen uzytkownika dotyczacych wysylki maili z CRM: wybor konta
nadawczego, tresc wprost albo z szablonu, placeholdery, zalaczniki, stopka i
wysylka odroczona. Pisane dla asystenta AI - zaklada wspolne wzorce z
[ai_integration.md](../ai_integration.md) (zwlaszcza `resolveUserId()`,
`WriteResult` i "Zlota zasade: nie zgaduj id").

## Pola

Wysylka: `MailSendInput` (`src/Dto/MailSendInput.php`). `null` = nie wysylaj
pola. Konto wskazujesz `accountId` ALBO `account` (adres). Tresc podajesz wprost
(`subject` + `body`) ALBO z szablonu (`templateId` + `variables`).

| pole | typ | po co (skad wziac id/referencje) |
|---|---|---|
| `accountId` | `?int` | konto nadawcze po id; z `mail()->accounts()` (`->id`) |
| `account` | `?string` | konto nadawcze po adresie e-mail; alternatywa dla `accountId` |
| `to` | `?list<string>` | adresaci glowni |
| `cc` | `?list<string>` | kopia (do wiadomosci) |
| `bcc` | `?list<string>` | ukryta kopia |
| `subject` | `?string` | temat; przy wysylce wprost; z szablonu bierze sie z szablonu |
| `body` | `?string` | tresc HTML; przy wysylce wprost |
| `templateId` | `?int` | szablon zamiast tresci wprost; z `mail()->templates()` (`->id`) |
| `variables` | `?array<string,mixed>` | wartosci placeholderow `{nazwa}` szablonu |
| `includeFooter` | `?bool` | czy dolaczyc stopke |
| `footerUserId` | `?int` | stopka konkretnego uzytkownika; `userId` z `resolveUserId()` |
| `sendAt` | `?string` | wysylka odroczona, ISO 8601 (`DATE_ATOM`); brak = od razu |

Zalaczniki NIE sa polem tego DTO - podajesz je drugim parametrem `send()` jako
`list<FileUpload>`.

Konto nadawcze (odczyt): `MailAccount` (`src/Dto/MailAccount.php`).

| pole | typ | po co |
|---|---|---|
| `id` | `int` | to jest `accountId` do `MailSendInput` |
| `email` | `?string` | adres konta - do dopasowania po nazwie/adresie |
| `name` | `?string` | nazwa opisowa konta |
| `raw` | `array` | pelny surowy rekord |

Szablon (odczyt): `MailTemplate` (`src/Dto/MailTemplate.php`). Lista niesie
tylko metadane; pelny `body` i adresatow zwraca `getTemplate($id)`.

| pole | typ | po co |
|---|---|---|
| `id` | `int` | to jest `templateId` do `MailSendInput` |
| `name` | `?string` | nazwa do dopasowania |
| `subject` | `?string` | temat z szablonu |
| `categoryId` | `?int` | kategoria (`templateCategories()`) |
| `body` | `?string` | tresc HTML z placeholderami `{nazwa}` (tylko z `getTemplate()`) |
| `to`/`cc`/`bcc` | `array` | domyslni adresaci szablonu |
| `raw` | `array` | pelny surowy rekord |

Nowy szablon (zapis, API >= 2.4.0): `MailTemplateInput`
(`src/Dto/MailTemplateInput.php`). Wymagane `name` i `subject`.

| pole | typ | po co |
|---|---|---|
| `name` | `?string` | nazwa szablonu; WYMAGANE |
| `subject` | `?string` | temat; WYMAGANE |
| `body` | `?string` | tresc HTML z placeholderami `{nazwa}` |
| `categoryId` | `?int` | kategoria z `templateCategories()`; null = poziom glowny |
| `alias` | `?string` | unikalny alias, max 31 znakow (CRM znormalizuje do `!maly_snake`) |
| `to`/`cc`/`bcc` | `?list<string>` | domyslni adresaci |
| `acl` | `?array` | widocznosc (`{userIds, departmentIds, groupIds}`); null = wszyscy |
| `priority` | `?int` | kolejnosc na liscie (wyzszy = wyzej) |
| `default` | `?bool` | true = szablon domyslny (flaga schodzi z poprzedniego) |

## Model danych w skrocie

Wysylke robi `mail()->send(MailSendInput, $attachments)`. Zwraca `WriteResult`
z id wiadomosci w `->id` (z pola `messageId`). Dwie decyzje przed wyslaniem:

1. **Ktore konto** - `accountId` albo `account`. Jesli uzytkownik nie wskaze,
   odpytaj `mail()->accounts()` i wybierz jedno (albo dopytaj przy kilku).
2. **Tresc wprost czy z szablonu** - `subject`+`body`, albo `templateId`+
   `variables`. Placeholdery `{nazwa}` w szablonie wypelniasz przez `variables`.

Transport wybiera sie sam: bez zalacznikow idzie zwykly JSON, z zalacznikami -
`multipart/form-data` (payload jako JSON w polu `payload`, pliki w
`attachments[]`, max 50 MB/plik). Wariant z plikami jest BEZ RETRY - to
kluczowa pulapka (patrz nizej).

## Mapowanie intencji uzytkownika na dane API

| Uzytkownik mowi | Potrzebujesz | Skad wziac |
|---|---|---|
| "wyslij z konta biuro@..." | `account` albo `accountId` | `mail()->accounts()`, dopasuj adres/nazwe |
| "do jan@acme.pl, do wiadomosci szef@acme.pl" | `to`, `cc` | wprost z polecenia (listy adresow) |
| "temat X, tresc Y" | `subject`, `body` | wprost; `body` jako HTML |
| "uzyj szablonu Oferta" | `templateId` | `mail()->templates()`, dopasuj nazwe; potem `getTemplate()` po podglad |
| "wstaw imie klienta do szablonu" | `variables` | `['imie' => 'Jan']` na placeholder `{imie}` |
| "dolacz oferte.pdf" | `attachments[]` | `FileUpload::fromPath('/sciezka/do/plik.pdf')` |
| "wyslij jutro o 9" | `sendAt` | policz date, sformatuj `DATE_ATOM` |
| "ze stopka Piotra" | `includeFooter`, `footerUserId` | `true` + `resolveUserId()` |

## Scenariusz flagowy: mail z zalacznikiem z wybranego konta

Polecenie uzytkownika: *"Wyslij z konta biuro@przyklad.example ofertse do
jan.kowalski@przyklad.example, temat 'Oferta', w zalaczniku oferta.pdf."*

Tok postepowania:

1. Znajdz konto nadawcze po adresie (albo dopytaj, jesli nie wskazane).
2. Zbuduj `MailSendInput` z trescia wprost.
3. Dolacz plik jako `FileUpload`.
4. Wyslij DOKLADNIE raz (multipart bez retry) i zwroc id wiadomosci.

```php
use TillioCrm\Api\Dto\MailSendInput;
use TillioCrm\Api\Transport\FileUpload;
use TillioCrm\Api\Exception\TransportException;

// Krok 1: wybor konta nadawczego. Dopasuj po adresie wsrod dostepnych kont.
$wantedAccount = 'biuro@przyklad.example';
$accountId = null;
foreach ($client->mail()->accounts() as $account) {
    if (mb_strtolower((string) $account->email) === mb_strtolower($wantedAccount)) {
        $accountId = $account->id;
        break;
    }
}
if ($accountId === null) {
    // Nie zgaduj konta - dopytaj albo pokaz liste dostepnych.
    throw new RuntimeException("Nie znaleziono konta nadawczego {$wantedAccount} - wskaz jedno z mail()->accounts().");
}

// Krok 2: tresc wprost (subject + body jako HTML).
$input = new MailSendInput(
    accountId: $accountId,
    to: ['jan.kowalski@przyklad.example'],
    subject: 'Oferta',
    body: '<p>Dzien dobry,</p><p>w zalaczniku przesylam oferte.</p>',
);

// Krok 3: zalacznik z dysku. FileUpload rzuca, gdy plik nie istnieje.
$attachments = [FileUpload::fromPath('/sciezka/do/plik.pdf')];

// Krok 4: wysylka DOKLADNIE raz. Z plikami idzie multipart BEZ retry - powtorka
// po timeoutcie, ktory doszedl, to drugi mail u odbiorcy. Po TransportException
// NIE ponawiaj na slepo.
try {
    $result = $client->mail()->send($input, $attachments);
} catch (TransportException $e) {
    // Nie wiadomo, czy mail wyszedl. Zglos uzytkownikowi, nie wysylaj ponownie
    // automatycznie - ryzyko duplikatu.
    throw new RuntimeException('Wysylka nie potwierdzona (timeout/transport). Sprawdz skrzynke wyslanych przed ponowieniem. ' . $e->getMessage());
}

echo "Wyslano wiadomosc #{$result->id} do jan.kowalski@przyklad.example z zalacznikiem.\n";
```

Co zwrocic uzytkownikowi: id wiadomosci (`$result->id`), adresata i informacje
o zalaczniku. Przy `TransportException` NIE ponawiaj automatycznie.

## Warianty

### Wysylka z szablonu z placeholderami

```php
use TillioCrm\Api\Dto\MailSendInput;

// Znajdz szablon po nazwie (lista niesie tylko metadane).
$templateId = null;
foreach ($client->mail()->templates() as $template) {
    if (mb_strtolower((string) $template->name) === mb_strtolower('Oferta powitalna')) {
        $templateId = $template->id;
        break;
    }
}
if ($templateId === null) {
    throw new RuntimeException('Nie znaleziono szablonu "Oferta powitalna" - dopytaj albo zaloz szablon.');
}

// Opcjonalnie: podgladnij pelna tresc i placeholdery przed wysylka.
$full = $client->mail()->getTemplate($templateId);   // ->body niesie {placeholdery}

// variables wypelnia placeholdery {nazwa} w tresci szablonu.
$client->mail()->send(new MailSendInput(
    accountId: 5,
    to: ['jan.kowalski@przyklad.example'],
    templateId: $templateId,
    variables: ['imie' => 'Jan', 'firma' => 'Acme'],
));
```

### Zalacznik z pamieci (PDF wygenerowany w locie)

```php
use TillioCrm\Api\Transport\FileUpload;

// Gdy plik nie jest na dysku, tylko w zmiennej - fromString bez zapisu na dysk.
$pdfBytes = generateOfferPdf();   // Twoj kod: zwraca surowe bajty PDF
$attachments = [FileUpload::fromString($pdfBytes, 'oferta.pdf', 'application/pdf')];
$client->mail()->send($input, $attachments);
```

### Wysylka odroczona i stopka

```php
use TillioCrm\Api\Dto\MailSendInput;

// sendAt w ISO 8601 z offsetem strefy. Stopka konkretnego uzytkownika po userId.
$sendAt = (new DateTimeImmutable('tomorrow 09:00'))->format(DATE_ATOM);
$client->mail()->send(new MailSendInput(
    accountId: 5,
    to: ['jan.kowalski@przyklad.example'],
    subject: 'Przypomnienie',
    body: '<p>Przypominamy o spotkaniu.</p>',
    includeFooter: true,
    footerUserId: 42,   // resolveUserId() jak w innych playbookach
    sendAt: $sendAt,
));
```

### Zalaczniki szablonu (API >= 2.7.0)

Szablon może mieć własne, na stałe dopięte pliki - CRM dokłada je do KAŻDEJ
wysyłki z tym szablonem, niezależnie od `attachments[]` pojedynczej wysyłki
(np. cennik dołączany do oferty powitalnej). Odrębne metody, wymagają API >= 2.7.0.
`downloadUrl` ma TTL ~1 min, a `id` załącznika jest STRINGIEM (nie int jak id
encji CRM).

```php
use TillioCrm\Api\Transport\FileUpload;

// Zalaczniki dopiete do szablonu - CRM dokłada je do KAZDEJ wysylki z tym
// szablonem (osobno od attachments[] pojedynczej wysylki). Wymaga API >= 2.7.0.

// Odczyt zalacznikow szablonu (z podpisanym downloadUrl, TTL ~1 min).
$attachments = $client->mail()->templateAttachments($templateId);   // list<MailTemplateAttachment>
foreach ($attachments as $att) {
    // UWAGA: $att->id jest STRINGIEM (nie int jak id encji CRM).
    if ($att->downloadUrl !== null) {
        $bytes = $client->download($att->downloadUrl);   // pobierz od razu, link wygasa
    }
}

// Dopiecie pliku do szablonu (do 10 MB, multipart, BEZ retry). Zwraca
// MailTemplateAttachment nowego zalacznika - jego id to STRING.
$added = $client->mail()->addTemplateAttachment($templateId, FileUpload::fromPath('/sciezka/do/cennik.pdf'));
echo "Dopieto zalacznik {$added->id} do szablonu #{$templateId}.\n";

// Usuniecie zalacznika szablonu - attachmentId to STRING z odczytu/dopiecia.
$client->mail()->deleteTemplateAttachment($templateId, $added->id);
```

### Nowy szablon (API >= 2.4.0)

```php
use TillioCrm\Api\Dto\MailTemplateInput;

// Wymaga instancji >= 2.4.0. Wymagane name i subject; body z placeholderami.
$template = $client->mail()->createTemplate(new MailTemplateInput(
    name: 'Oferta powitalna',
    subject: 'Oferta dla {firma}',
    body: '<p>Dzien dobry {imie},</p><p>w zalaczniku oferta.</p>',
));
echo "Utworzono szablon #{$template->id}.\n";
```

## Pulapki

- **Wysylka z zalacznikami jest BEZ RETRY.** Wariant multipart nie jest
  ponawiany - powtorka po timeoutcie, ktory w rzeczywistosci doszedl, to drugi
  mail u odbiorcy. Po `TransportException` NIE ponawiaj automatycznie; najpierw
  sprawdz, czy mail nie wyszedl.
- **Konto: `accountId` ALBO `account`, nie zgaduj.** Jesli uzytkownik nie
  wskaze konta jednoznacznie, odpytaj `accounts()` i dopytaj przy kilku
  kandydatach.
- **Tresc wprost XOR szablon.** Albo `subject`+`body`, albo `templateId`+
  `variables`. Mieszanie bywa niejednoznaczne - przy szablonie temat i tresc
  ida z szablonu.
- **Placeholdery to `{nazwa}`.** Wartosci podajesz w `variables` jako mape
  `nazwa => wartosc`. Brakujaca zmienna zostawia placeholder w tresci - uzupelnij
  wszystkie albo dopytaj.
- **`send()` zwraca `WriteResult` z id w `->id`** (zrodlo: pole `messageId`).
  To nie jest samo id - sa tez `->warnings`.
- **`sendAt` w ISO 8601 z offsetem** (`DATE_ATOM`, np.
  `2026-09-05T09:00:00+02:00`). Inny format to blad walidacji.
- **Lista szablonow niesie tylko metadane.** Pelny `body` z placeholderami
  zwraca dopiero `getTemplate($id)`.
- **Szablony (`createTemplate`, `templateCategories`) wymagaja API >= 2.4.0.**
  Na starszej instancji trasy nie istnieja - sprawdz `health()['version']` albo
  obsluz `ServiceUnavailableException`.
- **Zalacznik z pamieci wymaga nazwy.** `FileUpload::fromString()` rzuca przy
  pustej nazwie - API zapisuje ja w CRM.
- **Zalaczniki szablonu wymagaja API >= 2.7.0, a ich id to STRING.**
  `templateAttachments()`/`addTemplateAttachment()`/`deleteTemplateAttachment()`
  na starszej instancji zwroca `FeatureNotSupportedException` (501) - nie ponawiaj,
  zaktualizuj CRM. `addTemplateAttachment()` idzie multipartem, limit 10 MB, BEZ
  retry; `deleteTemplateAttachment()` bierze `attachmentId` jako STRING (nie int).
