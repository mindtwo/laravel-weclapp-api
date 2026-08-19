<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Http\Endpoints;

class CashAccountTransaction extends Endpoint
{
    protected string $path = 'cashAccountTransaction';

    protected array $writes = ['create', 'delete'];
}
