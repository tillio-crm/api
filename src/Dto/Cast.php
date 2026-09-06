<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Rzutowanie surowych wartości z odpowiedzi API na typy DTO.
 *
 * DLACZEGO defensywnie: DTO budujemy z JSON-a, który zmienia się niezależnie od
 * SDK (nowe wydania API). Pole, które zniknie albo zmieni typ, ma dać `null`
 * w DTO - nie `TypeError` w środku przebiegu. Pełna, niezinterpretowana
 * odpowiedź i tak zostaje w `$raw`.
 *
 * KWOTY ZOSTAJĄ STRINGAMI. API oddaje wartości pieniężne jako stringi dziesiętne
 * (`"1999.90"`) i DTO to szanuje - rzutowanie na float gubi grosze przy dużych
 * kwotach. Rzutuj świadomie po swojej stronie, jeżeli naprawdę chcesz float.
 *
 * @internal używane przez DTO tej paczki; nie jest częścią publicznego API
 */
final class Cast
{
    private function __construct()
    {
    }

    public static function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    /** Pole, które kontrakt deklaruje jako zawsze obecne (np. `id`); brak = 0, nie wyjątek. */
    public static function requiredInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    public static function string(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    /** String z pustką znormalizowaną do null (np. `publicId` - pusty string to brak identyfikatora). */
    public static function nonEmptyString(mixed $value): ?string
    {
        $string = self::string($value);

        return $string === '' ? null : $string;
    }

    /** Pole, które kontrakt deklaruje jako zawsze obecne (np. `name`); brak = ''. */
    public static function requiredString(mixed $value): string
    {
        return self::string($value) ?? '';
    }

    public static function bool(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        return is_scalar($value) ? (bool) $value : null;
    }

    public static function float(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Mapa klucz => wartość (np. `customField`); wszystko inne = pusta mapa.
     *
     * @return array<string, mixed>
     */
    public static function map(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $map = [];
        foreach ($value as $key => $item) {
            $map[(string) $key] = $item;
        }

        return $map;
    }

    /**
     * Lista identyfikatorów (np. `assignedUserIds`).
     *
     * @return list<int>
     */
    public static function intList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $item) {
            if (is_numeric($item)) {
                $ids[] = (int) $item;
            }
        }

        return $ids;
    }

    /**
     * Mapa klucz => wartość albo null, gdy pola nie ma w odpowiedzi
     * (zagnieżdżone rekordy opcjonalne, np. `info.duplicate`).
     *
     * @return array<string, mixed>|null
     */
    public static function mapOrNull(mixed $value): ?array
    {
        return is_array($value) ? self::map($value) : null;
    }

    /**
     * Lista stringów (np. `emails`, `permissions`); wartości niebędące skalarami odpadają.
     *
     * @return list<string>
     */
    public static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $strings = [];
        foreach ($value as $item) {
            if (is_scalar($item)) {
                $strings[] = (string) $item;
            }
        }

        return $strings;
    }

    /**
     * Lista surowych rekordów (wiersze zagnieżdżonych kolekcji).
     *
     * @return list<array<string, mixed>>
     */
    public static function rows(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $rows = [];
        foreach ($value as $row) {
            if (is_array($row)) {
                /** @var array<string, mixed> $row */
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Payload wejściowy bez null-i - wspólna implementacja `Arrayable::toArray()`.
     *
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    public static function withoutNulls(array $fields): array
    {
        return array_filter($fields, static fn (mixed $value): bool => $value !== null);
    }
}
