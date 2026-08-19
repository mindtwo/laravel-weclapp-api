<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Http\Endpoints;

/**
 * Suppliers are parties flagged as such — the v2 API has no /supplier resource.
 */
class Supplier extends Endpoint
{
    protected string $path = 'party';

    protected array $defaultFilters = ['supplier-eq' => 'true'];
}
