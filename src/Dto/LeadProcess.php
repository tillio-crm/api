<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Proces leadowy (odczyt) - z zagnieżdżonymi statusami (`type`:
 * `default|qualified|disqualified`). To odpowiedź `GET /v2/lead/statuses`.
 *
 * API zwraca pole `isActive`, a przy zapisie przyjmuje `active`
 * ({@see LeadProcessInput}) - to kontrakt, nie literówka. Podobna asymetria:
 * odczyt niesie statusy w polu `statuses`, a zapis w {@see LeadProcessInput}
 * przyjmuje je jako `stages`.
 */
final readonly class LeadProcess
{
    /**
     * @param list<ProcessStage>   $statuses statusy procesu
     * @param array<string, mixed> $raw      pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $name,
        public ?bool $isActive,
        public array $statuses,
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
            statuses: array_map(ProcessStage::fromArray(...), Cast::rows($row['statuses'] ?? null)),
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
