<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Http\Endpoints;

class InventoryTransportReference extends Endpoint
{
    protected string $path = 'inventoryTransportReference';

    protected array $writes = ['create', 'update'];
}
