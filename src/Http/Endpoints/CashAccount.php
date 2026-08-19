<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Http\Endpoints;

class CashAccount extends Endpoint
{
    protected string $path = 'cashAccount';

    protected array $writes = ['create', 'update'];
}
