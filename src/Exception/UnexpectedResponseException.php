<?php

declare(strict_types=1);

namespace TillioCrm\Api\Exception;

/**
 * Status 2xx, ale body nie jest JSON-em w kontrakcie v2 (np. HTML od reverse proxy
 * albo obcięta odpowiedź). Osobny typ, żeby w logu było widać różnicę między
 * "API odmówiło" a "coś po drodze podmieniło odpowiedź".
 */
final class UnexpectedResponseException extends TillioApiException
{
    public function __construct(string $message, public readonly int $status = 0, public readonly string $rawBody = '')
    {
        parent::__construct($message, $status);
    }
}
