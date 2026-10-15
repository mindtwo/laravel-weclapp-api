<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Http\Endpoints;

class BankTransaction extends Endpoint
{
    protected string $path = 'bankTransaction';

    protected array $writes = ['delete'];
}
