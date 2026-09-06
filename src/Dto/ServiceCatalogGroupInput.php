<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Grupa katalogu usług do zapisu. Przy tworzeniu API wymaga `name`.
 */
final readonly class ServiceCatalogGroupInput implements Arrayable
{
    public function __construct(
        public ?string $name = null,
        public ?int $parentId = null,
        public ?string $color = null,
        public ?string $icon = null,
        public ?int $order = null,
        public ?bool $active = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'name' => $this->name,
            'parentId' => $this->parentId,
            'color' => $this->color,
            'icon' => $this->icon,
            'order' => $this->order,
            'active' => $this->active,
        ]);
    }
}
