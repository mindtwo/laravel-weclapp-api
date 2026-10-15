<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Http\Endpoints;

class SerialNumber extends Endpoint
{
    protected string $path = 'serialNumber';

    protected array $writes = ['update'];
}
