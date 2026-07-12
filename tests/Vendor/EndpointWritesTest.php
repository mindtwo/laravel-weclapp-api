<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Mindtwo\LaravelWeclappApi\Facades\WeclappClient;
use Mindtwo\LaravelWeclappApi\Http\LazyResponseProxy;

it('updates a record with a PUT to the entity path and id', function () {
    Http::fake(['*/article/42' => Http::response(['id' => '42'], 200)]);

    $proxy = WeclappClient::articles()->update(42, ['name' => 'Renamed']);

    expect($proxy)->toBeInstanceOf(LazyResponseProxy::class)
        ->and($proxy->status())->toBe(200);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/article/42')
        && $request->method() === 'PUT'
        && $request['name'] === 'Renamed');
});

it('deletes a record with a DELETE to the entity path and id', function () {
    Http::fake(['*/article/42' => Http::response('', 204)]);

    $proxy = WeclappClient::articles()->delete(42);

    expect($proxy)->toBeInstanceOf(LazyResponseProxy::class)
        ->and($proxy->status())->toBe(204);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/article/42')
        && $request->method() === 'DELETE');
});

it('counts records for an endpoint', function () {
    Http::fake(['*/party/count*' => Http::response(['result' => 12], 200)]);

    expect(WeclappClient::parties()->count(['company-eq' => 'ACME']))->toBe(12);
});
