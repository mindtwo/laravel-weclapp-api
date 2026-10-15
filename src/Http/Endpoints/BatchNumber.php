<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Http\Endpoints;

class BatchNumber extends Endpoint
{
    protected string $path = 'batchNumber';

    protected array $writes = [];
}
