<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Kontrahent (odczyt) - pola wg kanonu 2.0.0 (`ownerUserId`, pola słownikowe
 * z nazwą słownika: `contractorStatusId`, `contractorSourceId`...).
 *
 * `revenue` i inne kwoty są STRINGAMI dziesiętnymi - patrz {@see Cast}.
 * Nieznane pola odpowiedzi nie giną: pełny payload w `$raw`.
 */
final readonly class Contractor
{
    /**
     * @param array<string, mixed> $customField wartości pól niestandardowych: klucz => wartość
     * @param list<Address>        $address     LISTA adresów (każdy z addressTypeId)
     * @param array<string, mixed> $raw         pełny rekord z API
     */
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $alias,
        public ?string $fullName,
        public ?string $taxId,
        public ?string $regon,
        public ?string $pesel,
        public ?string $email,
        public ?string $phone,
        public ?string $domain,
        public ?string $country,
        public ?string $externalId,
        public ?string $note,
        public ?int $contractorTypeId,
        public ?int $contractorStatusId,
        public ?int $industryId,
        public ?int $contractorSourceId,
        public ?int $contractorPriorityId,
        public ?int $paymentTypeId,
        public ?int $legalFormId,
        public ?int $ownerUserId,
        public ?int $parentId,
        public ?int $employeesCount,
        public ?string $revenue,
        public ?string $revenueCurrency,
        public ?string $createdAt,
        public ?string $updatedAt,
        public ?string $lastActivityAt,
        public array $customField,
        public array $address,
        public ?string $url,
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
            alias: Cast::string($row['alias'] ?? null),
            fullName: Cast::string($row['fullName'] ?? null),
            taxId: Cast::string($row['taxId'] ?? null),
            regon: Cast::string($row['regon'] ?? null),
            pesel: Cast::string($row['pesel'] ?? null),
            email: Cast::string($row['email'] ?? null),
            phone: Cast::string($row['phone'] ?? null),
            domain: Cast::string($row['domain'] ?? null),
            country: Cast::string($row['country'] ?? null),
            externalId: Cast::string($row['externalId'] ?? null),
            note: Cast::string($row['note'] ?? null),
            contractorTypeId: Cast::int($row['contractorTypeId'] ?? null),
            contractorStatusId: Cast::int($row['contractorStatusId'] ?? null),
            industryId: Cast::int($row['industryId'] ?? null),
            contractorSourceId: Cast::int($row['contractorSourceId'] ?? null),
            contractorPriorityId: Cast::int($row['contractorPriorityId'] ?? null),
            paymentTypeId: Cast::int($row['paymentTypeId'] ?? null),
            legalFormId: Cast::int($row['legalFormId'] ?? null),
            ownerUserId: Cast::int($row['ownerUserId'] ?? null),
            parentId: Cast::int($row['parentId'] ?? null),
            employeesCount: Cast::int($row['employeesCount'] ?? null),
            revenue: Cast::string($row['revenue'] ?? null),
            revenueCurrency: Cast::string($row['revenueCurrency'] ?? null),
            createdAt: Cast::string($row['createdAt'] ?? null),
            updatedAt: Cast::string($row['updatedAt'] ?? null),
            lastActivityAt: Cast::string($row['lastActivityAt'] ?? null),
            customField: Cast::map($row['customField'] ?? null),
            address: array_map(Address::fromArray(...), Cast::rows($row['address'] ?? null)),
            url: Cast::string($row['url'] ?? null),
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
