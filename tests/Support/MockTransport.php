<?php

declare(strict_types=1);

namespace TillioCrm\Api\Tests\Support;

use TillioCrm\Api\Transport\TransportInterface;
use TillioCrm\Api\Transport\TransportRequest;
use TillioCrm\Api\Transport\TransportResponse;

/**
 * Atrapa transportu: oddaje zakolejkowane odpowiedzi (albo rzuca zakolejkowane
 * wyjątki) i nagrywa każde żądanie. Nie zna kontraktu v2 - surowe stringi.
 */
final class MockTransport implements TransportInterface
{
    /** @var list<TransportResponse|\Throwable> */
    private array $queue = [];

    /** @var list<TransportRequest> */
    public array $requests = [];

    public function queue(TransportResponse $response): self
    {
        $this->queue[] = $response;

        return $this;
    }

    public function queueThrowable(\Throwable $throwable): self
    {
        $this->queue[] = $throwable;

        return $this;
    }

    /**
     * Skrót: odpowiedź JSON o danym statusie.
     *
     * @param array<string, string> $headers
     */
    public function queueJson(int $status, string $json, array $headers = []): self
    {
        return $this->queue(new TransportResponse($status, $json, $headers));
    }

    public function send(TransportRequest $request): TransportResponse
    {
        $this->requests[] = $request;

        $next = array_shift($this->queue);
        if ($next === null) {
            throw new \LogicException('MockTransport: kolejka odpowiedzi pusta - test wysłał więcej żądań, niż zakolejkowano.');
        }

        if ($next instanceof \Throwable) {
            throw $next;
        }

        return $next;
    }

    public function lastRequest(): TransportRequest
    {
        $last = end($this->requests);
        if ($last === false) {
            throw new \LogicException('MockTransport: nie było żadnego żądania.');
        }

        return $last;
    }
}
