<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Jobs;

use DateTimeInterface;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Mindtwo\LaravelWeclappApi\Events\WeclappApiCallCompleted;
use Mindtwo\LaravelWeclappApi\Exceptions\WeclappApiCallFailedException;
use Mindtwo\LaravelWeclappApi\WeclappClient;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

/**
 * One Weclapp request on the queue.
 *
 * The job is bounded by time, not by a count of attempts: the rate limiter
 * puts a throttled job back on the queue, and every release counts as an
 * attempt, so a backlog a few minutes long would exhaust a fixed `$tries`
 * before a single request went out. Inside the budget it releases itself on a
 * 429, a 5xx or a connection error, with Weclapp's Retry-After when given; a
 * 4xx is a refusal a retry cannot change and fails at once.
 */
class WeclappApiCallJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Genuine exceptions, as opposed to attempts: the throttle and the
     * handled failures below release the job without throwing.
     */
    public int $maxExceptions = 3;

    /**
     * Seconds the worker lets one attempt run. Derived from the client's own
     * timeouts so the worker never kills a request Weclapp is still answering,
     * which the queue would then send a second time.
     */
    public int $timeout;

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

        $this->timeout = (int) config('weclapp-api.http.timeout', 60)
            + (int) config('weclapp-api.http.connect_timeout', 10)
            + 5;
    }

    /**
     * How long after dispatch the call may still be retried.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHour();
    }

    public function handle(): void
    {
        $api = app(WeclappClient::class);

        if ($this->isMutating() && $api->writesSuppressed()) {
            $api->recordSuppressedWrite($this->method, $this->endpoint);

            return;
        }

        // One request per attempt: retrying is this job's business, through
        // release() and the time budget, not the transport's.
        $client = $api->client()->retry(1, 0, throw: false);

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
            if ($this->withinBudget()) {
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

        if (($status === 429 || $status >= 500) && $this->withinBudget()) {
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
        return [new SkipIfBatchCancelled, new RateLimited('weclapp-api-jobs')];
    }

    protected function isMutating(): bool
    {
        return in_array($this->method, [Request::METHOD_POST, Request::METHOD_PUT, Request::METHOD_DELETE], true);
    }

    /**
     * Seconds to wait after a connection error, by attempt.
     *
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

    /**
     * Whether the dispatch-time budget from retryUntil() still allows a release.
     * Run outside a worker there is no budget to check.
     */
    private function withinBudget(): bool
    {
        $until = $this->job?->retryUntil();

        return $until === null || $until > now()->getTimestamp();
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
