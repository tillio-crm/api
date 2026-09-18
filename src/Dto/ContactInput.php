<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Osoba kontaktowa do zapisu - named arguments, null = nie wysyłaj pola.
 * Przy tworzeniu API wymaga `firstName`.
 *
 *     new ContactInput(firstName: 'Jan', lastName: 'Kowalski', contractorId: 42)
 */
final readonly class ContactInput implements Arrayable
{
    /**
     * @param int|null                  $contractorId  dopina kontakt do kartoteki i ustawia ją jako GŁÓWNĄ;
     *                                                 dotychczasowe powiązania zostają, schodzą niżej
     * @param list<int>|null            $contractorIds ZASTĘPUJE całą listę kartotek kontaktu (pierwsza = główna,
     *                                                 od API 2.10.0). Kartoteka usunięta z listy traci powiązanie
     *                                                 kontaktu ze swoimi szansami i zgłoszeniami; pusta lista = 422
     *                                                 (API nie odpina kontaktu od wszystkich kontrahentów)
     * @param array<string, mixed>|null $customField   wartości pól niestandardowych
     * @param string|null               $createdAt     data utworzenia przy imporcie historycznym
     * @param int|null                  $creatorUserId tylko przy tworzeniu (import historii)
     */
    public function __construct(
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $position = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $phoneAlternative = null,
        public ?string $note = null,
        public ?int $contactStatusId = null,
        public ?int $ownerUserId = null,
        public ?string $externalId = null,
        public ?int $contractorId = null,
        public ?array $contractorIds = null,
        public ?array $customField = null,
        public ?string $createdAt = null,
        public ?int $creatorUserId = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'position' => $this->position,
            'email' => $this->email,
            'phone' => $this->phone,
            'phoneAlternative' => $this->phoneAlternative,
            'note' => $this->note,
            'contactStatusId' => $this->contactStatusId,
            'ownerUserId' => $this->ownerUserId,
            'externalId' => $this->externalId,
            'contractorId' => $this->contractorId,
            'contractorIds' => $this->contractorIds,
            'customField' => $this->customField,
            'createdAt' => $this->createdAt,
            'creatorUserId' => $this->creatorUserId,
        ]);
    }
}
