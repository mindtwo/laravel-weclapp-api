<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Http;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Mindtwo\LaravelWeclappApi\Events\WeclappApiCallCompleted;
use Mindtwo\LaravelWeclappApi\Jobs\WeclappApiCallJob;
use Mindtwo\LaravelWeclappApi\WeclappClient;
use RuntimeException;
use Throwable;

/**
 * A response proxy that behaves like a Response but can also hand back a job.
 *
 * By default it executes synchronously the first time a Response method is
 * accessed and caches the result. Calling getJob() instead returns an
 * undispatched WeclappApiCallJob for manual queueing; after that, executing
 * synchronously is no longer allowed.
 *
 * A proxy nobody reads from sends its request when it goes out of scope, so
 * a write used as a plain statement still happens; only getJob() opts out.
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

        // Before the request, so the destructor never repeats an attempt that threw.
        $this->executed = true;

        try {
            return $this->response = $this->api->send($this->method, $this->endpoint, $this->body, $this->configure());
        } catch (ConnectionException $e) {
            // send() reports responses; a request that never got one is reported here.
            WeclappApiCallCompleted::dispatch($this->endpoint, $this->method, ['error' => $e->getMessage()], 0, false);

            throw $e;
        }
    }

    /**
     * @return (\Closure(PendingRequest): PendingRequest)|null
     */
    private function configure(): ?\Closure
    {
        return $this->queryParams === []
            ? null
            : fn (PendingRequest $client): PendingRequest => $client->withQueryParameters($this->queryParams);
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

    /**
     * A failure here has no caller left to catch it, and throwing from a
     * destructor during shutdown is fatal, so it is reported instead.
     */
    public function __destruct()
    {
        if ($this->executed || $this->jobRetrieved) {
            return;
        }

        try {
            $this->execute();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
