<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Połączenie telefoniczne (odczyt). Ten sam rekord co zakładają integracje VoIP
 * (np. Tillio Calls); z każdą rozmową powstaje notatka na kartotece (noteIds).
 * Dostępne od wersji API 2.10.0.
 */
final readonly class PhoneCall
{
    /**
     * @param 'inbound'|'outbound'|string|null                          $direction      kierunek rozmowy
     * @param 'answered'|'missed'|'busy'|'voicemail'|'failed'|string|null $status        status rozmowy
     * @param list<int>            $contractorIds  powiązane kartoteki kontrahentów
     * @param list<int>            $noteIds        notatki powstałe z rozmowy
     * @param 'negative'|'neutral'|'positive'|string|null $sentiment sentyment z analizy AI;
     *        null = brak analizy (API >= 2.18.0, starsze instancje zawsze null)
     * @param list<PhoneCallTranscriptSegment>|null $transcription transkrypcja - WYŁĄCZNIE z `get()`
     *        i z wyniku `create()`/`update()`; strona listy jej nie niesie (null). API >= 2.18.0
     * @param array<string, mixed> $raw            pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $provider,
        public ?string $source,
        public ?string $sourceId,
        public ?string $direction,
        public ?string $status,
        public ?string $ownNumber,
        public ?string $remoteNumber,
        public ?string $startedAt,
        public ?int $duration,
        public ?int $contactId,
        public array $contractorIds,
        public ?int $userId,
        public ?string $title,
        public ?string $summary,
        public ?string $tldr,
        public ?string $recordingCallId,
        public ?string $callsUrl,
        public array $noteIds,
        public ?string $sentiment,
        public ?array $transcription,
        public array $raw = [],
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            id: Cast::requiredInt($row['id'] ?? null),
            provider: Cast::string($row['provider'] ?? null),
            source: Cast::string($row['source'] ?? null),
            sourceId: Cast::string($row['sourceId'] ?? null),
            direction: Cast::string($row['direction'] ?? null),
            status: Cast::string($row['status'] ?? null),
            ownNumber: Cast::string($row['ownNumber'] ?? null),
            remoteNumber: Cast::string($row['remoteNumber'] ?? null),
            startedAt: Cast::string($row['startedAt'] ?? null),
            duration: Cast::int($row['duration'] ?? null),
            contactId: Cast::int($row['contactId'] ?? null),
            contractorIds: Cast::intList($row['contractorIds'] ?? null),
            userId: Cast::int($row['userId'] ?? null),
            title: Cast::string($row['title'] ?? null),
            summary: Cast::string($row['summary'] ?? null),
            tldr: Cast::string($row['tldr'] ?? null),
            recordingCallId: Cast::string($row['recordingCallId'] ?? null),
            callsUrl: Cast::string($row['callsUrl'] ?? null),
            noteIds: Cast::intList($row['noteIds'] ?? null),
            sentiment: Cast::string($row['sentiment'] ?? null),
            transcription: is_array($row['transcription'] ?? null)
                ? array_map(PhoneCallTranscriptSegment::fromArray(...), Cast::rows($row['transcription']))
                : null,
            raw: $row,
        );
    }

    /**
     * Pełny, surowy rekord z API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->raw;
    }
}
