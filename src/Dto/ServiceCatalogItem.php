<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Pozycja katalogu usług (odczyt) - szablon, z którego zakłada się usługi
 * u kontrahentów. Kwoty jako stringi dziesiętne.
 */
final readonly class ServiceCatalogItem
{
    /**
     * @param array<string, mixed> $raw pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $icon,
        public ?bool $active,
        public ?bool $isAgreement,
        public ?string $defaultPayValue,
        public ?string $currency,
        public ?string $defaultTax,
        public ?int $priority,
        public ?int $groupId,
        public ?string $groupName,
        public ?string $groupColor,
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
            icon: Cast::string($row['icon'] ?? null),
            active: Cast::bool($row['active'] ?? null),
            isAgreement: Cast::bool($row['isAgreement'] ?? null),
            defaultPayValue: Cast::string($row['defaultPayValue'] ?? null),
            currency: Cast::string($row['currency'] ?? null),
            defaultTax: Cast::string($row['defaultTax'] ?? null),
            priority: Cast::int($row['priority'] ?? null),
            groupId: Cast::int($row['groupId'] ?? null),
            groupName: Cast::string($row['groupName'] ?? null),
            groupColor: Cast::string($row['groupColor'] ?? null),
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
