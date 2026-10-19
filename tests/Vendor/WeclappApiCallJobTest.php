<?php

declare(strict_types=1);

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\Jobs\FakeJob;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mindtwo\LaravelWeclappApi\Events\WeclappApiCallCompleted;
use Mindtwo\LaravelWeclappApi\Exceptions\WeclappApiCallFailedException;
use Mindtwo\LaravelWeclappApi\Facades\WeclappClient;
use Mindtwo\LaravelWeclappApi\Jobs\WeclappApiCallJob;

beforeEach(function () {
    config()->set('weclapp-api.base_url', 'https://tenant.weclapp.com/webapp/api/v2/');
    config()->set('weclapp-api.token', 'test-token');
});

/**
 * A job whose dispatch-time retry budget has already run out.
 */
function exhaustedJob(): FakeJob
{
    return new class extends FakeJob
    {
        public function retryUntil()
        {
            return now()->subMinute()->getTimestamp();
        }
    };
}

it('can be queued from the proxy', function () {
    Queue::fake();

    $job = WeclappClient::articles()->update(9, ['name' => 'New'])->getJob();

    dispatch($job);

    Queue::assertPushed(WeclappApiCallJob::class, fn ($queued) => $queued->method === 'PUT'
        && $queued->endpoint === 'article/id/9');
});

it('performs the request and emits a completion event when handled', function () {
    Event::fake([WeclappApiCallCompleted::class]);
    Http::fake(['*/article/id/9' => Http::response(['id' => '9'], 200)]);

    (new WeclappApiCallJob('article/id/9', 'PUT', ['name' => 'New']))->handle();

    Http::assertSent(fn ($request) => $request->method() === 'PUT');
    Event::assertDispatched(WeclappApiCallCompleted::class, fn ($event) => $event->successful === true);
});

it('is bounded by time rather than by a count of attempts, so throttle releases cost nothing', function () {
    $job = new WeclappApiCallJob('article/id/9', 'PUT');

    expect($job->retryUntil()->getTimestamp())->toBeGreaterThan(now()->addMinutes(59)->getTimestamp())
        ->and($job->maxExceptions)->toBe(3)
        ->and(property_exists($job, 'tries'))->toBeFalse();
});

it('lets the worker wait at least as long as one request can take', function () {
    config()->set('weclapp-api.http.timeout', 45);
    config()->set('weclapp-api.http.connect_timeout', 7);

    expect((new WeclappApiCallJob('article/id/9', 'PUT'))->timeout)->toBe(57);
});

it('skips a call whose batch was cancelled, and rate-limits the rest', function () {
    $middleware = (new WeclappApiCallJob('article/id/9', 'PUT'))->middleware();

    expect($middleware[0])->toBeInstanceOf(SkipIfBatchCancelled::class)
        ->and($middleware[1])->toBeInstanceOf(RateLimited::class);
});

