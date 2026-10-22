<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Jobs;

use DateTimeInterface;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Mindtwo\LaravelWeclappApi\Events\WeclappApiCallCompleted;
use Mindtwo\LaravelWeclappApi\Exceptions\WeclappApiCallFailedException;
use Mindtwo\LaravelWeclappApi\WeclappClient;
use Throwable;

/**
 * One Weclapp request on the queue.
 *
 * Bounded by time rather than attempts, because every rate-limit release
 * counts as an attempt. Within the budget it releases itself on a 429, and on
 * a 5xx or a connection error only for PUT/DELETE: those leave it open whether
 * Weclapp processed the request, and re-sending a POST could create a second
 * record. Every other failure fails at once.
 */
class WeclappApiCallJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $maxExceptions = 3;

    /**
     * Derived from the client's timeouts, so the worker never kills a request
     * Weclapp is still answering and the queue then sends it again.
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

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHour();
    }

    public function handle(): void
    {
        $api = app(WeclappClient::class);

        $configure = $this->queryParams === []
            ? null
            : fn (PendingRequest $client): PendingRequest => $client->withQueryParameters($this->queryParams);

        // Decided once, as the response arrives: the event's `final` and the
        // release below must agree even if the budget runs out in between.
        $retry = false;

        try {
            // One request per attempt; retrying is done through release().
            $response = $api->send(
                $this->method,
                $this->endpoint,
                $this->body,
                $configure,
                retry: false,
                isFinal: function (Response $response) use (&$retry): bool {
                    $retry = $this->willRetry($response);

                    return ! $retry;
                },
            );
        } catch (ConnectionException $e) {
            if ($this->idempotent() && $this->withinBudget()) {
                $backoff = $this->backoff();

                $this->release($backoff[$this->attempts() - 1] ?? (int) end($backoff));

                return;
            }

            $this->fail($e);

            return;
        }

        if ($response->successful()) {
            return;
        }

        if ($retry) {
            $this->release($this->retryAfterSeconds($response));

            return;
        }

        $channel = config('weclapp-api.logging.channel');

        Log::channel(is_string($channel) && $channel !== '' ? $channel : null)->warning('Weclapp API call failed (terminal)', [
            'endpoint' => $this->endpoint,
            'method'   => $this->method,
            'status'   => $response->status(),
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

    /**
     * A 429, or a 5xx on an idempotent verb, is "not yet", not "failed", while
     * the budget lasts.
     */
    private function willRetry(Response $response): bool
    {
        return ! $response->successful()
            && ($response->status() === 429 || ($response->status() >= 500 && $this->idempotent()))
            && $this->withinBudget();
    }

    /**
     * Same rule as WeclappClient::shouldRetry().
     */
    private function idempotent(): bool
    {
        return in_array($this->method, ['PUT', 'DELETE'], true);
    }

    /**
     * Only a real queue worker runs a released job again. On the sync driver,
     * or when handle() is called without a job, release() is a no-op, so the
     * attempt has to be final rather than silently dropped.
     */
    private function withinBudget(): bool
    {
        if ($this->job === null || $this->job instanceof SyncJob) {
            return false;
        }

        $until = $this->job->retryUntil();

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
