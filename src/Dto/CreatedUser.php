<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

use TillioCrm\Api\ApiResponse;

/**
 * Wynik `POST /v2/users` - użytkownik + HASŁO STARTOWE.
 *
 * `temporaryPassword` jest JEDNORAZOWE: występuje wyłącznie w tej odpowiedzi
 * i nie da się go później odczytać żadną trasą. Przekaż je użytkownikowi OD RAZU
 * (system wymusi zmianę przy pierwszym logowaniu) i nie zapisuj w logach.
 * Dlatego `POST /v2/users` jedzie bez retry: powtórka po timeoutcie, który
 * w rzeczywistości doszedł, to "login zajęty" - a hasło przepadło.
 */
final readonly class CreatedUser
{
    /**
     * @param array<string, mixed> $warnings `info.warnings` (np. ostrzeżenie o kalendarzu)
     */
    public function __construct(
        public SystemUser $user,
        #[\SensitiveParameter]
        public ?string $temporaryPassword,
        public array $warnings = [],
    ) {
    }

    public static function fromResponse(ApiResponse $response): self
    {
        /** @var array<string, mixed> $data */
        $data = $response->data();
        $info = $response->info();

        return new self(
            user: SystemUser::fromArray($data),
            temporaryPassword: Cast::string($info['temporaryPassword'] ?? null),
            warnings: Cast::map($info['warnings'] ?? null),
        );
    }
}
