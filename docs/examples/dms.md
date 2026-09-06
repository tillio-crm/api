# DMS - repozytorium plików kontrahenta

**Dokumenty adresuje się WYŁĄCZNIE stringowym `publicId`** - pole `id` to
referencja rekordu w CRM, która w ścieżkach API nie działa (404 albo cudzy
plik). `publicId` bierze się z listingu albo z wyniku uploadu.

```php
use TillioCrm\Api\Transport\FileUpload;

// Listing JEDNEGO poziomu drzewa (bez stronicowania; null = poziom główny)
$listing = $client->dms()->listing(12345);
$listing->directories;                       // list<DmsDirectory>
$listing->documents;                         // list<DmsDocument>
$doc = $listing->findDocument('umowa.pdf');  // szukanie lokalne - jedyna droga do publicId

// Katalogi - DMS nie ma duplicateCheck, więc do idempotentnego zakładania
// struktury służy ensureDirectory (najpierw szuka, potem tworzy):
$dir = $client->dms()->ensureDirectory(12345, 'Umowy');

// Upload (multipart, pole file, limit 128 MB; bez retry)
$uploaded = $client->dms()->uploadDocument(
    12345,
    FileUpload::fromPath('/sciezka/umowa.pdf'),
    directoryId: $dir->id,
);
$uploaded->publicId;    // TYM adresujesz plik od teraz

// Metadane + pobranie: downloadUrl żyje ~1 minutę - pobieraj świeży, nie buforuj
$document = $client->dms()->getDocument($uploaded->publicId);
if ($document->downloadUrl !== null) {
    $bytes = $client->download($document->downloadUrl);
}
// downloadUrl === null znaczy "środowisko bez podpisywania linków",
// nie "brak pliku".

// Zmiana nazwy - jedyna edycja dostępna przez API. Nazwę podawaj BEZ
// rozszerzenia: CRM zawsze dokleja je ze starej nazwy ('umowa-2026.pdf'
// skończyłoby jako 'umowa-2026.pdf.pdf'). Duplikat w katalogu dostaje sufiks -
// prawdziwa nazwa jest ta z odpowiedzi:
$renamed = $client->dms()->renameDocument($uploaded->publicId, 'umowa-2026');
$renamed->fileName;    // 'umowa-2026.pdf'
```
