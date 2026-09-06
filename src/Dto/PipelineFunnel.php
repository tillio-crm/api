<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Lejek sprzedaży (odczyt) - z zagnieżdżonymi etapami (`probability` per etap).
 *
 * API zwraca pole `isActive`, a przy zapisie przyjmuje `active`
 * ({@see PipelineFunnelInput}) - to kontrakt, nie literówka.
 */
final readonly class PipelineFunnel
{
    /**
     * @param list<ProcessStage>   $stages etapy lejka
     * @param array<string, mixed> $raw    pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $name,
        public ?bool $isActive,
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
