# Changelog

Wszystkie istotne zmiany paczki `tillio-crm/api`. Format wpisów: po ludzku,
z perspektywy konsumenta SDK; wpisy grupowane per wydanie (przy 0.1.0 wszystko
jest nowe, od kolejnych wydań sekcje Dodane/Zmienione/Naprawione).
Wersjonowanie: semver (przed 1.0.0 zmiany łamiące = minor).

## [0.4.0] - 2026-09-18

Wydanie z dostosowaniem do kontraktów API 2.15.0 i 2.16.0: przejścia statusów
leada i szansy dedykowanymi trasami, lookup po adresie e-mail, kategorie i tagi
leadów, kontakty przy szansie, a po stronie użytkowników służbowe dane kontaktowe
i osobna trasa aktywności. Minor, bo doszły nowe metody i pola, a `UserInput`
zmienia nazwy dwóch pól - szczegóły w Zmienione.

### Dodane

- `users()->activity()`, `users()->iterateActivity()` i `users()->getActivity($id)` -
  `GET /v2/users/activity` i `GET /v2/users/{id}/activity` (API >= 2.16.0):
  ostatnie logowanie, ostatnia czynność i licznik logowań konta, policzone ze
  wszystkich jego sesji. Nowe DTO `UserActivity` (`lastLoginAt`, `lastActivityAt`,
  `loginCount`, `hasEverLoggedIn()`); konto bez logowań ma daty `null` i `0`.
  Agregaty są na osobnej trasie, żeby `users()->list()` został lekki, więc tych
  pól nie ma w `SystemUser`. `iterateActivity()` wymusza `sort=userId` - ta lista
  nie ma pola `id`. "Kto nie logował się od miesiąca": `sort=lastActivityAt`,
  `sortDir=asc` (konta bez logowań idą pierwsze).
- `SystemUser`: `jobTitle`, `contactPhone`, `contactEmail` i `gender` w odczycie
  (API >= 2.16.0) - służbowe dane kontaktowe. `email` to nadal LOGIN i nie musi
  być tym samym adresem co `contactEmail`. Prywatny telefon i e-mail, hasła,
  tokeny i ustawienia 2FA dalej nie wychodzą przez API.
- `UserInput::$contactEmail` - służbowy adres e-mail do kontaktu, inny niż login
  (API >= 2.16.0). Adres niepoprawny nie blokuje założenia konta, wraca
  w `CreatedUser::$warnings`.
- Filtry `users()->list()`: `jobTitle` (zawiera), `contactPhone` (zawiera),
  `contactEmail` (dokładnie) i `gender` (`male|female|unspecified`), API >= 2.16.0.
- `leads()->changeStatus($id, $leadStatusId, $reasonId, $note)` -
  `POST /v2/leads/{id}/status` (API >= 2.15.0). Status leada to w CRM proces
  z historią, więc ma własną trasę: PUT dalej odbija `leadStatusId` błędem 422
  `body.fieldNotUpdatable`. Powód zmiany i notatkę (do 255 znaków) przyjmują
  WYŁĄCZNIE statusy kończące (`qualified`/`disqualified`); powód
  z `noteRequired` bez notatki to 422. Odpowiedź niesie leada po zmianie
  w `WriteResult::$data`.
- `pipelineItems()->changeStage($id, $pipelineStageId)` -
  `POST /v2/pipeline/items/{id}/stage` (API >= 2.15.0). CRM prowadzi historię
  etapów, dobiera prawdopodobieństwo z nowego etapu i przy zmianie lejka
  przepina przypisania pól niestandardowych. Etap wymagający pól, których
  szansa nie ma, to 422 `body.requiredFieldsMissing` z ich listą; brak dostępu
  do lejka etapu docelowego - 403.
- `pipelineItems()->changeStatus($id, $pipelineStatusId, $reasonId, $note)` -
  `POST /v2/pipeline/items/{id}/status` (API >= 2.15.0): 1 = aktywna (ponowne
  otwarcie), 2 = stracona, 3 = wygrana. Powód i notatkę przyjmują tylko 2 i 3,
  a powód musi należeć do statusu i do lejka szansy albo być wspólny.
