<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Http\Endpoints;

class ExternalConnection extends Endpoint
{
    protected string $path = 'externalConnection';

    protected array $writes = [];
}
