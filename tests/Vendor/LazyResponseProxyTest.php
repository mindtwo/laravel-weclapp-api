<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Mindtwo\LaravelWeclappApi\Events\WeclappApiCallCompleted;
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