- `lookup()->email($adres)` - `GET /v2/lookup/email` (API >= 2.15.0): kontakty
  i kontrahenci z tym adresem, dopasowanie dokładne (bez względu na wielkość
  liter), pełne rekordy jak w `lookup()->phone()`. Adres przyjmowany też
  w formie `Jan Kowalski <jan@acme.pl>`; nowe DTO `EmailLookupResult`.
- Cztery słowniki (tylko odczyt, API >= 2.15.0): `dictionaries()->leadCategories()`,
  `leadTags()`, `leadStatusChangeReasons(?int $leadStatusId)` oraz
  `pipelineStatusChangeReasons(?int $pipelineStatusId, ?int $pipelineFunnelId)`.
  Powody zmiany statusu mają własne DTO `StatusChangeReason` (`noteRequired`,
  `leadStatusId`, `pipelineStatusId`, `pipelineFunnelId`, `isDefault`); zakłada
  się je w panelu CRM, API przyjmuje wyłącznie ich id.
- `LeadInput`: `categoryId`, `region`, `district` i `leadTagIds` w zapisie
  (API >= 2.15.0). `leadTagIds` w `create()` DOKŁADA tagi do trafionego leada,
  w `update()` jest kompletną listą docelową (`[]` zdejmuje wszystkie);
  zdjęcie kategorii wymaga jawnego nulla, czyli tablicy
  (`update($id, ['categoryId' => null])`). `contractorSourceId` jest od 2.15.0
  edytowalny także w `update()`.
- `Lead::$leadTagIds` w odczycie oraz filtry listy `leadTagId` i `district`.
- `PipelineItemInput::$contactIds` i `PipelineItem::$contactIds` - kontakty
  przypięte do szansy (API >= 2.15.0, najwyżej 50). Wyłącznie osoby jej
  kontrahenta - obca to 422; w `update()` kompletna lista docelowa, `[]` odpina
  wszystkie. Nowy filtr listy `contactId`.
- `ContactInput::$contractorIds` - zastąpienie całej listy kartotek kontaktu
  (pierwsza = główna, API >= 2.10.0). Pole było w kontrakcie POST i PUT, a nie
  miało odpowiednika w DTO; pusta lista to 422 po stronie API.
- `DictionaryEntryInput`: `isFinal`, `icon`, `description`, `isUnique`,
  `passTasks` i `acl` - pola, które kontrakt przewiduje dla statusów zadań
  i zgłoszeń, typów notatek, priorytetów i typów płatności kontrahenta, typów
  adresów oraz statusów projektów, a których DTO dotąd nie wysyłało.
- `acl` w `LeadProcessInput`, `PipelineFunnelInput` i `TicketProcessInput` oraz
  `pinProtected` w `TicketProcessInput` - ograniczenie widoczności procesu
  albo lejka, dotąd nieobecne w DTO mimo obecności w kontrakcie.

### Zmienione

- **ZMIANA ŁAMIĄCA - `UserInput`: `position` -> `jobTitle`, `phone` ->
  `contactPhone`.** Kontrakt 2.16.0 przemianował te pola w `POST /v2/users`
  i pod starymi nazwami już ich nie zna. Kod zakładający konta trzeba
  przemianować; odczyt zyskuje te same nazwy (`SystemUser::$jobTitle`,
  `$contactPhone`), więc zapis i odczyt mówią wreszcie tym samym słownikiem.
- Mapa tras zweryfikowana 1:1 z kontraktem API **2.16.0** (240 -> **242 trasy**:
  aktywność wszystkich użytkowników i pojedynczego konta).
- Mapa tras zweryfikowana 1:1 z kontraktem API **2.15.0** (232 -> **240 tras**:
  zmiana statusu leada, etapu i statusu szansy, lookup po adresie e-mail
  i cztery nowe słowniki; 2.14.1 po drodze tras nie zmieniała).
