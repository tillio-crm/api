<?php

declare(strict_types=1);

namespace TillioCrm\Api\Resources;

use TillioCrm\Api\Dto\CreatedUser;
use TillioCrm\Api\Dto\SystemUser;
use TillioCrm\Api\Dto\UserInput;
use TillioCrm\Api\Page;

/**
 * Użytkownicy systemowi.
 */
final readonly class Users extends Resource
{
    /**
     * `GET /v2/users` - strona listy użytkowników.
     *
     * Filtry (komplet wg kontraktu): `id`, `firstName`, `lastName`, `email`,
     * `userStatusId`, `sort`/`sortDir`, `page`/`limit`.
     *
     * @param array<string, mixed> $filters
     *
     * @return Page<SystemUser>
     */
    public function list(array $filters = []): Page
    {
        return self::mapPage($this->client->get('v2/users', $filters), SystemUser::fromArray(...));
    }

    /**
     * Pełny przebieg wszystkich stron (generator, wymuszone `sort=id`).
     *
     * @param array<string, mixed> $filters
     *
     * @return \Generator<int, SystemUser>
     */
    public function iterate(array $filters = [], int $pageSize = 1000): \Generator
    {
        return $this->iterateMapped('v2/users', SystemUser::fromArray(...), $filters, $pageSize);
    }

    /**
     * `POST /v2/users` - założenie konta. Wymagane `firstName`, `email`,
     * `userStatusId`, `roleId`.
     *
     * NIEPONAWIALNE (celowo, na poziomie transportu): odpowiedź niesie
     * JEDNORAZOWE hasło startowe ({@see CreatedUser}), a powtórka po timeoutcie,
     * który w rzeczywistości doszedł, to "login zajęty" - i hasło przepada.
     * Po TransportException sprawdź listą, czy konto istnieje, zanim spróbujesz
     * ponownie.
     *
     * @param UserInput|array<string, mixed> $input
     *
     * @throws \TillioCrm\Api\Exception\UserLimitReachedException 409 - instancja bez wolnej
     *                                                            licencji; konta zakładane jako
     *                                                            NIEAKTYWNE nie liczą się do limitu
     */
    public function create(UserInput|array $input): CreatedUser
    {
        return CreatedUser::fromResponse(
            $this->client->request('POST', 'v2/users', body: self::payload($input), retry: false),
        );
    }
}
