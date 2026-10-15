<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Http\Endpoints;

class ArchivedEmail extends Endpoint
{
    protected string $path = 'archivedEmail';

    protected array $writes = [];
}
