<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Http\Endpoints;

class PurchaseRequisition extends Endpoint
{
    protected string $path = 'purchaseRequisition';

    protected array $writes = ['update'];
}
