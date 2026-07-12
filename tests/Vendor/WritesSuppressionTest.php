<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Mindtwo\LaravelWeclappApi\Events\WeclappApiCallCompleted;
use Mindtwo\LaravelWeclappApi\Facades\WeclappClient;

beforeEach(function () {
    config()->set('weclapp-api.base_url', 'https://tenant.weclapp.com/webapp/api/v2/');
    config()->set('weclapp-api.token', 'test-token');
    config()->set('weclapp-api.writes_enabled', false);
});

it('suppresses a low-level post when writes are disabled', function () {
    Http::fake();
    Event::fake([WeclappApiCallCompleted::class]);

    $result = WeclappClient::post('quotation', ['foo' => 'bar']);

    expect($result)->toBe([]);
    Http::assertNothingSent();
    Event::assertDispatched(WeclappApiCallCompleted::class, fn ($e) => $e->method === 'POST'
        && $e->endpoint === 'quotation'
        && $e->suppressed === true);
});

it('suppresses an endpoint write via the proxy and returns a neutral response', function () {
    Http::fake();
    Event::fake([WeclappApiCallCompleted::class]);

    $proxy = WeclappClient::quotations()->create(['customerId' => '5']);

    expect($proxy->status())->toBe(200)
        ->and($proxy->json())->toBe([]);
    Http::assertNothingSent();
    Event::assertDispatched(WeclappApiCallCompleted::class, fn ($e) => $e->method === 'POST'
        && $e->endpoint === 'quotation'
        && $e->suppressed === true);
});

it('still allows reads when writes are disabled', function () {
    Http::fake(['*/article*' => Http::response(['result' => [['id' => '1']]], 200)]);

    $result = WeclappClient::articles()->query();

    expect($result)->toHaveCount(1);
    Http::assertSent(fn ($request) => $request->method() === 'GET');
});

it('sends writes when the flag is enabled', function () {
    config()->set('weclapp-api.writes_enabled', true);
    Http::fake(['*/quotation' => Http::response(['id' => 'q-1'], 201)]);

    $result = WeclappClient::post('quotation', ['foo' => 'bar']);

    expect($result)->toBe(['id' => 'q-1']);
    Http::assertSent(fn ($request) => $request->method() === 'POST');
});

it('does not suppress when the flag is unset (defaults to live)', function () {
    config()->set('weclapp-api.writes_enabled', null);
    Http::fake(['*' => Http::response([], 200)]);

    WeclappClient::post('quotation', []);

    Http::assertSent(fn ($request) => $request->method() === 'POST');
});
