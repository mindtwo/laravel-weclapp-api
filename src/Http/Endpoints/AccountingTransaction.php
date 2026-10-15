<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Http\Endpoints;

class AccountingTransaction extends Endpoint
{
    protected string $path = 'accountingTransaction';

    protected array $writes = [];
}
