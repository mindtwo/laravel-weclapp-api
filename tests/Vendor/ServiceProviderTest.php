<?php

declare(strict_types=1);

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Mindtwo\LaravelWeclappApi\Events\WeclappApiCallCompleted;
use Mindtwo\LaravelWeclappApi\Jobs\WeclappApiCallJob;
use Mindtwo\LaravelWeclappApi\Listeners\LogWeclappEvent;
use Mindtwo\LaravelWeclappApi\WeclappApiServiceProvider;

it('rate-limits queued calls by the configured per-minute value', function () {
    config()->set('weclapp-api.rate_limit_per_minute', 42);

    $limiter = RateLimiter::limiter('weclapp-api-jobs');
    $limit = $limiter(new WeclappApiCallJob('quotation', 'POST'));

    expect($limit)->toBeInstanceOf(Limit::class)
        ->and($limit->maxAttempts)->toBe(42)
        ->and($limit->decaySeconds)->toBe(60);
});

it('registers the logging listener only when event logging is enabled', function () {
    // The package test case boots with logging off.
    expect(Event::hasListeners(WeclappApiCallCompleted::class))->toBeFalse();

    config()->set('weclapp-api.logging.enabled', true);
    app()->register(WeclappApiServiceProvider::class, force: true)->boot();

    expect(Event::hasListeners(WeclappApiCallCompleted::class))->toBeTrue()
        ->and(collect(Event::getRawListeners()[WeclappApiCallCompleted::class] ?? []))
        ->toContain(LogWeclappEvent::class);
});
