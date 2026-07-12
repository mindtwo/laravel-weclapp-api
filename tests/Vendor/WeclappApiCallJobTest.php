<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mindtwo\LaravelWeclappApi\Events\WeclappApiCallCompleted;
use Mindtwo\LaravelWeclappApi\Facades\WeclappClient;
use Mindtwo\LaravelWeclappApi\Jobs\WeclappApiCallJob;

beforeEach(function () {
    config()->set('weclapp-api.base_url', 'https://tenant.weclapp.com/webapp/api/v2/');
    config()->set('weclapp-api.token', 'test-token');
});

it('can be queued from the proxy', function () {
    Queue::fake();

    $job = WeclappClient::articles()->update(9, ['name' => 'New'])->getJob();

    dispatch($job);

    Queue::assertPushed(WeclappApiCallJob::class, fn ($queued) => $queued->method === 'PUT'
        && $queued->endpoint === 'article/9');
});

it('performs the request and emits a completion event when handled', function () {
    Event::fake([WeclappApiCallCompleted::class]);
    Http::fake(['*/article/9' => Http::response(['id' => '9'], 200)]);

    (new WeclappApiCallJob('article/9', 'PUT', ['name' => 'New']))->handle();

    Http::assertSent(fn ($request) => $request->method() === 'PUT');
    Event::assertDispatched(WeclappApiCallCompleted::class, fn ($event) => $event->successful === true);
});
