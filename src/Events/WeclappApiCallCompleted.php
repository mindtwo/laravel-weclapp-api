<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WeclappApiCallCompleted
{
    use Dispatchable, SerializesModels;

    /**
     * @param array<string, mixed> $response The decoded response body
     * @param bool $suppressed True when the write was intentionally not sent
     *                         (e.g. the writes-enabled toggle short-circuited it)
     */
    public function __construct(
        public string $endpoint,
        public string $method,
        public array $response,
        public int $statusCode,
        public bool $successful,
        public bool $suppressed = false,
    ) {}

    public function wasSuccessful(): bool
    {
        return $this->successful;
    }

    public function wasSuppressed(): bool
    {
        return $this->suppressed;
    }
}
