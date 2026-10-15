<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Mindtwo\LaravelWeclappApi\Facades\WeclappClient;

beforeEach(function () {
    config()->set('weclapp-api.base_url', 'https://tenant.weclapp.com/webapp/api/v2/');
    config()->set('weclapp-api.token', 'test-token');
});

// Weclapp addresses a single record as /{resource}/id/{id}. A bare
// /{resource}/{id} exists for no resource in the v2 spec, and because find()
// maps 404 to null, getting this wrong fails silently rather than loudly.
it('addresses a single record through the /id/ segment', function (string $verb) {
    Http::fake(['*' => Http::response(['id' => '42'], 200)]);

    match ($verb) {
        'GET'    => WeclappClient::articles()->find(42),
        'PUT'    => WeclappClient::articles()->update(42, ['name' => 'x'])->status(),
        'DELETE' => WeclappClient::articles()->delete(42)->status(),
    };

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/article/id/42')
        && $request->method() === $verb);
})->with(['GET', 'PUT', 'DELETE']);

it('narrows customers and suppliers to a filtered party query', function (string $accessor, string $filter) {
    Http::fake(['*' => Http::response(['result' => []], 200)]);

    WeclappClient::{$accessor}()->query();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/v2/party?')
        && str_contains($request->url(), $filter.'=true'));
})->with([
    'customers' => ['customers', 'customer-eq'],
    'suppliers' => ['suppliers', 'supplier-eq'],
]);

it('keeps the default filter when caller filters are added', function () {
    Http::fake(['*' => Http::response(['result' => []], 200)]);

    WeclappClient::customers()->query(['company-eq' => 'ACME']);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'customer-eq=true')
        && str_contains($request->url(), 'company-eq=ACME'));
});

it('applies the default filter to a count as well', function () {
    Http::fake(['*/party/count*' => Http::response(['result' => 3], 200)]);

    expect(WeclappClient::suppliers()->count())->toBe(3);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'supplier-eq=true'));
});
