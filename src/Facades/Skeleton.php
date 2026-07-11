<?php

namespace Mindtwo\LaravelWeclappApi\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Mindtwo\LaravelWeclappApi\Skeleton
 */
class Skeleton extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Mindtwo\LaravelWeclappApi\Skeleton::class;
    }
}