- `priority` leada, zgłoszenia i zadania to enum `0|1|2` (0 standard, 1 wysoki,
  2 najwyższy; domyślnie 0). Inna wartość kończy się 422 `body.invalidValue`
  przed zapisem - SDK nie filtruje jej lokalnie, tylko podaje błąd z nazwą pola.
  Dokumentacja nie mówi już "kontrakt nie definiuje skali".
- Notatka kontrahenta przyjmuje `serviceId` i `pipelineItemId` wprost
  z kontraktu, a usługa albo szansa INNEGO kontrahenta to teraz 422 na tym polu
  (wcześniej CRM po cichu zerował powiązanie i notatka powstawała bez niego).
  W odczycie powiązanie z szansą nadal nazywa się `pipelineId`.
- Dokumentacja normalizacji wejścia (nowa sekcja "Normalizacja wejścia"
  w `docs/.ai/ai_integration.md`): telefon sprowadzany do E.164 przez
  libphonenumber, a numer niepoprawny dla swojego kraju - za krótki, za długi,
  z doklejonym numerem wewnętrznym - NIE zapisuje się i wraca w `warnings`
  (w `lookup()->phone()` to 422). NIP bez prefiksu kraju traktowany jak polski
  (10 cyfr, `PL` pomijane), z prefiksem z obsługiwanej listy sprawdzany co do
  formatu kraju; e-mail, domena i URL walidowane; treści HTML czyszczone do
  bezpiecznego podzbioru jak w edytorze CRM, a pusta po wycięciu treść to pole
  pominięte z ostrzeżeniem. Wyszukiwanie duplikatu jest odporne na format
  (telefon z plusem i bez, domena z `www.`, NIP z `PL`), więc SDK niczego nie
  normalizuje przed wysyłką.
- Numery telefonów w fikstach testowych i przykładach są poprawne wg
  libphonenumber - `+48000000000` i `+48000000001` nie przeszłyby już zapisu.
- PHPDoc `selfcheck()`: sekcja `core` raportu ma od API 2.14.1 pola
  `callbacksChecked` i `mismatchedCallbacks` - pola modeli CRM, których zapis
  wskazuje inną metodę niż ta, na której polega API (zapis przeszedłby bez błędu
  i bez danych). Niepusta lista to `status: failed` i HTTP 500; SDK oddaje raport
  jak przy każdym wykrytym dryfie.
- PHPDoc `TaskInput::$pipelineItemId`: odpięcie szansy w `tasks()->update()` to
  jawny null w tablicy (`['pipelineItemId' => null]`), pusty string nie odpina.
  Od API 2.14.1 specyfikacja oznacza to pole w PUT jako `nullable`.

## [0.3.1] - 2026-09-16

Wydanie poprawkowe: uruchamialne przykłady trafiają do paczki z Composera.

### Dodane

- Przykłady z `examples/` uruchomisz po instalacji Composerem: konfigurację
  trzymasz we własnym projekcie i wskazujesz ją w zmiennej środowiskowej
  `TILLIO_EXAMPLES_CONFIG`, a `bootstrap.php` znajduje autoloader zarówno
  w klonie repozytorium, jak i w `vendor/`.

### Naprawione

- Katalog `examples/` jest teraz w paczce z Composera
  (`vendor/tillio-crm/api/examples/`). Wcześniej był z niej wykluczony, choć
  README i dokumentacja dla agentów AI do niego odsyłają.
- Liczba plików z przykładami w README zgadza się ze stanem katalogu
  `docs/examples/` (24 pliki).

## [0.3.0] - 2026-09-15

Wydanie z dostosowaniem do kontraktów API 2.13.0 i 2.14.0: leady bez dubli
(create-or-attach i batch upsert), adresy e-mail leada w zapisie, notatka pod
leadem, szansa sprzedaży w odczycie zadania oraz dokumentacja zaostrzonych reguł
z 2.14.0. Minor, bo doszły nowe metody i pola; uwaga na zmianę zachowania
`leads()->create()` po stronie API - szczegóły w Zmienione.

### Dodane

