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
    /**
     * @param string|null                            $email        LOGIN systemowy, unikalny w instancji
     * @param string|null                            $jobTitle     stanowisko; od API 2.16.0 pod tą nazwą
     *                                                             (do 2.15.x `position`)
     * @param string|null                            $contactPhone służbowy telefon do kontaktu; od API
     *                                                             2.16.0 pod tą nazwą (do 2.15.x `phone`).
     *                                                             Numer niepoprawny wg libphonenumber nie
     *                                                             blokuje założenia konta - wraca
     *                                                             w `CreatedUser::$warnings`
     * @param string|null                            $contactEmail służbowy adres e-mail do kontaktu, inny
     *                                                             niż login (od API 2.16.0); adres
     *                                                             niepoprawny wraca w ostrzeżeniach
     * @param 'male'|'female'|'unspecified'|null     $gender       brak = `unspecified`
     */
    public function __construct(
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $email = null,
        public ?string $jobTitle = null,
        public ?string $contactPhone = null,
        public ?string $contactEmail = null,
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
            'jobTitle' => $this->jobTitle,
            'contactPhone' => $this->contactPhone,
            'contactEmail' => $this->contactEmail,
            'gender' => $this->gender,
            'userStatusId' => $this->userStatusId,
            'departmentId' => $this->departmentId,
            'roleId' => $this->roleId,
        ]);
    }
}
