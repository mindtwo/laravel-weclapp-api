<?php

declare(strict_types=1);

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Mindtwo\LaravelWeclappApi\Facades\WeclappClient;
use Mindtwo\LaravelWeclappApi\WeclappClient as Client;

beforeEach(function () {
    config()->set('weclapp-api.base_url', 'https://tenant.weclapp.com/webapp/api/v2/');
    config()->set('weclapp-api.token', 'test-token');
    config()->set('weclapp-api.page_size', 2);
});

it('resolves the client as a singleton from the container', function () {
    expect(app(Client::class))->toBeInstanceOf(Client::class)
        ->and(app(Client::class))->toBe(app(Client::class));
});

it('sends the authentication token header on requests', function () {
    Http::fake([
        '*' => Http::response(['result' => []], 200),
    ]);

    WeclappClient::get('party');

    Http::assertSent(fn ($request) => $request->hasHeader('AuthenticationToken', 'test-token')
        && $request->hasHeader('Accept', 'application/json'));
});

it('paginates a collection endpoint until a short page is returned', function () {
    Http::fakeSequence()
        ->push(['result' => [['id' => '1'], ['id' => '2']]], 200)
        ->push(['result' => [['id' => '3']]], 200);

    $result = WeclappClient::get('party');

    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result)->toHaveCount(3);

    Http::assertSentCount(2);
});

it('appends the dryRun flag to a post when requested', function () {
    Http::fake(['*' => Http::response(['id' => 'q-1'], 201)]);

    WeclappClient::post('quotation', ['foo' => 'bar'], dryRun: true);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'quotation?dryRun=true')
        && $request->method() === 'POST');
});

it('returns null when finding a missing record', function () {
    Http::fake(['*' => Http::response('', 404)]);

    expect(WeclappClient::find('party', 'nope'))->toBeNull();
});

it('puts to an arbitrary record without a typed endpoint class', function () {
    Http::fake(['*' => Http::response(['id' => '7'], 200)]);

    $result = WeclappClient::put('warehouseStock', 7, ['quantity' => 3]);

    expect($result)->toBe(['id' => '7']);
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/warehouseStock/id/7')
        && $request->method() === 'PUT'
        && $request['quantity'] === 3);
});

it('deletes an arbitrary record without a typed endpoint class', function () {
    Http::fake(['*' => Http::response('', 204)]);

    expect(WeclappClient::delete('ticket', 9))->toBeTrue();

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/ticket/id/9')
        && $request->method() === 'DELETE');
});

it('throws when a generic write fails', function () {
    Http::fake(['*' => Http::response(['error' => 'nope'], 422)]);

    WeclappClient::put('ticket', 1, []);
})->throws(RequestException::class);
