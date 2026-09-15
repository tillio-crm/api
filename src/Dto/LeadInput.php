<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Lead do zapisu - named arguments, null = nie wysyłaj pola.
 * Przy tworzeniu API wymaga `title`. Opcje zapisu (`duplicateCheck`,
 * `allowDuplicates`) NIE są polami leada - jadą OBOK, w `WriteOptions`.
 */
final readonly class LeadInput implements Arrayable
{
    /**
     * @param list<string>|null         $emails             adresy e-mail, pierwszy = główny (od API 2.13.0, najwyżej 20).
     *                                                      W `create()` lista do założenia - przy podpięciu do
     *                                                      istniejącego leada adresy są DOKŁADANE; w `update()`
     *                                                      KOMPLETNA lista docelowa (`[]` usuwa wszystkie)
     * @param int|null                  $leadStatusId       tylko przy tworzeniu (dalej: proces leadowy)
     * @param int|null                  $contractorSourceId tylko przy tworzeniu
     * @param array<string, mixed>|null $customField        wartości pól niestandardowych
     * @param string|null               $createdAt          data utworzenia przy imporcie historycznym
     * @param int|null                  $creatorUserId      tylko przy tworzeniu
     */
    public function __construct(
        public ?string $title = null,
        public ?string $note = null,
        public ?int $ownerUserId = null,
        public ?int $priority = null,
        public ?string $companyName = null,
        public ?string $taxId = null,
        public ?string $regon = null,
        public ?string $domain = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $position = null,
        public ?string $phone = null,
        public ?string $phoneAlternative = null,
        public ?array $emails = null,
        public ?string $street = null,
        public ?string $street2 = null,
        public ?string $postCode = null,
        public ?string $city = null,
        public ?string $country = null,
        public ?int $leadStatusId = null,
        public ?int $contractorSourceId = null,
        public ?array $customField = null,
        public ?string $createdAt = null,
        public ?int $creatorUserId = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'title' => $this->title,
            'note' => $this->note,
            'ownerUserId' => $this->ownerUserId,
            'priority' => $this->priority,
            'companyName' => $this->companyName,
            'taxId' => $this->taxId,
            'regon' => $this->regon,
            'domain' => $this->domain,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'position' => $this->position,
            'phone' => $this->phone,
            'phoneAlternative' => $this->phoneAlternative,
            'emails' => $this->emails,
            'street' => $this->street,
            'street2' => $this->street2,
            'postCode' => $this->postCode,
            'city' => $this->city,
            'country' => $this->country,
            'leadStatusId' => $this->leadStatusId,
            'contractorSourceId' => $this->contractorSourceId,
            'customField' => $this->customField,
            'createdAt' => $this->createdAt,
            'creatorUserId' => $this->creatorUserId,
        ]);
    }
}