- `leads()->create()` przyjmuje `WriteOptions` jako drugi argument
  (`duplicateCheck`, `allowDuplicates`, `requireDuplicateCheck`). Od API 2.13.0
  API przed zapisem szuka istniejącego leada (domyślnie po e-mailu i telefonie,
  do wyboru też `taxId`, `domain`, `companyName`, `custom:<klucz>`) i zamiast
  dubla podpina dane do znalezionego: uzupełnia puste pola, dokłada adresy
  e-mail, wpisuje telefon w wolny numer, nadpisuje tylko `customField`.
  Odpowiedź 200 z `created === false`, `matchedBy()` i id istniejącego leada;
  przy leadzie już skonwertowanym `duplicate->raw['contractorId']` wskazuje
  kontrahenta. Lokalny strażnik `duplicateCheck` sprawdza warunek `email`
  w liście `emails`, bo lead nie ma pola `email`.
- `leads()->upsert()` - `POST /v2/leads/upsert`, paczka do 100 leadów, statusy
  `created|attached|failed` w `UpsertResult` (API >= 2.13.0).
- `leads()->createNote()` - `POST /v2/leads/{leadId}/notes`, notatka pod
  leadem (API >= 2.13.0). Body jak `NoteInput` kontrahenta, bez `contactIds`,
  `serviceId` i `pipelineItemId`.
- `LeadInput::$emails` - adresy e-mail leada w zapisie, pierwszy = główny
  (API >= 2.13.0). W `create()` lista do założenia (przy podpięciu adresy są
  dokładane), w `update()` kompletna lista docelowa - `[]` usuwa wszystkie.
- `Task::$pipelineItemId` i filtr `pipelineItemId` w `tasks()->list()` -
  szansa sprzedaży powiązana z zadaniem (API >= 2.13.0).

### Zmienione

- Mapa tras zweryfikowana 1:1 z kontraktem API **2.14.0** (232 trasy - w 2.13.0
  doszły `POST /v2/leads/upsert` i `POST /v2/leads/{leadId}/notes`, 2.14.0 tras
  nie zmienia).
- PHPDoc i dokumentacja pod zaostrzony kontrakt API 2.14.0. Sygnatury SDK bez
  zmian; to zachowania po stronie API, które integracja musi znać:
  - `TicketMessageInput::$visibility`: `internal` zawsze daje 422
    `ticket.internalMessagesUnavailable`. Starsza instancja z komentarzami
    zgłoszeń zapisywała taką wiadomość jako zwykłą, widoczną dla klienta.
  - `generatedDocuments()->create()`/`regenerate()`: typ ze `store=false` to 422
    `document.typeNotStored`, a `updatePipeline` przy szablonie HTML to 422
    `document.updatePipelineUnsupported` (CRM kasował tam pozycje szansy).
  - `OrderInput::$currency` tylko przy tworzeniu - w `orders()->update()` to 422
    i nic z żądania się nie zapisuje.
  - `stocks()->update()`: nieudany zapis CRM to 422 `stock.saveFailed` zamiast
    200 z poprzednią ilością; `adjustBy` nie jest atomowe, korekty tej samej pary
    produkt/magazyn trzeba wysyłać po kolei.
  - `ServiceInput`: daty umowy w częściowym `update()` porównywane z zapisanymi
    (422 przy końcu przed początkiem); zmiana samych dat nie odświeża `updatedAt`.
  - `tasks()->update()` z `priority` i innymi polami zapisuje wszystkie (dotąd
    sam priorytet), a częściowy zapis to 422 `task.partialUpdate`.
  - `mail()->send()`: załączniki razem z załącznikami szablonu najwyżej 50 MB
    (422 `body.attachmentsTooLarge`); body JSON do 2 MB i 20 000 struktur (413).
  - `createTicketProcess()`/`createPipelineFunnel()`/`createLeadProcess()` z etapami
    zapisują wszystko albo nic (`process.partialCreate` przy odmowie CRM).
  - `WriteOptions::$failOnInvalidTaxId`: odmowa albo limit GUS to ostrzeżenie
    `taxIdLookup`, nie 422.
  - Daty w filtrach sprawdzane ściśle (pusty `updatedAfter` i nieistniejący dzień
    to 400 `query.invalidDate` zamiast cichego "teraz"), także w `calendars()->events()`;
    `CustomFieldFilter::NotSet` nie łapie już zera w polach liczbowych;
    `wiki()->entries()` z `search` wreszcie zwraca wyniki; `selfcheck()` ma
    informacyjną sekcję `acl`.
