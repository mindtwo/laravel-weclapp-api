<?php

declare(strict_types=1);

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Mindtwo\LaravelWeclappApi\Events\WeclappApiCallCompleted;
use Mindtwo\LaravelWeclappApi\Exceptions\WeclappApiCallFailedException;
use Mindtwo\LaravelWeclappApi\Facades\WeclappClient;
use Mindtwo\LaravelWeclappApi\Jobs\WeclappApiCallJob;

beforeEach(function () {
    config()->set('weclapp-api.base_url', 'https://tenant.weclapp.com/webapp/api/v2/');
    config()->set('weclapp-api.token', 'test-token');
});

it('executes synchronously on response access and emits a completion event', function () {
    Event::fake([WeclappApiCallCompleted::class]);
    Http::fake(['*/quotation' => Http::response(['id' => 'q-1'], 201)]);

    $proxy = WeclappClient::quotations()->create(['customerId' => '5']);

    expect($proxy->status())->toBe(201);

    Event::assertDispatched(WeclappApiCallCompleted::class, fn ($event) => $event->method === 'POST'
        && $event->endpoint === 'quotation'
        && $event->successful === true);
});

it('emits a failed completion event without throwing on a 4xx', function () {
    Event::fake([WeclappApiCallCompleted::class]);
    Http::fake(['*/quotation' => Http::response(['error' => 'bad'], 422)]);

    $proxy = WeclappClient::quotations()->create([]);

    expect($proxy->status())->toBe(422);

    Event::assertDispatched(WeclappApiCallCompleted::class, fn ($event) => $event->successful === false
        && $event->statusCode === 422);
});

it('emits a failed completion event when the request never gets a response', function () {
    config()->set('weclapp-api.http.retry_times', 1);
    config()->set('weclapp-api.http.retry_sleep', 0);
    Event::fake([WeclappApiCallCompleted::class]);
    Http::fake(fn () => throw new ConnectionException('timed out'));

    $proxy = WeclappClient::articles()->update(9, ['name' => 'x']);

    expect(fn () => $proxy->status())->toThrow(ConnectionException::class);

    Event::assertDispatched(WeclappApiCallCompleted::class, fn ($event) => $event->method === 'PUT'
        && $event->endpoint === 'article/id/9'
        && $event->statusCode === 0
        && $event->successful === false
        && $event->response === ['error' => 'timed out']);
});

it('offers array access to the response body, like a Response does', function () {
    Http::fake(['*/quotation' => Http::response(['id' => 'q-1'], 201)]);

    $proxy = WeclappClient::quotations()->create(['customerId' => '5']);

    expect(isset($proxy['id']))->toBeTrue()
        ->and($proxy['id'])->toBe('q-1')
        ->and(isset($proxy['missing']))->toBeFalse();
});

it('hands back an undispatched job and does not execute', function () {
    Http::fake();

    $job = WeclappClient::quotations()->create(['customerId' => '5'])->getJob();

    expect($job)->toBeInstanceOf(WeclappApiCallJob::class)
        ->and($job->method)->toBe('POST')
        ->and($job->endpoint)->toBe('quotation')
        ->and($job->body)->toBe(['customerId' => '5']);

    Http::assertNothingSent();
});

it('refuses to execute after the job was retrieved', function () {
    Http::fake();

    $proxy = WeclappClient::quotations()->create([]);
    $proxy->getJob();

    expect(fn () => $proxy->status())->toThrow(RuntimeException::class);
});

describe('a proxy nobody reads', function () {
    beforeEach(function () {
        // The transport's own retries are the client's concern; count the proxy's attempts only.
        config()->set('weclapp-api.http.retry_times', 1);
        config()->set('weclapp-api.http.retry_sleep', 0);
    });

    it('still sends its request when it goes out of scope', function () {
        Event::fake([WeclappApiCallCompleted::class]);
        Http::fake(['*/ticket/id/9' => Http::response('', 204)]);

        (function (): void {
            WeclappClient::tickets()->delete(9);
        })();

        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_ends_with($request->url(), '/ticket/id/9'));
        Event::assertDispatched(WeclappApiCallCompleted::class, fn ($event) => $event->method === 'DELETE');
    });

    it('sends nothing when the job was handed out instead', function () {
        Http::fake();

        (function (): void {
            WeclappClient::tickets()->delete(9)->getJob();
        })();

        Http::assertNothingSent();
    });

    it('does not resend an attempt that already threw', function () {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;

            throw new ConnectionException('timed out');
        });

        (function (): void {
            $proxy = WeclappClient::tickets()->delete(9);

            try {
                $proxy->status();
            } catch (ConnectionException) {
                // The caller saw it; the destructor must not try again.
            }
        })();

        expect($calls)->toBe(1);
    });

    it('reports rather than throws a failure of the late send', function () {
        Http::fake(fn () => throw new ConnectionException('timed out'));
        $handler = Mockery::mock(ExceptionHandler::class);
        $handler->shouldReceive('report')->once()->with(Mockery::type(ConnectionException::class));
        app()->instance(ExceptionHandler::class, $handler);

        (function (): void {
            WeclappClient::tickets()->delete(9);
        })();
    });

    it('reports a refusal of the late send, since nobody reads its status', function () {
        Http::fake(['*/ticket/id/9' => Http::response(['detail' => 'not allowed'], 403)]);
        $handler = Mockery::mock(ExceptionHandler::class);
        $handler->shouldReceive('report')->once()->with(Mockery::on(fn (Throwable $e) => $e instanceof WeclappApiCallFailedException
            && $e->statusCode === 403
            && str_contains($e->getMessage(), 'not allowed')));
        app()->instance(ExceptionHandler::class, $handler);

        (function (): void {
            WeclappClient::tickets()->delete(9);
        })();
    });

    it('reports nothing when the late send succeeds', function () {
        Http::fake(['*/ticket/id/9' => Http::response('', 204)]);
        $handler = Mockery::mock(ExceptionHandler::class);
        $handler->shouldNotReceive('report');
        app()->instance(ExceptionHandler::class, $handler);

        (function (): void {
            WeclappClient::tickets()->delete(9);
        })();
    });
});
