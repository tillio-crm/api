<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Nowy użytkownik (`POST /v2/users`). API wymaga `firstName`, `email`,
 * `userStatusId` i `roleId` (role: `GET /v2/user/roles`, statusy:
 * `GET /v2/user/statuses`, działy: `GET /v2/user/departments`).
 *
 * UWAGA licencyjna: konta AKTYWNE liczą się do limitu instancji
 * (409 `user.limitReached`); konta zakładane jako nieaktywne - nie.
 */
final readonly class UserInput implements Arrayable
{
    public function __construct(
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $email = null,
        public ?string $position = null,
        public ?string $phone = null,
        public ?string $gender = null,
        public ?int $userStatusId = null,
        public ?int $departmentId = null,
        public ?int $roleId = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'email' => $this->email,
            'position' => $this->position,
            'phone' => $this->phone,
            'gender' => $this->gender,
            'userStatusId' => $this->userStatusId,
            'departmentId' => $this->departmentId,
            'roleId' => $this->roleId,
        ]);
    }
}
