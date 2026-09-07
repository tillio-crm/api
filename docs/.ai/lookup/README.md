# Playbook: Wyszukanie po numerze telefonu (lookup)

"Kto dzwoni" - rozpoznanie kontaktu i kontrahenta po numerze telefonu. Pisane
dla asystenta AI - zaklada wspolne wzorce z
[ai_integration.md](../ai_integration.md) (zwlaszcza "Zlota zasada: nie zgaduj
id"). Wymaga API >= 2.10.0.

Zasob: `$client->lookup()`. To odczyt tylko do czytania - nic nie zapisuje.
Typowy nastepny krok po lookup: zapis rozmowy
([phone-calls](../phone-calls/README.md)) albo SMS
([text-messages](../text-messages/README.md)) z rozwiazanym `contactId`
i `contractorId`.

## Model danych w skrocie

Jedna metoda: `phone(string $number): PhoneLookupResult`. Przyjmuje numer w
dowolnej postaci - API sprowadza go do kanonu miedzynarodowego po swojej stronie.
Zwraca dwie listy: kontakty (`contacts`) i kontrahenci (`contractors`), ktorzy
maja ten numer.

```php
$result = $client->lookup()->phone('+48601234567');
```

Kontakt trafia na liste `contacts`, gdy numer pasuje do jego pola glownego
(`phone`) ALBO alternatywnego (`phoneAlternative`). Kontrahent trafia na
`contractors`, gdy numer jest zapisany wprost na kartotece firmy.

Wynik moze byc:

- pusty (nieznany numer) - obie listy puste,
- jednoznaczny - dokladnie jeden kontakt albo jeden kontrahent,
- wieloznaczny - kilka kontaktow (ten sam numer u kilku osob) albo mieszanka
  kontaktow i kontrahentow. Wtedy nie zgaduj - patrz Pulapki.

Poniewaz metoda nie ma inputu do zapisu, sekcja "Pola" opisuje pola ODCZYTU:
strukture `PhoneLookupResult` i jej elementow.

## Pola

`PhoneLookupResult` - korzen wyniku:

| pole | typ | po co |
|---|---|---|
| `number` | ?string | Numer po sprowadzeniu do kanonu miedzynarodowego (tak, jak API go zrozumialo). Warto pokazac uzytkownikowi, na jakim numerze faktycznie szukano |
| `contacts` | list&lt;Contact&gt; | Kontakty (osoby) z tym numerem w polu glownym albo alternatywnym, aktywne pierwsze (max 50) |
| `contractors` | list&lt;Contractor&gt; | Kontrahenci (firmy) z tym numerem na kartotece (max 50) |
| `raw` | array | Pelny surowy rekord z API (gdy potrzebujesz pola spoza DTO) |

Od API 2.12.0 obie listy niosa PELNE rekordy - dokladnie te same DTO, co
`contacts()->get()` i `contractors()->get()`. Masz wiec od razu e-mail,
stanowisko, opiekuna i pola niestandardowe dzwoniacego, bez drugiego zadania
o kartoteke. Pola opisuja playbooki [contacts](../contacts/README.md)
i [contractors](../contractors/README.md).

Do identyfikacji dzwoniacego najczesciej wystarcza:

| pole | typ | po co (do czego dalej) |
|---|---|---|
| `contacts[]->id` | int | Id kontaktu. To trafia jako `contactId` do `PhoneCallInput`/`TextMessageInput` |
| `contacts[]->name` | ?string | Imie i nazwisko zlozone przez API - do pokazania "kto dzwoni" |
| `contacts[]->contractorId` | ?int | Kartoteka macierzysta kontaktu. To trafia jako `contractorId` przy zapisie rozmowy/SMS |
| `contacts[]->contractorIds` | list&lt;int&gt; | Wszystkie kartoteki powiazane z kontaktem (moze byc podpiety pod wiele firm) |
| `contacts[]->url` | ?string | Link "otworz w CRM" do karty kontaktu (API >= 2.12.0) - do powiadomienia dla uzytkownika |
| `contractors[]->id` | int | Id kontrahenta. To trafia jako `contractorId` do `PhoneCallInput`/`TextMessageInput` |
| `contractors[]->name` | ?string | Nazwa firmy - do potwierdzenia dla uzytkownika |

Flaga `active` (kontakt aktywny w CRM) jest tylko w odpowiedzi lookupu, nie ma
jej w kontrakcie kontaktu, wiec czytasz ja z `$contact->raw['active']`.
Nieaktywny kontakt to sygnal do ostroznosci (stary rekord).

Pelna lista: `src/Dto/PhoneLookupResult.php`, `src/Dto/Contact.php`,
`src/Dto/Contractor.php`.

## Mapowanie intencji uzytkownika na dane API

| Uzytkownik mowi / zdarzenie | Potrzebujesz | Skad wziac |
|---|---|---|
| "dzwoni +48601234567, kto to" | `lookup()->phone($number)` | numer wprost; API sam go znormalizuje |
| "podepnij rozmowe do dzwoniacego" | `contactId` z `contacts[0]->id` | tylko gdy dokladnie jeden kontakt |
| "przypnij do firmy tej osoby" | `contractorId` z `contacts[0]->contractorId` | j.w., gdy kontakt niesie kartoteke |
| "numer nieznany, zaloz kontakt" | pusty wynik -> `contacts()->create(...)` | patrz playbook [contacts](../contacts/README.md) |

## Scenariusz flagowy: przychodzi polaczenie, znajdz kto dzwoni

Zdarzenie: *"Dzwoni +48601234567 - powiedz kto to i przygotuj powiazanie do
zapisu rozmowy."*

Tok postepowania:

1. Odpytaj `lookup()->phone()` numerem ze zdarzenia.
2. Rozroznij trzy przypadki: pusto / jednoznacznie / wieloznacznie.
3. Przy jednoznacznym trafieniu wyluskaj `contactId` i `contractorId` do
   pozniejszego zapisu rozmowy.
4. Przy wieloznacznym - NIE wybieraj sam; oddaj liste kandydatow do decyzji.
5. Przy pustym - zasygnalizuj nieznany numer (mozna zaproponowac zalozenie
   kontaktu).

```php
<?php

$remoteNumber = '+48601234567';

// Krok 1: odpytanie. API sprowadza numer do kanonu miedzynarodowego.
$result = $client->lookup()->phone($remoteNumber);

$contactId = null;
$contractorId = null;

// Krok 2 + 5: pusty wynik - numer nieznany.
if ($result->contacts === [] && $result->contractors === []) {
    echo "Numer {$result->number} nieznany - brak kontaktu i kartoteki.\n";
    // Opcjonalnie: zaproponuj zalozenie kontaktu (patrz playbook contacts).
    return;
}

// Krok 3: jednoznaczny kontakt - dokladnie jeden.
if (count($result->contacts) === 1) {
    $contact = $result->contacts[0];
    $contactId = $contact->id;
    $contractorId = $contact->contractorId;   // firma tej osoby, jesli jest
    $who = trim(($contact->firstName ?? '') . ' ' . ($contact->lastName ?? ''));
    echo "Dzwoni: {$who} (kontakt #{$contactId}).\n";
} elseif (count($result->contacts) > 1) {
    // Krok 4: wieloznacznie - ten sam numer u kilku osob. Nie zgaduj.
    echo "Numer {$result->number} pasuje do " . count($result->contacts) . " kontaktow:\n";
    foreach ($result->contacts as $c) {
        $name = trim(($c->firstName ?? '') . ' ' . ($c->lastName ?? ''));
        echo "  - {$name} (kontakt #{$c->id})\n";
    }
    // Oddaj liste uzytkownikowi do wskazania wlasciwej osoby; zostaw contactId null.
} elseif (count($result->contractors) === 1) {
    // Brak kontaktu, ale numer pasuje do kartoteki firmy wprost.
    $contractor = $result->contractors[0];
    $contractorId = $contractor->id;
    echo "Numer firmowy: {$contractor->name} (kontrahent #{$contractorId}).\n";
}

// contactId / contractorId sa teraz gotowe do przekazania przy zapisie
// rozmowy albo SMS (patrz phone-calls / text-messages), o ile ustalono
// jednoznacznie. Przy wieloznacznosci zostaja null i zapis idzie bez powiazania.
```

Co zwrocic uzytkownikowi: kto dzwoni (imie i nazwisko albo nazwa firmy) i id
rekordu; przy wieloznacznosci - liste kandydatow do wskazania; przy pustym
wyniku - informacje, ze numer nieznany.

## Warianty

### Lookup jako wstep do zapisu rozmowy

Najczestszy uklad: rozpoznaj numer, potem zapisz rozmowe z gotowym powiazaniem.
Pelny kod zapisu jest w playbooku [phone-calls](../phone-calls/README.md); tu
tylko styk:

```php
$lookup = $client->lookup()->phone($remoteNumber);
$contactId = count($lookup->contacts) === 1 ? $lookup->contacts[0]->id : null;
$contractorId = $contactId !== null
    ? $lookup->contacts[0]->contractorId
    : (count($lookup->contractors) === 1 ? $lookup->contractors[0]->id : null);
// ... przekaz contactId i contractorId do PhoneCallInput
```

### Numer alternatywny

Kontakt moze trafic na liste przez numer alternatywny, nie glowny. Gdy chcesz
wiedziec, ktore pole pasowalo, porownaj znormalizowany `$result->number`
z `phone` i `phoneAlternative` kontaktu - ale do przypiecia rozmowy i tak
uzywasz tylko `contactId`, wiec zwykle to nieistotne.

## Pulapki

- **To odczyt - nic nie zapisuje.** Lookup nie zaklada kontaktu ani rozmowy;
  sluzy wylacznie do rozpoznania. Zapis robisz osobno.
- **Wynik bywa wieloznaczny.** Ten sam numer moze byc u kilku osob (rodzina,
  wspolna komorka firmowa). Przy wielu kontaktach NIE wybieraj pierwszego z
  brzegu - to zlamanie zlotej zasady. Oddaj liste do decyzji albo zapisz
  rozmowe bez `contactId`.
- **Numer normalizowany po stronie API.** Podajesz numer w dowolnej postaci,
  ale wynik `->number` jest w kanonie miedzynarodowym - pokaz go uzytkownikowi,
  zeby bylo jasne, na czym faktycznie szukano.
- **Kontakt vs kontrahent to dwie osobne listy.** Numer moze pasowac tylko do
  firmy (`contractors`) bez zadnego kontaktu, tylko do osoby (`contacts`), albo
  do obu. Sprawdzaj obie.
- **`contactId` bierz z pola `id`, `contractorId` z `contact->contractorId`
  albo `contractor->id`.** Nie myl id kontaktu z id kartoteki - to rozne
  przestrzenie identyfikatorow.
- **Pusty wynik to nie blad.** Nieznany numer zwraca puste listy, nie wyjatek.
  Obsluz go jako osobny przypadek (mozna zaproponowac zalozenie kontaktu).