describe('retries', function () {
    it('releases itself for Retry-After seconds on a 429', function () {
        Event::fake([WeclappApiCallCompleted::class]);
        Http::fake(['*' => Http::response('', 429, ['Retry-After' => '12'])]);

        $job = (new WeclappApiCallJob('article/id/9', 'PUT', ['name' => 'New']))->withFakeQueueInteractions();
        $job->handle();

        $job->assertReleased(12);
        Http::assertSentCount(1);
    });

    it('releases itself on a server error', function () {
        Event::fake([WeclappApiCallCompleted::class]);
        Http::fake(['*' => Http::response('', 503)]);

        $job = (new WeclappApiCallJob('article/id/9', 'PUT', ['name' => 'New']))->withFakeQueueInteractions();
        $job->handle();

        $job->assertReleased(30);
        // The transport sends once per attempt even on PUT; the queue is what retries.
        Http::assertSentCount(1);
    });

    it('fails a POST on a server error instead of sending it again', function () {
        Event::fake([WeclappApiCallCompleted::class]);
        Http::fake(['*' => Http::response('', 503)]);

        $job = (new WeclappApiCallJob('quotation', 'POST', ['foo' => 'bar']))->withFakeQueueInteractions();
        $job->handle();

        $job->assertNotReleased();
        $job->assertFailedWith(WeclappApiCallFailedException::class);
        Http::assertSentCount(1);
        Event::assertDispatched(WeclappApiCallCompleted::class, fn ($e) => $e->statusCode === 503 && $e->final === true);
    });

    it('fails a POST on a connection error, sending once', function () {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;

            throw new ConnectionException('timed out');
        });

        $job = (new WeclappApiCallJob('quotation', 'POST', ['foo' => 'bar']))->withFakeQueueInteractions();
        $job->handle();

        $job->assertNotReleased();
        $job->assertFailedWith(ConnectionException::class);
        expect($calls)->toBe(1);
    });

    it('releases itself with its own backoff after a connection error, sending once', function () {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;

            throw new ConnectionException('timed out');
        });

        $job = (new WeclappApiCallJob('article/id/9', 'PUT', ['name' => 'New']))->withFakeQueueInteractions();
        $job->handle();

        $job->assertReleased(10);
        expect($calls)->toBe(1);
    });

    it('fails a refusal at once, with one event', function () {
        Event::fake([WeclappApiCallCompleted::class]);
        Http::fake(['*' => Http::response(['detail' => 'nope'], 422)]);

        $job = (new WeclappApiCallJob('article/id/9', 'PUT', ['name' => 'New']))->withFakeQueueInteractions();
        $job->handle();

        $job->assertNotReleased();
        $job->assertFailedWith(WeclappApiCallFailedException::class);
        Event::assertDispatchedTimes(WeclappApiCallCompleted::class, 1);
    });

    it('fails instead of releasing once the budget has run out', function () {
        Event::fake([WeclappApiCallCompleted::class]);
        Http::fake(['*' => Http::response('', 503)]);

        $job = new WeclappApiCallJob('article/id/9', 'PUT', ['name' => 'New']);
        $job->setJob(exhaustedJob());
        $job->handle();

        expect($job->job->isReleased())->toBeFalse()
            ->and($job->job->hasFailed())->toBeTrue();
    });

    it('suppresses a write inside the job too', function () {
        config()->set('weclapp-api.writes_enabled', false);
        Http::fake();
        Event::fake([WeclappApiCallCompleted::class]);

        (new WeclappApiCallJob('quotation', 'POST', ['foo' => 'bar']))->handle();

        Http::assertNothingSent();
        Event::assertDispatched(WeclappApiCallCompleted::class, fn ($event) => $event->suppressed === true);
    });
});

describe('attempt events', function () {
    it('marks an attempt it will retry as not final, and the one that succeeds as final', function () {
        Event::fake([WeclappApiCallCompleted::class]);
        Http::fakeSequence()
            ->push('', 429, ['Retry-After' => '1'])
            ->push(['id' => 'q-1'], 201);

        $job = (new WeclappApiCallJob('quotation', 'POST', ['foo' => 'bar']))->withFakeQueueInteractions();
        $job->handle();
        $job->handle();

        Event::assertDispatchedTimes(WeclappApiCallCompleted::class, 2);
        Event::assertDispatched(WeclappApiCallCompleted::class, fn ($e) => $e->statusCode === 429 && $e->successful === false && $e->final === false);
        Event::assertDispatched(WeclappApiCallCompleted::class, fn ($e) => $e->statusCode === 201 && $e->successful === true && $e->final === true);
    });

    it('marks a refusal as final', function () {
        Event::fake([WeclappApiCallCompleted::class]);
        Http::fake(['*' => Http::response(['detail' => 'nope'], 422)]);

        $job = (new WeclappApiCallJob('article/id/9', 'PUT', ['name' => 'New']))->withFakeQueueInteractions();
        $job->handle();

        Event::assertDispatched(WeclappApiCallCompleted::class, fn ($e) => $e->statusCode === 422 && $e->final === true);
    });

    it('marks a server error as final once the budget has run out', function () {
        Event::fake([WeclappApiCallCompleted::class]);
        Http::fake(['*' => Http::response('', 503)]);

        $job = new WeclappApiCallJob('article/id/9', 'PUT', ['name' => 'New']);
        $job->setJob(exhaustedJob());
        $job->handle();

        Event::assertDispatched(WeclappApiCallCompleted::class, fn ($e) => $e->statusCode === 503 && $e->final === true);
    });

    it('is neither failed nor re-run by a listener that throws', function () {
        Http::fake(['*/article/id/9' => Http::response(['id' => '9'], 200)]);
        Event::listen(WeclappApiCallCompleted::class, function (): void {
            throw new RuntimeException('listener broke');
        });
        $handler = Mockery::mock(ExceptionHandler::class);
        $handler->shouldReceive('report')->once()->with(Mockery::on(fn (Throwable $e) => $e->getMessage() === 'listener broke'));
        app()->instance(ExceptionHandler::class, $handler);

        $job = (new WeclappApiCallJob('article/id/9', 'PUT', ['name' => 'New']))->withFakeQueueInteractions();
        $job->handle();

        $job->assertNotReleased();
        $job->assertNotFailed();
        Http::assertSentCount(1);
    });
});
