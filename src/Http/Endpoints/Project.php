<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Http\Endpoints;

/**
 * Absent from the vendored spec, yet it responds and is populated (live-confirmed
 * with GET only). With no contract to derive writes from, none are offered;
 * add one to $writes once it has been exercised against a tenant.
 */
class Project extends Endpoint
{
    protected string $path = 'project';

    protected array $writes = [];
}
