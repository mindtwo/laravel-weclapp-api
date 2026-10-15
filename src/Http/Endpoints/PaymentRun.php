<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Http\Endpoints;

class PaymentRun extends Endpoint
{
    protected string $path = 'paymentRun';

    protected array $writes = ['update', 'delete'];
}
