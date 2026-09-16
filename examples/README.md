# Tillio API v2 SDK - przykład uruchamialny

Minimalny, działający przykład w czystym PHP: sprawdzenie konfiguracji, odczyt
listy kontrahentów i jeden zapis (utworzenie zadania). To punkt startowy -
pełne przykłady, osobno dla każdego zasobu i metod systemowych (24 pliki),
są w [../docs/examples/](../docs/examples/).

## Uruchomienie (klon repozytorium SDK)

1. W katalogu głównym projektu zainstaluj zależności:
   ```bash
   composer install
   ```

2. Skopiuj konfigurację przykładową i uzupełnij danymi swojej instancji:
   ```bash
   cp examples/config.example.php examples/config.php
   ```
   W `examples/config.php` wpisz `apiKey` (format `nazwa:klucz` z panelu Tillio),
   `tenantDomain` i `tenantId`. Plik jest w `.gitignore` - nie trafi do repo.

3. Uruchom przykład:
   ```bash
   php examples/quickstart.php
   ```

## Uruchomienie po instalacji Composerem

Po `composer require tillio-crm/api` ten katalog jest w
`vendor/tillio-crm/api/examples/`. Konfiguracji nie kopiuj do `vendor/`
(`composer update` ją usunie) - trzymaj ją we własnym projekcie i wskaż
zmienną środowiskową `TILLIO_EXAMPLES_CONFIG`:

```bash
cp vendor/tillio-crm/api/examples/config.example.php tillio-config.php
# uzupełnij tillio-config.php, potem:
TILLIO_EXAMPLES_CONFIG=tillio-config.php php vendor/tillio-crm/api/examples/quickstart.php
```

W PowerShell: `$env:TILLIO_EXAMPLES_CONFIG = "tillio-config.php"`, potem
`php vendor/tillio-crm/api/examples/quickstart.php`. Dopisz `tillio-config.php`
do `.gitignore` swojego projektu - plik zawiera sekret.

## Pliki

| Plik | Rola |
|---|---|
| `quickstart.php` | Właściwy przykład: whoami, lista kontrahentów, utworzenie zadania. |
| `bootstrap.php` | Ładuje autoloader i config, zwraca gotowy `TillioClient`. |
| `config.example.php` | Szablon konfiguracji do skopiowania. |
| `config.php` | **Lokalny (gitignored)** - Twój `apiKey` i dane tenanta. |

## Uwagi

- Przykład działa w **trybie bezpośrednim** (własny klucz API). Tryb proxy
  (aplikacja marketplace z tokenem OAuth) różni się tylko konstrukcją klienta -
  patrz główny [README](../README.md).
- Zapis w kroku 3 tworzy realne zadanie w CRM. Uruchamiaj na instancji testowej
  albo usuń sekcję zapisu, jeśli chcesz tylko odczyt.
- Windows / PHP CLI: jeśli żądania HTTPS padają na weryfikacji TLS, odkomentuj
  `caFile` w `config.php` (szczegóły w `config.example.php`).
- Integrujesz przez asystenta AI? Zajrzyj do [../docs/.ai/](../docs/.ai/) -
  playbooki opisują, jak zamieniać polecenia w język naturalny na wywołania SDK.
