<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Połączenie do zapisu. Przy tworzeniu API wymaga source, sourceId, direction,
 * status, remoteNumber, startedAt. Dostępne od wersji API 2.10.0.
 *
 * `sentiment` i `transcription` (API >= 2.18.0) typowo dojeżdżają drugim doręczeniem,
 * `update()` po analizie AI - razem z `summary`. Wyczyszczenie sentymentu albo
 * transkrypcji to jawny null w tablicowym fallbacku: `update($id, ['sentiment' => null])`
 * (null w tym DTO znaczy "nie wysyłaj", patrz {@see Arrayable}).
 */
final readonly class PhoneCallInput implements Arrayable
{
    public const string SENTIMENT_NEGATIVE = 'negative';
    public const string SENTIMENT_NEUTRAL = 'neutral';
    public const string SENTIMENT_POSITIVE = 'positive';

    /**
     * @param 'inbound'|'outbound'|string|null                            $direction kierunek rozmowy
     * @param 'answered'|'missed'|'busy'|'voicemail'|'failed'|string|null $status    status rozmowy
     * @param int|null                                                    $duration  czas trwania w sekundach.
     *        Przy statusie 'answered' PODAWAJ ZAWSZE: od API 2.12.0 rozmowa odebrana bez czasu (albo
     *        z zerem) zapisuje się z ostrzeżeniem zamiast błędu 422, ale raport VoIP liczy odebrane
     *        po czasie rozmowy - bez duration wypada z zestawień
     * @param string|null                                                 $summary   podsumowanie rozmowy (HTML)
     * @param int|null                                                    $creatorUserId tylko przy tworzeniu
     * @param 'negative'|'neutral'|'positive'|string|null                 $sentiment sentyment z analizy AI
     *        (API >= 2.18.0) - CRM pokazuje go przy notatce „Telefon", także gdy przyszedł w `update()`
     *        po zapisaniu rozmowy. Skalę 1-3 przeliczy {@see sentimentFromLevel()}. Inna wartość = 422
     * @param list<PhoneCallTranscriptSegment|array<string, mixed>>|null $transcription transkrypcja
     *        (API >= 2.18.0): wypowiedzi w kolejności rozmowy; w `update()` zastępuje poprzednią w całości.
     *        Limity API: 5000 wypowiedzi, 10 000 znaków na wypowiedź, 500 000 łącznie. Pusta lista = 422
     */
    public function __construct(
        public ?string $source = null,
        public ?string $sourceId = null,
        public ?string $direction = null,
        public ?string $status = null,
        public ?string $remoteNumber = null,
        public ?string $ownNumber = null,
        public ?string $startedAt = null,
        public ?int $duration = null,
        public ?int $userId = null,
        public ?int $contactId = null,
        public ?int $contractorId = null,
        public ?string $title = null,
        public ?string $summary = null,
        public ?string $tldr = null,
        public ?string $recordingCallId = null,
        public ?string $callsUrl = null,
        public ?int $creatorUserId = null,
        public ?string $sentiment = null,
        public ?array $transcription = null,
    ) {
    }

    /**
     * Sentyment ze skali 1-3 (Tillio AI `sentiment_lvl`, słownik CRM): 1 = negatywny,
     * 2 = neutralny, 3 = pozytywny. Brak albo wartość spoza skali = null (pole nie pójdzie).
     */
    public static function sentimentFromLevel(int|string|null $level): ?string
    {
        return match (is_numeric($level) ? (int) $level : null) {
            1 => self::SENTIMENT_NEGATIVE,
            2 => self::SENTIMENT_NEUTRAL,
            3 => self::SENTIMENT_POSITIVE,
            default => null,
        };
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'source' => $this->source,
            'sourceId' => $this->sourceId,
            'direction' => $this->direction,
            'status' => $this->status,
            'remoteNumber' => $this->remoteNumber,
            'ownNumber' => $this->ownNumber,
            'startedAt' => $this->startedAt,
            'duration' => $this->duration,
            'userId' => $this->userId,
            'contactId' => $this->contactId,
            'contractorId' => $this->contractorId,
            'title' => $this->title,
            'summary' => $this->summary,
            'tldr' => $this->tldr,
            'recordingCallId' => $this->recordingCallId,
            'callsUrl' => $this->callsUrl,
            'creatorUserId' => $this->creatorUserId,
            'sentiment' => $this->sentiment,
            'transcription' => $this->transcription === null ? null : PhoneCallTranscriptSegment::listPayload($this->transcription),
        ]);
    }
}
