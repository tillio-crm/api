<?php

declare(strict_types=1);

namespace TillioCrm\Api\Resources;

use TillioCrm\Api\Dto\CreatedUser;
use TillioCrm\Api\Dto\SystemUser;
use TillioCrm\Api\Dto\UserActivity;
use TillioCrm\Api\Dto\UserInput;
use TillioCrm\Api\Page;

/**
 * Użytkownicy systemowi.
 */
final readonly class Users extends Resource
{
    /**
     * `GET /v2/users` - strona listy użytkowników (bez kont technicznych).
     *
     * Filtry (komplet wg kontraktu): `id`, `firstName`, `lastName`, `email`
     * (LOGIN, dokładnie), `userStatusId`, `jobTitle` (zawiera), `contactPhone`
     * (zawiera, dosłownie po zapisie w bazie), `contactEmail` (dokładnie),
     * `gender` (`male|female|unspecified`), `sort`/`sortDir`, `page`/`limit`.
     *
     * `jobTitle`, `contactPhone`, `contactEmail` i `gender` wymagają API >= 2.16.0 -
     * starsza instancja odrzuci nieznany parametr błędem 400.
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

    /**
     * `GET /v2/users/activity` - strona listy z ostatnim logowaniem, ostatnią
     * czynnością i licznikiem logowań KAŻDEGO użytkownika (od API 2.16.0).
     * Agregaty liczy osobna trasa, żeby `list()` został lekki.
     *
     * "Kto nie logował się od miesiąca": `sort=lastActivityAt`, `sortDir=asc` -
     * konta bez żadnego logowania mają `null` i idą pierwsze.
     *
     * Filtry (komplet wg kontraktu): `userId`, `sort` (`userId`, `lastLoginAt`,
     * `lastActivityAt`, `loginCount`), `sortDir`, `page`/`limit`.
     *
     * @param array<string, mixed> $filters
     *
     * @return Page<UserActivity>
     */
    public function activity(array $filters = []): Page
    {
        return self::mapPage($this->client->get('v2/users/activity', $filters), UserActivity::fromArray(...));
    }

    /**
     * Pełny przebieg wszystkich stron aktywności (generator). Stabilny porządek
     * wymusza `sort=userId` - ta lista nie ma pola `id`.
     *
     * @param array<string, mixed> $filters
     *
     * @return \Generator<int, UserActivity>
     */
    public function iterateActivity(array $filters = [], int $pageSize = 1000): \Generator
    {
        return $this->iterateMapped('v2/users/activity', UserActivity::fromArray(...), $filters, $pageSize, stableSort: 'userId');
    }

    /**
     * `GET /v2/users/{id}/activity` - aktywność jednego użytkownika (od API 2.16.0).
     * Konto techniczne i nieistniejące id to tak samo 404.
     */
    public function getActivity(int $id): UserActivity
    {
        return UserActivity::fromArray(self::single($this->client->get('v2/users/' . $id . '/activity')));
    }
}
