<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Jedna wypowiedź transkrypcji rozmowy - w zapisie (`PhoneCallInput::$transcription`)
 * i w odczycie (`PhoneCall::$transcription`). Dostępne od wersji API 2.18.0.
 *
 * Ten sam kształt w obie strony, więc jedna klasa: `toArray()` daje payload żądania
 * (bez `endSec`, gdy go nie ma), `fromArray()` czyta wypowiedź z odpowiedzi.
 */
final readonly class PhoneCallTranscriptSegment implements Arrayable
{
    /** Pracownik - użytkownik CRM, którego jest rozmowa. */
    public const string SPEAKER_AGENT = 'agent';

    /** Rozmówca po drugiej stronie. */
    public const string SPEAKER_CLIENT = 'client';

    /**
     * @param 'agent'|'client'|string|null $speaker  kto mówi; w odczycie `null`, gdy CRM nie wie,
     *                                               która strona jest klientem (część rozmów
     *                                               z integracji natywnych)
     * @param int|float                    $startSec początek wypowiedzi w sekundach od startu rozmowy
     *                                               (API zaokrągla ułamki)
     * @param int|float|null               $endSec   koniec wypowiedzi; brak = do początku następnej
     * @param string                       $text     treść; API usuwa znaczniki HTML, limit 10 000 znaków
     */
    public function __construct(
        public ?string $speaker,
        public int|float $startSec,
        public string $text,
        public int|float|null $endSec = null,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            speaker: Cast::string($row['speaker'] ?? null),
            startSec: Cast::int($row['startSec'] ?? null) ?? 0,
            text: Cast::string($row['text'] ?? null) ?? '',
            endSec: Cast::int($row['endSec'] ?? null),
        );
    }

    /**
     * Lista wypowiedzi (DTO albo tablice) na payload żądania.
     *
     * @param list<self|array<string, mixed>> $segments
     *
     * @return list<array<string, mixed>>
     *
     * @internal
     */
    public static function listPayload(array $segments): array
    {
        return array_map(
            static fn (self|array $segment): array => $segment instanceof self ? $segment->toArray() : $segment,
            array_values($segments),
        );
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'speaker' => $this->speaker,
            'startSec' => $this->startSec,
            'endSec' => $this->endSec,
            'text' => $this->text,
        ]);
    }
}
