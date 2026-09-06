<?php

declare(strict_types=1);

namespace TillioCrm\Api;

/**
 * Jeden wpis z typowanego kontraktu błędu v2: `{field, code, message}`.
 *
 * v2 zwraca WSZYSTKIE błędy walidacji naraz (`_error.errors[]`), więc konsument
 * może zaraportować komplet powodów odrzucenia rekordu, a nie pierwszy z brzegu.
 */
final readonly class ApiError
{
    public function __construct(
        public string $field,
        public string $code,
        public string $message,
    ) {
    }

    /**
     * Buduje wpis z surowego wiersza `_error.errors[]`.
     *
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            self::stringValue($row['field'] ?? null),
            self::stringValue($row['code'] ?? null),
            self::stringValue($row['message'] ?? null),
        );
    }

    public function __toString(): string
    {
        $field = $this->field !== '' ? $this->field . ': ' : '';

        return $field . $this->message . ' [' . $this->code . ']';
    }

    private static function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
