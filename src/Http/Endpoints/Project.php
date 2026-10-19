<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Http\Endpoints;

/**
 * Absent from the vendored spec, yet it responds and is populated (live-confirmed
 * with GET only). The writes are inherited, not documented: they have not been
 * exercised against a tenant.
 */
class Project extends Endpoint
{
    protected string $path = 'project';
}
