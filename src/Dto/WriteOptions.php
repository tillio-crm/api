<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Opcje sterujące zapisem - wspólne dla encji z wyszukiwaniem duplikatów
 * (kontrahenci, kontakty, produkty). Jadą w payloadzie OBOK pól encji.
 *
 *     $client->contractors()->create(
 *         new ContractorInput(name: 'Acme', taxId: '0000000000', contractorTypeId: 1),
 *         new WriteOptions(duplicateCheck: ['taxId']),
 *     );
 *
 * Null = nie wysyłaj opcji (API użyje swojego domyślnego zachowania). Flagi
 * `false` też są wysyłane tylko wtedy, gdy podasz je jawnie - instancje starsze
 * niż dana opcja odbiłyby nieznane pole błędem.
 */
final readonly class WriteOptions implements Arrayable
{
    /**
     * @param list<string>|null $duplicateCheck           pola do wyszukania istniejącego rekordu
     *                                                    (`taxId`, `email`, `custom:<klucz>`...);
     *                                                    KAŻDE wskazane pole musi mieć wartość
     *                                                    w payloadzie - SDK pilnuje tego lokalnie
     * @param bool|null         $allowDuplicates          true = nie szukaj duplikatu, zawsze twórz
     * @param bool|null         $requireDuplicateCheck    true = odmów zapisu, gdy ŻADNEGO pola
     *                                                    domyślnego zestawu nie da się sprawdzić
     * @param string|null       $taxIdLookup              pobranie danych z GUS po NIP (wartość wg spec)
     * @param bool|null         $failOnInvalidTaxId       true = błędny NIP to 422, nie ostrzeżenie
     * @param bool|null         $createSystemNote         notatka systemowa przy założeniu kontrahenta
     * @param bool|null         $createContractorContacts osoba kontaktowa z email/phone przy założeniu
     */
    public function __construct(
        public ?array $duplicateCheck = null,
        public ?bool $allowDuplicates = null,
        public ?bool $requireDuplicateCheck = null,
        public ?string $taxIdLookup = null,
        public ?bool $failOnInvalidTaxId = null,
        public ?bool $createSystemNote = null,
        public ?bool $createContractorContacts = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'duplicateCheck' => $this->duplicateCheck,
            'allowDuplicates' => $this->allowDuplicates,
            'requireDuplicateCheck' => $this->requireDuplicateCheck,
            'taxIdLookup' => $this->taxIdLookup,
            'failOnInvalidTaxId' => $this->failOnInvalidTaxId,
            'createSystemNote' => $this->createSystemNote,
            'createContractorContacts' => $this->createContractorContacts,
        ]);
    }
}
