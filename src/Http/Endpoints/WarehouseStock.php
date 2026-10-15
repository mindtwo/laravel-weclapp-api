<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Http\Endpoints;

class WarehouseStock extends Endpoint
{
    protected string $path = 'warehouseStock';

    protected array $writes = [];
}
