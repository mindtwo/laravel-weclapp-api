<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Http\Endpoints;

use BadMethodCallException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Mindtwo\LaravelWeclappApi\Http\LazyResponseProxy;
use Mindtwo\LaravelWeclappApi\WeclappClient;
use Symfony\Component\HttpFoundation\Request;

/**
 * Base class for a typed Weclapp entity endpoint.
 *
 * Reads (query/find/count) execute immediately and return decoded data; every
 * Weclapp resource supports all three. Writes (create/update/delete) return a
 * LazyResponseProxy so the caller can run them synchronously or hand back a
 * queueable job via ->getJob(), and are only available where the API actually
 * offers them.
 */
abstract class Endpoint
{
    /**
     * The Weclapp REST resource segment, e.g. "party" or "salesOrder".
     */
    protected string $path;

    /**
     * Filters applied to every read, for endpoints that are a filtered view of
     * a shared resource (e.g. customers and suppliers over /party).
     *
     * @var array<string, mixed>
     */
    protected array $defaultFilters = [];

    /**
     * The write operations this resource offers, taken from the OpenAPI spec.
     * Not every resource is writable: some are reports or system-maintained
     * lists, so calling an unsupported write fails here instead of returning a
     * 404/405 from Weclapp.
     *
     * @var list<string>
     */
    protected array $writes = ['create', 'update', 'delete'];

    public function __construct(protected WeclappClient $api) {}

    /**
     * The write operations this resource offers.
     *
     * @return list<string>
     */
    public function writes(): array
    {
        return $this->writes;
    }

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
        return $this->api->get($this->path, [...$this->defaultFilters, ...$filters]);
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
        return $this->api->count($this->path, [...$this->defaultFilters, ...$filters]);
    }

    /**
     * Create a record.
     *
     * @param array<string, mixed> $data
     *
     * @throws BadMethodCallException when the resource is not creatable
     */
    public function create(array $data): LazyResponseProxy
    {
        $this->guardWrite('create');

        return new LazyResponseProxy($this->api, $this->path, Request::METHOD_POST, body: $data);
    }

    /**
     * Replace a record by id.
     *
     * @param array<string, mixed> $data
     *
     * @throws BadMethodCallException when the resource is not updatable
     */
    public function update(string|int $id, array $data): LazyResponseProxy
    {
        $this->guardWrite('update');

        return new LazyResponseProxy($this->api, $this->api->recordPath($this->path, $id), Request::METHOD_PUT, body: $data);
    }

    /**
     * Delete a record by id.
     *
     * @throws BadMethodCallException when the resource is not deletable
     */
    public function delete(string|int $id): LazyResponseProxy
    {
        $this->guardWrite('delete');

        return new LazyResponseProxy($this->api, $this->api->recordPath($this->path, $id), Request::METHOD_DELETE);
    }

    /**
     * @throws BadMethodCallException
     */
    protected function guardWrite(string $operation): void
    {
        if (in_array($operation, $this->writes, true)) {
            return;
        }

        throw new BadMethodCallException(sprintf(
            'The Weclapp "%s" resource does not support %s. Supported writes: %s.',
            $this->path,
            $operation,
            $this->writes === [] ? 'none (read-only)' : implode(', ', $this->writes),
        ));
    }
}