- **Zmiana zachowania po stronie API:** od 2.13.0 `leads()->create()` bez opcji
  nie zakłada drugiego leada z tym samym e-mailem albo telefonem, tylko zwraca
  istniejący (HTTP 200, `created === false`). Integracja, która liczy nowe
  leady, musi patrzeć na `$result->created`, a nie na samo `$result->id`.
  Dawne zachowanie daje `new WriteOptions(allowDuplicates: true)`.
- `DuplicateMatch::$id` bierze id z klucza encji operacji (`leadId`,
  `contractorId`...), a pierwszą wartość liczbową tylko awaryjnie - duplikat
  skonwertowanego leada niesie obok `leadId` także `contractorId`.
- PHPDoc `TaskInput::$pipelineItemId`: od API 2.13.0 szansa musi należeć do
  kontrahenta zadania (inaczej 422), a bez `contractorId` zadanie dostaje
  kontrahenta szansy. Wcześniej CRM po cichu nadpisywał kontrahenta albo
  odpinał szansę.
- Udokumentowane nowe filtry list z API 2.12.2 (SDK przekazuje filtry bez własnej
  białej listy, więc działały od razu - PHPDoc deklaruje "komplet wg kontraktu"
  i musiał nadążyć): `notes()->list()` o `id`, `leadId`, `serviceId`, `pipelineId`,
  `body`, `pinned`, `creatorUserId`; `projects()->list()` o `id`, `description`,
  `ownerUserId`, `creatorUserId`; `services()->list()` o `id`, `catalogId`,
  `catalogName`, `billingPeriodId`, `paymentTermId`, `invoiceTypeId`, `salesUserId`,
  `ownerUserId`, `place`, `note`; `serviceCatalog()->list()` o `id`, `currency`,
  `groupName`. Wymagają API >= 2.12.2 - starsza instancja odrzuca nieznany
  parametr błędem 400.

### Naprawione

- PHPDoc i przykłady `assignedTo` pól niestandardowych opisywały id
  użytkowników. To id PODTYPÓW rekordów, w których pole działa (typy notatek,
  procesy zgłoszeń, pozycje katalogu usług, procesy leadowe, lejki sprzedaży),
  i dotyczy tylko encji `note`, `ticket`, `service`, `lead`, `pipeline` - przykład
  `update('contractor', ..., ['assignedTo' => [7, 12]])` był błędny podwójnie.
  Od API 2.14.0 nieistniejący podtyp to 422, więc kod zbudowany na starym opisie
  przestaje przechodzić. Dopisane też reguły `allowUnassign` (wyłącznie bool)
  i kształtu `editableBy`.

## [0.2.0] - 2026-09-07

Wydanie z poprawką adresu domyślnego serwera (0.1.0 nie łączyło się z domyślną
konfiguracją) i dostosowaniem do kontraktu API 2.12.1. Minor, nie patch, bo
`lookup()->phone()` zmienia typy zwracanych rekordów - szczegóły w Zmienione.

### Dodane

- `Note::$url` i `Contact::$url` - gotowy link "otwórz w CRM" do notatki na osi
  czasu kartoteki i do karty osoby (API >= 2.12.0). Przydatne w powiadomieniach
  dla ludzi, zamiast sklejania adresu samodzielnie. Notatka bez kontrahenta
  i kontakt bez powiązanej firmy nie mają gdzie się otworzyć i zwracają `null`;
  na instancjach starszych niż 2.12.0 pola nie ma i też wychodzi `null`.

### Zmienione

- Mapa tras odpowiada kontraktowi API **2.12.1** (dalej 230 tras - w 2.12 nie
  doszła ani nie zniknęła żadna trasa).
