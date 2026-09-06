<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Rola użytkownika do zapisu. Wymagane `name` (max 32 znaki); `permissions` to
 * klucze (albo id) z `GET /v2/user/permissions` - pominięte = rola bez uprawnień.
 */
final readonly class UserRoleInput implements Arrayable
{
    /**
     * @param list<string>|null $permissions klucze uprawnień
     */
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?array $permissions = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'name' => $this->name,
            'description' => $this->description,
            'permissions' => $this->permissions,
        ]);
    }
}
