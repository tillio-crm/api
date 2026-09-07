# Changelog

Wszystkie istotne zmiany paczki `tillio-crm/api`. Format wpisów: po ludzku,
z perspektywy konsumenta SDK; wpisy grupowane per wydanie (przy 0.1.0 wszystko
jest nowe, od kolejnych wydań sekcje Dodane/Zmienione/Naprawione).
Wersjonowanie: semver (przed 1.0.0 zmiany łamiące = minor).

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

[Unreleased]: https://github.com/tillio-crm/api/compare/0.2.0...HEAD
[0.2.0]: https://github.com/tillio-crm/api/compare/0.1.0...0.2.0
[0.1.0]: https://github.com/tillio-crm/api/releases/tag/0.1.0