- PHPDoc `PhoneCallInput::$duration`: przy statusie `answered` czas trwania
  podawaj zawsze. Od API 2.12.0 jego brak nie jest już błędem 422, ale rozmowa
  wypada z raportu VoIP, bo ten liczy odebrane po czasie rozmowy.
- PHPDoc `Contact::$lastName` i `Lead::$lastName`: udokumentowana walidacja CRM
  (2-65 znaków, litery i typowe znaki nazwisk, cyfra tylko jako pierwszy znak).
  Od API 2.12.0 obowiązuje tak samo przy tworzeniu i aktualizacji - wcześniej
  POST przepuszczał wartości, których PUT już nie przyjmował.
- **Zmiana łamiąca:** `lookup()->phone()` zwraca w `contacts` i `contractors`
  pełne `Contact` i `Contractor` zamiast okrojonych `PhoneLookupContact`
  i `PhoneLookupContractor` (obie klasy usunięte). Od API 2.12.0 endpoint oddaje
  rekordy tego samego kształtu, co `GET /v2/contacts/{id}` i `GET /v2/contractors/{id}`,
  więc identyfikacja dzwoniącego ma od razu e-mail, stanowisko, opiekuna i pola
  niestandardowe, bez drugiego żądania o kartotekę. Nazwy pól używanych dotąd
  (`id`, `firstName`, `lastName`, `phone`, `contractorId`, `contractorIds`)
  są w nowych DTO takie same; zmienia się typ. Flagę `active`, której nie ma
  w kontrakcie kontaktu, czytasz z `$contact->raw['active']`.

### Naprawione

- Domyślny adres API w trybie bezpośrednim wskazuje właściwy serwer SaaS:
  `https://s2.public.api.tillio.app`. Poprzedni adres uniemożliwiał poprawne
  połączenie z domyślną konfiguracją. Zaktualizowano przykłady konfiguracji.

### Bezpieczeństwo

- `download()` przyjmuje wyłącznie adresy HTTP(S) z hostem i bez poświadczeń
  w URL-u; adres jest sprawdzany przed wysyłką. Wcześniej pełny adres szedł
  wprost do cURL, więc `file://` w adresie oddawał zawartość lokalnego pliku,
  a inne schematy pozwalały odpytać usługi wewnętrzne - ryzyko realne wtedy,
  gdy aplikacja przekazuje do `download()` adres pochodzący od użytkownika.
  Niezależnie od tego transport ogranicza cURL do HTTP(S) dla każdego żądania.

## [0.1.0] - 2026-09-06

Pierwsze wydanie. Wymaga Tillio API v2 w wersji **co najmniej 2.0.4**
(funkcje z nowszych wydań API mają wymaganą wersję w PHPDoc metody).

- Pokrycie 100% tras API v2 (230 tras wg kontraktu 2.11.0), pilnowane testem
  kontraktowym mapy metod SDK; pełne porównanie ze specyfikacją OpenAPI
  instancji przez `TILLIO_OPENAPI_FILE`.
- Telefonia (API >= 2.10.0): połączenia telefoniczne (`phoneCalls()`) i SMS
  (`textMessages()`) w tabelach CRM - odczyt, zapis i aktualizacja z przypięciem
  do kontrahenta i notatką; `lookup()->phone()` znajduje kontakty i kontrahentów
  po numerze (kto dzwoni). Konfiguracja integracji Tillio Calls
  (`integrations()`) od API >= 2.11.0.
- Kontakty w relacjach (API >= 2.8.0): przypinanie osób kontaktowych do notatek
  (`notes()->contacts()`/`addContact()`/`removeContact()`, pole `contactIds`),
  `contactId` na zadaniu i wydarzeniu kalendarza; notatka pod kontaktem
  (`contacts()->createNote()`, API >= 2.10.0).
- Komentarze zadań przez API (>= 2.7.0): `tasks()->addComment()`/`updateComment()`,
  załącznik pod komentarzem (`commentId` w `addAttachment()`); załączniki
  szablonów maili (`mail()->templateAttachments()`/`addTemplateAttachment()`/
  `deleteTemplateAttachment()`).
