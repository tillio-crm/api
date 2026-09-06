<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Wspólny kontrakt input-DTO: zamiana na payload żądania.
 *
 * `toArray()` POMIJA pola o wartości null - null w input-DTO znaczy "nie wysyłaj
 * pola", nie "wyczyść pole". Kto chce jawnie wysłać null (wyczyścić wartość,
 * np. `externalId` przy odpinaniu integracji), używa tablicowego fallbacku metod
 * zapisu - to świadoma, udokumentowana furtka.
 *
 * DTO ODCZYTU mają własne `toArray()` o innej semantyce: zwracają pełny, surowy
 * rekord z API, razem z nullami. Zbieżność nazw jest świadoma.
 */
interface Arrayable
{
    /**
     * Payload żądania - bez pól o wartości null.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
