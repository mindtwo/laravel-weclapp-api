<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Mindtwo\LaravelWeclappApi\Enums\EventSource;

abstract class WeclappEvent
{
    use Dispatchable, SerializesModels;

    /**
     * @param array<string, mixed> $payload The event data payload
     * @param EventSource $source Where the event originated (API call or webhook)
     * @param bool $successful Whether the underlying operation succeeded
     */
    public function __construct(
        public array $payload,
        public EventSource $source = EventSource::API,
        public bool $successful = true,
    ) {}

    public function isFromWebhook(): bool
    {
        return $this->source->isWebhook();
    }

    public function isFromApi(): bool
    {
        return $this->source->isApi();
    }

    public function wasSuccessful(): bool
    {
        return $this->successful;
    }
}
