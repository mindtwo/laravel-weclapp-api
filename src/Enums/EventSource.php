<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Enums;

enum EventSource: string
{
    case API = 'api';
    case WEBHOOK = 'webhook';

    public function isApi(): bool
    {
        return $this === self::API;
    }

    public function isWebhook(): bool
    {
        return $this === self::WEBHOOK;
    }
}
