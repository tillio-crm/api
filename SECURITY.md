# Polityka bezpieczeństwa

## Zgłaszanie podatności

Podatności bezpieczeństwa zgłaszaj PRYWATNIE - nie przez publiczne issue:

- e-mail: pomoc@tillio.pl (temat zaczynający się od `[SECURITY]`),
- albo prywatne zgłoszenie na GitHubie (Security -> Report a vulnerability).

Opisz wersję paczki, sposób odtworzenia i potencjalny skutek. Odpowiadamy
najpóźniej w 5 dni roboczych; poprawka bezpieczeństwa ma pierwszeństwo przed
innymi pracami.

## Wspierane wersje

Poprawki bezpieczeństwa trafiają do najnowszego wydania linii 0.x.

## Zakres

Paczka nie przechowuje danych i nie otwiera portów - jest klientem HTTP.
Najwrażliwsze elementy to obsługa klucza API/tokenu (nagłówki budowane per
żądanie, token nie wycieka do hostów spoza konfiguracji; pobieranie plików
z podpisanych URL-i idzie bez nagłówków uwierzytelniających) oraz weryfikacja
TLS (nie da się jej wyłączyć konfiguracją SDK - klucz `caFile` pozwala tylko
wskazać właściwy zestaw zaufanych CA).
