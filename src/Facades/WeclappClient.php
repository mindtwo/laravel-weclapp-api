<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Facades;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use Mindtwo\LaravelWeclappApi\WeclappClient as BaseWeclappClient;

/**
 * @method static Collection<int, object> get(string $endpoint, array<string, mixed> $params = [])
 * @method static array<string, mixed> post(string $endpoint, array<string, mixed> $data, bool $dryRun = false)
 * @method static array<string, mixed> put(string $endpoint, string|int $id, array<string, mixed> $data)
 * @method static void delete(string $endpoint, string|int $id)
 * @method static object|null find(string $endpoint, string|int $id)
 * @method static int count(string $endpoint, array<string, mixed> $params = [])
 *
 * @see BaseWeclappClient
 */
class WeclappClient extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return BaseWeclappClient::class;
    }
}
