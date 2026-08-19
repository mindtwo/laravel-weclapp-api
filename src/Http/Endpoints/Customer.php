<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Http\Endpoints;

/**
 * Customers are parties flagged as such — the v2 API has no /customer resource.
 */
class Customer extends Endpoint
{
    protected string $path = 'party';

    protected array $defaultFilters = ['customer-eq' => 'true'];
}
