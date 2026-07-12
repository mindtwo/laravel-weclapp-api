<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Listeners;

use Illuminate\Support\Facades\Log;
use Mindtwo\LaravelWeclappApi\Events\WeclappApiCallCompleted;

class LogWeclappEvent
{
    public function handle(WeclappApiCallCompleted $event): void
    {
        /** @var string|null $channel */
        $channel = config('weclapp-api.logging.channel');

        /** @var string $level */
        $level = config('weclapp-api.logging.level', 'info');

        $context = [
            'endpoint'   => $event->endpoint,
            'method'     => $event->method,
            'status'     => $event->statusCode,
            'successful' => $event->successful,
            'suppressed' => $event->suppressed,
        ];

        if (config('weclapp-api.logging.include_payload', false)) {
            $context['response'] = $event->response;
        }

        Log::channel($channel)->log($level, 'Weclapp API call completed', $context);
    }
}
