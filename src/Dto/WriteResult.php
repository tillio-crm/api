<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

use TillioCrm\Api\ApiResponse;

/**
 * Wynik pojedynczego zapisu (POST/PUT) w kontrakcie v2.
 *
 * NAJWAŻNIEJSZE: v2 na POST z włączonym `duplicateCheck` odpowiada **200
 * z istniejącym rekordem** zamiast 201 - i NIE zmienia jego danych. Bez tego
 * rozróżnienia integracja uznałaby "znaleziono duplikat" za "utworzono"
 * i raportowała fałszywe kreacje przy każdym przebiegu. Dlatego `created`
 * bierzemy z `info.created`, a duplikat z `info.duplicate` - oba wyciągnięte
 * na wierzch.
 *
 * `info.ids` nie ma stałego zestawu kluczy: obok klucza głównego encji bywają
 * tam id rekordów utworzonych PRZY OKAZJI (`noteId` notatki systemowej,
 * `contactId` osoby kontaktowej przy zakładaniu kontrahenta). Całość zostaje
 * w `$ids`.
 *
 * `warnings` (`info.warnings`) niosą wartości ODRZUCONE przy normalizacji
 * (z oryginalnym wejściem) i ostrzeżenia `duplicateCheck` o pominiętych
 * warunkach - czytaj je i podnoś wyżej, to jedyny ślad po cichych korektach.
 */
final readonly class WriteResult
{
    /**
     * @param int                  $status    201 = utworzono, 200 = znaleziono/zaktualizowano
     * @param bool                 $created   czy powstał NOWY rekord
     * @param int|null             $id        id głównego rekordu operacji
     * @param DuplicateMatch|null  $duplicate znaleziony duplikat (null = brak)
     * @param array<string, mixed> $data      zapisany (albo znaleziony) rekord
     * @param array<string, mixed> $ids       `info.ids` - wszystkie id dotknięte zapisem
     * @param array<string, mixed> $warnings  `info.warnings` - ciche korekty i pominięte warunki
     */
    public function __construct(
        public int $status,
        public bool $created,
        public ?int $id,
        public ?DuplicateMatch $duplicate,
        public array $data,
        public array $ids = [],
        public array $warnings = [],
    ) {
    }

    /**
     * @param string $idKey nazwa klucza id w `info.ids` (contractorId, productId...);
     *                      fallback na `data.id`, bo nie każdy endpoint niesie `ids`
     */
    public static function fromResponse(ApiResponse $response, string $idKey): self
    {
        $info = $response->info();
        /** @var array<string, mixed> $data */
        $data = $response->data();
        $ids = Cast::map($info['ids'] ?? null);
        $duplicateRow = Cast::mapOrNull($info['duplicate'] ?? null);

        return new self(
            status: $response->status,
            // Brak `info.created` (np. PUT) = "nie utworzono" - 201 i tak wyłapiemy
            // statusem, więc fałszywego pozytywu tu nie będzie.
            created: Cast::bool($info['created'] ?? null) ?? ($response->status === 201),
            id: Cast::int($ids[$idKey] ?? ($data['id'] ?? null)),
            duplicate: $duplicateRow === null ? null : DuplicateMatch::fromArray($duplicateRow),
            data: $data,
            ids: $ids,
            warnings: Cast::map($info['warnings'] ?? null),
        );
    }

    /** Czy rekord został znaleziony jako duplikat (200-na-istniejącym), a nie utworzony. */
    public function isDuplicate(): bool
    {
        return !$this->created && $this->duplicate !== null;
    }

    /** Pole, po którym znaleziono duplikat (skrót do `duplicate->matchedBy`). */
    public function matchedBy(): ?string
    {
        return $this->duplicate?->matchedBy;
    }
}