- Komplet filtrów list wg kontraktu (wszystkie zasoby) i nowy wyjątek
  `FeatureNotSupportedException` (501) - funkcja poza wersją CRM instalacji,
  deterministyczny, bez retry.
- Klucz konfiguracji `instanceName` (nagłówek `X-Instance-Name`) dla serwerów
  hostujących wiele instancji CRM pod jednym adresem API; pomijany na produkcji
  z jedną instancją per domena.
- Kalendarze (API >= 2.2.0) i ich wydarzenia (API >= 2.3.0): lista kalendarzy
  z dostępami użytkowników i flagą kalendarza głównego, odczyt wydarzeń
  w zakresie dat, zakładanie wydarzeń z uczestnikami i powiązaniem
  z kontrahentem/zadaniem/zgłoszeniem. Instalacja bez modułu kalendarza
  dostaje deterministyczny `ServiceUnavailableException` zamiast retry.
- Szablony przez API (>= 2.4.0): tworzenie szablonów maili, notatek i zadań
  wraz z kategoriami (odczyt, tworzenie, edycja); pełny cykl typów dokumentów
  generatora - szkic, upload pliku źródłowego z wykryciem zmiennych,
  definicja formularza, aktywacja; kategorie i schematy numeracji dokumentów.
- Pliki pól niestandardowych typu FILE (>= 2.6.0): odczyt, wgranie (multipart)
  i usunięcie pliku z pola przez `customFields()->getFieldFile()` /
  `uploadFieldFile()` / `deleteFieldFile()`, z DTO `CustomFieldFile`
  (wartości pól FILE nie przechodzą przez `customField` w PUT rekordu).
- Dokumentacja dla asystentów AI w `docs/.ai/`: przewodnik ze wspólnymi
  wzorcami (rozwiązywanie osób i słowników na id, podłączenie, wydajne zapytania,
  pola niestandardowe, pliki) plus playbook na każdą domenę - tak, by agent
  zamienił polecenie w języku naturalnym na wywołania SDK bez zgadywania.
- Dwa tryby połączenia w jednym kliencie: bezpośredni (klucz API tenanta)
  i przez proxy platformy (aplikacje marketplace, token z auto-odświeżaniem);
  zasoby nie wiedzą, którym trybem działają.
- Typowane DTO dla każdej encji: odczyt jako `readonly` z pełnym surowym
  rekordem w `raw` (nowe pola API nie giną), zapis przez input-DTO z named
  arguments (null = nie wysyłaj pola) albo tablicę (jawne nulle).
- Wbudowana ochrona przed pułapkami kontraktu: lokalna walidacja
  `duplicateCheck` (koniec z cichymi duplikatami), wymuszone `sort=id` przy
  pełnych przebiegach (koniec z gubionymi rekordami), pusta wartość filtra
  pola niestandardowego tylko przez jawny `CustomFieldFilter::NotSet`.
- Retry z backoffem i limiter okienkowy: ponawiane 429 i 5xx, nigdy operacje
  nieidempotentne (zakładanie użytkowników z jednorazowym hasłem startowym,
  uploady plików, mail z załącznikami).
- Typowana hierarchia wyjątków z kompletem błędów walidacji
  (`{field, code, message}`) i rozróżnieniem odmowy proxy platformy od odmowy
  samego API.
- Upload plików multipart (DMS, załączniki notatek/zadań, załączniki maili)
  i pobieranie przez podpisane linki `downloadUrl`.
- Selfcheck z raportem (HTTP 500 z raportem dryfu to wynik, nie awaria),
  `health()`, `whoami()`, `openapi()`, `modules()`.

[Unreleased]: https://github.com/tillio-crm/api/compare/0.4.0...HEAD
[0.4.0]: https://github.com/tillio-crm/api/compare/0.3.1...0.4.0
[0.3.1]: https://github.com/tillio-crm/api/compare/0.3.0...0.3.1
[0.3.0]: https://github.com/tillio-crm/api/compare/0.2.0...0.3.0
[0.2.0]: https://github.com/tillio-crm/api/compare/0.1.0...0.2.0
[0.1.0]: https://github.com/tillio-crm/api/releases/tag/0.1.0
