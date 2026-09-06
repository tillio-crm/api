<?php

declare(strict_types=1);

namespace TillioCrm\Api\Dto;

/**
 * Komentarz zadania do zapisu. Wymagane body (HTML). parentCommentId = odpowiedź
 * w wątku. Dostępne od wersji API 2.7.0.
 */
final readonly class TaskCommentInput implements Arrayable
{
    public function __construct(
        public ?string $body = null,
        public ?int $parentCommentId = null,
    ) {
    }

    public function toArray(): array
    {
        return Cast::withoutNulls([
            'body' => $this->body,
            'parentCommentId' => $this->parentCommentId,
        ]);
    }
}
