<?php

declare(strict_types=1);

namespace TillioCrm\Api\Tests;

use PHPUnit\Framework\TestCase;

/**
 * PRZENOŚNOŚĆ SDK - wymóg twardy, egzekwowany mechanicznie.
 *
 * Paczka jest publiczna i ma zero zależności runtime: `src/` nie może znać niczego
 * spoza własnego namespace'u (poza klasami wbudowanymi PHP/SPL). Przegląd kodu tego
 * nie łapie - jeden `use` dołożony "na chwilę" przy debugowaniu przechodzi review
 * i wychodzi dopiero u konsumenta, u którego tej klasy nie ma. Dlatego skan plików,
 * nie konwencja.
 */
final class PortabilityTest extends TestCase
{
    private const string NAMESPACE_PREFIX = 'TillioCrm\\Api\\';

    /**
     * Rośnie razem z paczką. Strażnik przed "testem, który przechodzi pusto":
     * gdyby ktoś przeniósł źródła w inne miejsce, skan nie znalazłby nic i wszystkie
     * asercje byłyby prawdziwe na pustym zbiorze.
     */
    private const int MIN_FILES = 30;

    /**
     * Nazwy, których w kodzie paczki być nie może - każda to ukryte wejście na świat
     * (konfiguracja wyłącznie przez konstruktor, sieć wyłącznie przez transport).
     */
    private const array FORBIDDEN_TOKENS = [
        'env', 'getenv', 'curl_init', 'curl_exec', 'file_get_contents', 'fopen',
        '$_ENV', '$_SERVER', '$_GET', '$_POST', '$_REQUEST', '$_COOKIE', '$_FILES',
    ];

    /** Jedyny plik, który z definicji rozmawia z siecią i czyta pliki (multipart). */
    private const string NETWORK_FILE = 'CurlTransport.php';

    /** @return list<string> ścieżki wszystkich plików PHP w src/ */
    private function sourceFiles(): array
    {
        $srcDir = dirname(__DIR__) . '/src';
        self::assertDirectoryExists($srcDir);

        $files = [];
        /** @var \SplFileInfo $file */
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($srcDir)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        self::assertGreaterThanOrEqual(
            self::MIN_FILES,
            count($files),
            'Skan nie znalazł źródeł - jeżeli pliki się przeniosły, zaktualizuj test, a nie licz na pusty sukces.',
        );

        return $files;
    }

    public function testNoImportsOutsideOwnNamespace(): void
    {
        $foreign = [];
        foreach ($this->sourceFiles() as $path) {
            $source = (string) file_get_contents($path);
            preg_match_all('/^use\s+(?:(function|const)\s+)?([^;]+);/m', $source, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $kind = $match[1];
                $used = trim($match[2]);
                // Aliasy (`use X as Y`) i importy grupowe sprawdzamy po nazwie bazowej.
                $used = trim((string) preg_replace('/\s+as\s+\w+$/i', '', $used));

                if (str_starts_with($used, self::NAMESPACE_PREFIX)) {
                    continue;
                }

                // Wbudowane PHP/SPL są dozwolone - wszystko inne jest obcą zależnością.
                if ($kind === '' && $this->isPhpBuiltin($used)) {
                    continue;
                }

                $foreign[] = basename($path) . ': use ' . ($kind !== '' ? $kind . ' ' : '') . $used;
            }
        }

        self::assertSame([], $foreign, 'Import spoza TillioCrm\\Api w src/ - paczka ma być przenośna bez żadnych zależności.');
    }

    public function testNoForeignNamespaceInSourceDirectory(): void
    {
        // Plik podrzucony do src/ pod cudzym namespace'em jest tak samo nieprzenośny
        // jak obcy import - tyle że bez ani jednego `use`, więc test wyżej by go przepuścił.
        $strangers = [];
        foreach ($this->sourceFiles() as $path) {
            preg_match('/^namespace\s+([^;]+);/m', (string) file_get_contents($path), $match);
            $namespace = trim($match[1] ?? '') . '\\';
            if (!str_starts_with($namespace, self::NAMESPACE_PREFIX)) {
                $strangers[] = basename($path) . ': ' . $namespace;
            }
        }

        self::assertSame([], $strangers, 'Obcy namespace w src/.');
    }

    public function testWorldAccessOnlyThroughTransport(): void
    {
        // Skanujemy TOKENY, nie tekst pliku: komentarze paczki opisują wprost, czego
        // tu nie ma ("konfiguracja nie przez env()"), więc szukanie po surowym źródle
        // wywalałoby test na własnej dokumentacji.
        $violations = [];
        foreach ($this->sourceFiles() as $path) {
            if (basename($path) === self::NETWORK_FILE) {
                continue;
            }

            foreach (token_get_all((string) file_get_contents($path)) as $token) {
                if (!is_array($token)) {
                    continue;
                }
                if (in_array($token[0], [T_STRING, T_VARIABLE], true)
                    && in_array($token[1], self::FORBIDDEN_TOKENS, true)
                ) {
                    $violations[] = basename($path) . ': ' . $token[1];
                }
            }
        }

        self::assertSame([], $violations, 'Ukryte wejście na świat poza transportem.');
    }

    /**
     * Czy nazwa wskazuje klasę/interfejs/enum wbudowany w PHP albo rozszerzenie
     * z twardych wymagań paczki (SPL, curl, json). Autoload celowo wyłączony -
     * pytamy tylko o to, co silnik PHP zna sam z siebie.
     */
    private function isPhpBuiltin(string $name): bool
    {
        if (!class_exists($name, false) && !interface_exists($name, false) && !enum_exists($name, false)) {
            return false;
        }

        $reflection = new \ReflectionClass($name);

        return $reflection->isInternal();
    }
}
