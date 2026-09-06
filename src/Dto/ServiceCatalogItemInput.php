<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Pozycja katalogu usług do zapisu. Przy tworzeniu API wymaga `name` i `groupId`.
 * `defaultPayValue` - ta sama nazwa co w odczycie (kanon 2.0.0; wymaga API
 * >= 2.0.4, starsze wydania przyjmowały tu `price`).
 */
final readonly class ServiceCatalogItemInput implements Arrayable
{
    public function __construct(
        public ?string $name = null,
        public ?int $groupId = null,
        public ?string $icon = null,
        public ?string $defaultPayValue = null,
        public ?string $currency = null,
        public ?bool $isAgreement = null,
        public ?bool $active = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'name' => $this->name,
            'groupId' => $this->groupId,
            'icon' => $this->icon,
            'defaultPayValue' => $this->defaultPayValue,
            'currency' => $this->currency,
            'isAgreement' => $this->isAgreement,
            'active' => $this->active,
        ]);
    }
}
