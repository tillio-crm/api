# Praca z plikami

API v2 obsługuje pliki w kilku miejscach i we WSZYSTKICH obowiązują te same
dwie reguły: upload idzie przez `multipart/form-data` (nie JSON), a pobranie
NIGDY nie zwraca binariów - dostajesz podpisany `downloadUrl` i pobierasz plik
osobno. Ten dokument zbiera całą pracę z plikami; playbooki domenowe odsyłają
tutaj.

## Trzy rodzaje plików

| Rodzaj | Gdzie | Metoda uploadu |
|---|---|---|
| Dokumenty DMS | repozytorium plików kontrahenta | `dms()->uploadDocument()` |
| Załączniki | notatki, zadania, maile | `notes()->addAttachment()`, `tasks()->addAttachment()`, `mail()->send(..., $attachments)` |
| Pliki w polu niestandardowym typu FILE | wartość pola FILE na rekordzie | `customFields()->uploadFieldFile()` (API >= 2.6.0) |

## Wgrywanie: typ `FileUpload`

Plik do wysyłki opakowujesz w `FileUpload` - z dysku albo z pamięci:

```php
use TillioCrm\Api\Transport\FileUpload;

$fromDisk   = FileUpload::fromPath('/sciezka/do/umowa.pdf');            // nazwa z ostatniego segmentu sciezki
$fromMemory = FileUpload::fromString($pdfBytes, 'umowa.pdf', 'application/pdf');  // np. PDF wygenerowany w locie
```

Wszystkie uploady idą BEZ retry. Upload nie jest idempotentny: powtórka po
timeoucie, który w rzeczywistości doszedł, zostawiłaby drugi plik (DMS, załączniki)
albo nadpisałaby świeży (pole FILE). To celowe - nie zmieniaj tego, obsłuż wyjątek.

## Pobieranie: zawsze przez podpisany `downloadUrl`

API nie oddaje binariów w odpowiedzi. Metadane pliku niosą `downloadUrl` -
podpisany link do magazynu plików, ważny OKOŁO 1 MINUTY. Pobierasz go osobnym
klientem, BEZ nagłówków Tillio (podpis jest w URL-u):

```php
$document = $client->dms()->getDocument($publicId);   // swiezy downloadUrl
$bytes = $client->download($document->downloadUrl);   // surowe bajty pliku
```

Nie buforuj `downloadUrl` - po wygaśnięciu odczytaj metadane ponownie.
`downloadUrl === null` znaczy "srodowisko bez podpisywania", nie "brak pliku".

`download()` przyjmuje wyłącznie adresy `http`/`https` z hostem i bez poświadczeń
w URL-u; cokolwiek innego (np. `file://`) leci `TransportException` jeszcze przed
wysyłką. To zabezpieczenie na wypadek, gdyby adres w Twojej aplikacji dało się
podmienić z zewnątrz - podawaj tu `downloadUrl` z metadanych, nie adres od
użytkownika.

## DMS: dokumenty kontrahenta

```php
use TillioCrm\Api\Transport\FileUpload;

// Upload (pole file, limit 128 MB; directoryId = katalog docelowy).
$doc = $client->dms()->uploadDocument($contractorId, FileUpload::fromPath('/sciezka/umowa.pdf'), directoryId: 7);

// Metadane + swiezy downloadUrl (dokument adresujesz publicId, NIE liczbowym id).
$meta = $client->dms()->getDocument($doc->publicId);
$bytes = $client->download($meta->downloadUrl);
```

Dokument DMS adresuje się WYŁĄCZNIE stringowym `publicId` - liczbowe `id` w tej
ścieżce nie działa (404 albo cudzy plik). Katalogi zakłada się idempotentnie
przez `ensureDirectory()`. Szczegóły: [docs/examples/dms.md](../../examples/dms.md).

## Załączniki notatek, zadań i maili

```php
use TillioCrm\Api\Transport\FileUpload;

$client->notes()->addAttachment($noteId, FileUpload::fromPath('/sciezka/oferta.pdf'));
$client->tasks()->addAttachment($taskId, FileUpload::fromPath('/sciezka/brief.pdf'));

// Mail: zalaczniki jako lista FileUpload; z zalacznikami wysylka idzie multipart, bez retry.
$client->mail()->send($mailInput, [FileUpload::fromPath('/sciezka/oferta.pdf')]);

// Odczyt zalacznikow notatki (downloadUrl TTL ~1 min):
foreach ($client->notes()->attachments($noteId) as $att) {
    $bytes = $att->downloadUrl !== null ? $client->download($att->downloadUrl) : null;
}
```

## Pliki w polu niestandardowym typu FILE (API >= 2.6.0)

Pole niestandardowe typu FILE to OSOBNY mechanizm - jego wartości NIE zapisuje
się przez `customField` w PUT rekordu (core je odrzuca). Służą do tego trzy
dedykowane metody. Encje z polami plikowymi: `contractor`, `contact`, `note`,
`lead`, `ticket`, `service`, `project`, `pipeline` (zadania NIE mają tej końcówki).

```php
use TillioCrm\Api\Transport\FileUpload;

// Wgranie (multipart, zastepuje poprzedni plik, limit 25 MB, bez retry).
$file = $client->customFields()->uploadFieldFile('contractor', 42, 'contractor_file_2', FileUpload::fromPath('/sciezka/umowa.pdf'));

// Odczyt metadanych + swiezy downloadUrl (null = pole bez pliku).
$file = $client->customFields()->getFieldFile('contractor', 42, 'contractor_file_2');
if ($file !== null && $file->downloadUrl !== null) {
    $bytes = $client->download($file->downloadUrl);
}

// Usuniecie pliku z pola (i samego pliku z instancji).
$client->customFields()->deleteFieldFile('contractor', 42, 'contractor_file_2');
```

W ODCZYCIE REKORDU wartość pola FILE pojawia się w `customField[<klucz>]` jako
obiekt `{fileName, mimeType, sizeBytes, storagePath, fileUrl}` (bez `downloadUrl` -
po plik idziesz przez `getFieldFile()`, którego adres niesie `fileUrl`). Pole bez
pliku ma tam `null`. Szczegóły pola FILE: [../custom-fields/README.md](../custom-fields/README.md).

## Tryb proxy: pliki działają, ale binaria omijają proxy

W trybie proxy pobieranie plików DZIAŁA, bo przez proxy jedzie tylko JSON
z `downloadUrl`, a sam plik idzie prosto z magazynu. Gdyby jednak jakaś odpowiedź
binarna trafiła w proxy, dostaniesz jasny `ProxyBinaryResponseException` -
nie pobieraj plików "przez proxy", zawsze przez `downloadUrl`.

## Pułapki

- **`downloadUrl` żyje ~1 minutę.** Pobieraj od razu po odczycie metadanych;
  po wygaśnięciu odczytaj metadane ponownie. Nie buforuj linku.
- **Uploady bez retry.** Obsłuż `TransportException` sam; nie ponawiaj w pętli.
- **DMS: tylko `publicId`.** Liczbowe `id` w ścieżce dokumentu nie działa.
- **Pole FILE nie przez `customField`.** Zapis/odczyt pliku pola FILE to osobne
  metody `customFields()->*FieldFile()`, nie PUT rekordu.
- **Limity różne:** DMS 128 MB, załączniki notatek/zadań 128 MB, mail 50 MB/plik,
  pole niestandardowe FILE 25 MB.
- **`downloadUrl === null` to nie brak pliku** - to środowisko bez podpisywania.
