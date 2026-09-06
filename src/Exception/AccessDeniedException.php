<?php

declare(strict_types=1);

namespace TillioCrm\Api\Exception;

/**
 * 403 - klucz uwierzytelniony, ale bez uprawnień do tej operacji (np. tworzenie pól
 * niestandardowych wymaga klucza administratora). Bez retry - uprawnień nie przybędzie
 * między próbami.
 */
class AccessDeniedException extends ApiException
{
}
