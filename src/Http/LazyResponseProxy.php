<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Http;

use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Mindtwo\LaravelWeclappApi\Events\WeclappApiCallCompleted;
use Mindtwo\LaravelWeclappApi\Jobs\WeclappApiCallJob;
use Mindtwo\LaravelWeclappApi\WeclappClient;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

/**
 * A response proxy that behaves like a Response but can also hand back a job.
 *
 * By default it executes synchronously the first time a Response method is
 * accessed and caches the result. Calling getJob() instead returns an
 * undispatched WeclappApiCallJob for manual queueing; after that, executing
 * synchronously is no longer allowed.
 *
 * @mixin Response
 */
class LazyResponseProxy
{
    protected ?Response $response = null;

    protected WeclappApiCallJob $job;

    protected bool $jobRetrieved = false;

    protected bool $executed = false;

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $queryParams
     */
    public function __construct(
        protected WeclappClient $api,
        protected string $endpoint,
        protected string $method,
        protected array $body = [],
        protected array $queryParams = [],
    ) {
        $this->job = new WeclappApiCallJob(
            endpoint: $endpoint,
            method: $method,
            body: $body,
            queryParams: $queryParams,
        );
    }

    /**
     * Return the undispatched job for manual queueing. Once called, the proxy
     * can no longer execute synchronously.
     */
    public function getJob(): WeclappApiCallJob
    {
        if ($this->executed) {
            throw new RuntimeException('Cannot retrieve the job after the API call has been executed.');
        }

        $this->jobRetrieved = true;

        return $this->job;
    }

    /**
     * @throws ConnectionException
     */
    protected function execute(): Response
    {
        if ($this->response !== null) {
            return $this->response;
        }

        if ($this->jobRetrieved) {
            throw new RuntimeException('Cannot execute the API call after getJob() has been called.');
        }

        if ($this->isMutating() && $this->api->writesSuppressed()) {
            $this->api->recordSuppressedWrite($this->method, $this->endpoint);
            $this->executed = true;

            return $this->response = new Response(new PsrResponse(200, [], (string) json_encode([])));
        }

        $client = $this->api->client();

        if (! empty($this->queryParams)) {
            $client = $client->withQueryParameters($this->queryParams);
        }

        $this->response = match ($this->method) {
            Request::METHOD_GET    => $client->get($this->endpoint, $this->queryParams),
            Request::METHOD_POST   => $client->post($this->endpoint, $this->body),
            Request::METHOD_PUT    => $client->put($this->endpoint, $this->body),
            Request::METHOD_DELETE => $client->delete($this->endpoint),
            default                => throw new \InvalidArgumentException("Unsupported HTTP method: {$this->method}"),
        };

        $this->executed = true;

        WeclappApiCallCompleted::dispatch(
            $this->endpoint,
            $this->method,
            $this->response->json() ?? [],
            $this->response->status(),
            $this->response->successful(),
        );

        return $this->response;
    }

    protected function isMutating(): bool
    {
        return in_array($this->method, [Request::METHOD_POST, Request::METHOD_PUT, Request::METHOD_DELETE], true);
    }

    /**
     * @param array<int, mixed> $parameters
     *
     * @throws ConnectionException
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->execute()->$method(...$parameters);
    }

    public function __get(string $name): mixed
    {
        return $this->execute()->$name;
    }

    public function __isset(string $name): bool
    {
        return isset($this->execute()->$name);
    }

    public function __toString(): string
    {
        return (string) $this->execute();
    }
}
