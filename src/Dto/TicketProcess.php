<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Proces obsługi zgłoszeń (odczyt) - z zagnieżdżonymi etapami.
 * `resolutionTimeMinutes` = SLA w minutach (0 = brak).
 *
 * API zwraca pole `isActive`, a przy zapisie przyjmuje `active`
 * ({@see TicketProcessInput}) - to kontrakt, nie literówka.
 */
final readonly class TicketProcess
{
    /**
     * @param list<ProcessStage>   $stages etapy procesu
     * @param array<string, mixed> $raw    pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $name,
        public ?bool $isActive,
        public ?int $resolutionTimeMinutes,
        public ?int $autoCloseTimeMinutes,
        public ?bool $autoTicket,
        public array $stages,
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
            name: Cast::string($row['name'] ?? null),
            isActive: Cast::bool($row['isActive'] ?? null),
            resolutionTimeMinutes: Cast::int($row['resolutionTimeMinutes'] ?? null),
            autoCloseTimeMinutes: Cast::int($row['autoCloseTimeMinutes'] ?? null),
            autoTicket: Cast::bool($row['autoTicket'] ?? null),
            stages: array_map(ProcessStage::fromArray(...), Cast::rows($row['stages'] ?? null)),
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
