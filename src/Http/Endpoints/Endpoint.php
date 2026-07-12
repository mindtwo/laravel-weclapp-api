<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Http\Endpoints;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Mindtwo\LaravelWeclappApi\Http\LazyResponseProxy;
use Mindtwo\LaravelWeclappApi\WeclappClient;
use Symfony\Component\HttpFoundation\Request;

/**
 * Base class for a typed Weclapp entity endpoint.
 *
 * Reads (query/find/count) execute immediately and return decoded data.
 * Writes (create/update/delete) return a LazyResponseProxy so the caller can
 * run them synchronously or hand back a queueable job via ->getJob().
 */
abstract class Endpoint
{
    /**
     * The Weclapp REST resource segment, e.g. "party" or "salesOrder".
     */
    protected string $path;

    public function __construct(protected WeclappClient $api) {}

    /**
     * Fetch every record matching the given filters (all pages merged).
     *
     * @param array<string, mixed> $filters
     *
     * @throws RequestException
     *
     * @return Collection<int, object>
     */
    public function query(array $filters = []): Collection
    {
        return $this->api->get($this->path, $filters);
    }

    /**
     * Fetch a single record by id, or null when it does not exist.
     *
     * @throws RequestException
     */
    public function find(string|int $id): ?object
    {
        return $this->api->find($this->path, $id);
    }

    /**
     * The number of records matching the given filters.
     *
     * @param array<string, mixed> $filters
     *
     * @throws RequestException
     */
    public function count(array $filters = []): int
    {
        return $this->api->count($this->path, $filters);
    }

    /**
     * Create a record.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): LazyResponseProxy
    {
        return new LazyResponseProxy($this->api, $this->path, Request::METHOD_POST, body: $data);
    }

    /**
     * Replace a record by id.
     *
     * @param array<string, mixed> $data
     */
    public function update(string|int $id, array $data): LazyResponseProxy
    {
        return new LazyResponseProxy($this->api, $this->path.'/'.$id, Request::METHOD_PUT, body: $data);
    }

    /**
     * Delete a record by id.
     */
    public function delete(string|int $id): LazyResponseProxy
    {
        return new LazyResponseProxy($this->api, $this->path.'/'.$id, Request::METHOD_DELETE);
    }
}
