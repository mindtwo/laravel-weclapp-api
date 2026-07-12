<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Mindtwo\LaravelWeclappApi\Events\WeclappApiCallCompleted;
use Mindtwo\LaravelWeclappApi\Exceptions\WeclappApiCallFailedException;
use Mindtwo\LaravelWeclappApi\WeclappClient;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

class WeclappApiCallJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 60;

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $queryParams
     */
    public function __construct(
        public string $endpoint,
        public string $method,
        public array $body = [],
        public array $queryParams = [],
    ) {
        $connection = config('weclapp-api.queue_connection');

        if (is_string($connection) && $connection !== '') {
            $this->onConnection($connection);
        }
    }

    public function handle(): void
    {
        $client = app(WeclappClient::class)->client;

        if (! empty($this->queryParams)) {
            $client = $client->withQueryParameters($this->queryParams);
        }

        try {
            $response = match ($this->method) {
                Request::METHOD_GET    => $client->get($this->endpoint, $this->queryParams),
                Request::METHOD_POST   => $client->post($this->endpoint, $this->body),
                Request::METHOD_PUT    => $client->put($this->endpoint, $this->body),
                Request::METHOD_DELETE => $client->delete($this->endpoint),
                default                => throw new \InvalidArgumentException("Unsupported HTTP method: {$this->method}"),
            };
        } catch (ConnectionException $e) {
            if ($this->attempts() < $this->tries) {
                $backoff = $this->backoff();

                $this->release($backoff[$this->attempts() - 1] ?? (int) end($backoff));

                return;
            }

            $this->fail($e);

            return;
        }

        WeclappApiCallCompleted::dispatch(
            $this->endpoint,
            $this->method,
            $response->json() ?? [],
            $response->status(),
            $response->successful(),
        );

        if ($response->successful()) {
            return;
        }

        $status = $response->status();

        if (($status === 429 || $status >= 500) && $this->attempts() < $this->tries) {
            $this->release($this->retryAfterSeconds($response));

            return;
        }

        Log::warning('Weclapp API call failed (terminal)', [
            'endpoint' => $this->endpoint,
            'method'   => $this->method,
            'status'   => $status,
        ]);

        $this->fail(WeclappApiCallFailedException::fromResponse($this->endpoint, $this->method, $response));
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('weclapp-api-jobs')];
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function failed(?Throwable $e): void
    {
        if ($e instanceof WeclappApiCallFailedException) {
            return;
        }

        WeclappApiCallCompleted::dispatch(
            $this->endpoint,
            $this->method,
            ['error' => $e?->getMessage() ?? 'Weclapp API job failed'],
            0,
            false,
        );
    }

    private function retryAfterSeconds(Response $response): int
    {
        $retryAfter = $response->header('Retry-After');

        if (is_numeric($retryAfter)) {
            return max(1, (int) $retryAfter);
        }

        return 30;
    }
}
