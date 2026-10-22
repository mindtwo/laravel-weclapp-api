<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Http\Endpoints;

class ServiceQuota extends Endpoint
{
    protected string $path = 'serviceQuota';

    protected array $writes = ['update', 'delete'];
}
