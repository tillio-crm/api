<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Powód zmiany statusu (odczyt) - wspólny kształt dla powodów leadowych
 * (`GET /v2/lead/status-change-reasons`) i szans sprzedaży
 * (`GET /v2/pipeline/status-change-reasons`). Dostępne od wersji API 2.15.0.
 *
 * Powody zakłada się w panelu CRM - API przyjmuje wyłącznie ich id. Pola spoza
 * danego słownika przychodzą jako null i zostają w `$raw`: lead ma
 * `leadStatusId`, szansa `pipelineStatusId` i `pipelineFunnelId` (null =
 * powód wspólny dla wszystkich lejków, `isDefault`).
 */
final readonly class StatusChangeReason
{
    /**
     * @param bool|null            $noteRequired     zmiana z tym powodem wymaga notatki (inaczej 422)
     * @param int|null             $leadStatusId     status leada, do którego należy powód
     * @param int|null             $pipelineStatusId status szansy (2 = stracona, 3 = wygrana)
     * @param int|null             $pipelineFunnelId lejek powodu; null = powód wspólny
     * @param bool|null            $isDefault        powód wspólny (systemowy) dla wszystkich lejków
     * @param array<string, mixed> $raw              pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $name,
        public ?bool $active,
        public ?bool $noteRequired,
        public ?int $leadStatusId,
        public ?int $pipelineStatusId,
        public ?int $pipelineFunnelId,
        public ?bool $isDefault,
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
            active: Cast::bool($row['active'] ?? null),
            noteRequired: Cast::bool($row['noteRequired'] ?? null),
            leadStatusId: Cast::int($row['leadStatusId'] ?? null),
            pipelineStatusId: Cast::int($row['pipelineStatusId'] ?? null),
            pipelineFunnelId: Cast::int($row['pipelineFunnelId'] ?? null),
            isDefault: Cast::bool($row['isDefault'] ?? null),
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
