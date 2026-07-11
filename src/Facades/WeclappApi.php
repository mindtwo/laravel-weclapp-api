<?php

namespace Mindtwo\LaravelWeclappApi\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Mindtwo\LaravelWeclappApi\WeclappApi
 */
class WeclappApi extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Mindtwo\LaravelWeclappApi\WeclappApi::class;
    }
}
