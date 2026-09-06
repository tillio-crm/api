<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Stan integracji Tillio Calls w instancji. Klucz API NIGDY nie jest zwracany
 * (tylko flaga hasApiKey). Dostepne od wersji API 2.11.0.
 */
final readonly class TillioCallsIntegration
{
    /**
     * @param array<string, mixed> $raw pelny rekord z API
     */
    public function __construct(
        public ?bool $registered,
        public ?string $apiUrl,
        public ?bool $hasApiKey,
        public ?int $providerId,
        public ?int $configId,
        public array $raw = [],
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            registered: Cast::bool($row['registered'] ?? null),
            apiUrl: Cast::string($row['apiUrl'] ?? null),
            hasApiKey: Cast::bool($row['hasApiKey'] ?? null),
            providerId: Cast::int($row['providerId'] ?? null),
            configId: Cast::int($row['configId'] ?? null),
            raw: $row,
        );
    }

    /**
     * Pelny, surowy rekord z API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->raw;
    }
}
