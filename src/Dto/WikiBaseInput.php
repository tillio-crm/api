<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Baza wiki do zapisu. Wymagane `name`; `type`: `article`/`procedure`.
 */
final readonly class WikiBaseInput implements Arrayable
{
    /**
     * @param 'article'|'procedure'|null $type rodzaj bazy
     */
    public function __construct(
        public ?string $name = null,
        public ?string $type = null,
        public ?string $subtitle = null,
        public ?string $icon = null,
        public ?string $color = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'name' => $this->name,
            'type' => $this->type,
            'subtitle' => $this->subtitle,
            'icon' => $this->icon,
            'color' => $this->color,
        ]);
    }
}
