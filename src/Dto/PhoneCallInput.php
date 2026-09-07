<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Połączenie do zapisu. Przy tworzeniu API wymaga source, sourceId, direction,
 * status, remoteNumber, startedAt. Dostępne od wersji API 2.10.0.
 */
final readonly class PhoneCallInput implements Arrayable
{
    /**
     * @param 'inbound'|'outbound'|string|null                            $direction kierunek rozmowy
     * @param 'answered'|'missed'|'busy'|'voicemail'|'failed'|string|null $status    status rozmowy
     * @param int|null                                                    $duration  czas trwania w sekundach.
     *        Przy statusie 'answered' PODAWAJ ZAWSZE: od API 2.12.0 rozmowa odebrana bez czasu (albo
     *        z zerem) zapisuje się z ostrzeżeniem zamiast błędu 422, ale raport VoIP liczy odebrane
     *        po czasie rozmowy - bez duration wypada z zestawień
     * @param string|null                                                 $summary   podsumowanie rozmowy (HTML)
     * @param int|null                                                    $creatorUserId tylko przy tworzeniu
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
    ) {
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
        ]);
    }
}
