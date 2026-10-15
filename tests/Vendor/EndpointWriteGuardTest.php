<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Mindtwo\LaravelWeclappApi\Facades\WeclappClient;

beforeEach(function () {
    config()->set('weclapp-api.base_url', 'https://tenant.weclapp.com/webapp/api/v2/');
    config()->set('weclapp-api.token', 'test-token');
    config()->set('weclapp-api.writes_enabled', true);
});

// Not every Weclapp resource is writable. Failing in the guard keeps a wrong
// call from going out as a request the API would reject anyway.
it('refuses a write the resource does not offer', function (string $accessor, string $operation) {
    Http::fake();

    $endpoint = WeclappClient::{$accessor}();

    $call = match ($operation) {
        'create' => fn () => $endpoint->create([]),
        'update' => fn () => $endpoint->update(1, []),
        'delete' => fn () => $endpoint->delete(1),
    };

    expect($call)->toThrow(BadMethodCallException::class);
    Http::assertNothingSent();
})->with([
    // salesOpenItem is read-only in the spec
    'read-only create' => ['salesOpenItems', 'create'],
    'read-only update' => ['salesOpenItems', 'update'],
    'read-only delete' => ['salesOpenItems', 'delete'],
    // user has no DELETE /user/id/{id}
    'user delete' => ['users', 'delete'],
    // document has no POST /document
    'document create' => ['documents', 'create'],
    // purchaseRequisition only supports PUT
    'requisition create' => ['purchaseRequisitions', 'create'],
    'requisition delete' => ['purchaseRequisitions', 'delete'],
]);

it('names the resource and the supported writes in the error', function () {
    expect(fn () => WeclappClient::salesOpenItems()->create([]))
        ->toThrow(BadMethodCallException::class, 'does not support create');

    expect(fn () => WeclappClient::users()->delete(1))
        ->toThrow(BadMethodCallException::class, 'Supported writes: create, update.');
});

it('still allows the writes a resource does offer', function () {
    Http::fake(['*' => Http::response(['id' => '1'], 200)]);

    expect(WeclappClient::tickets()->create(['title' => 'x'])->status())->toBe(200)
        ->and(WeclappClient::users()->update(1, ['email' => 'a@b.c'])->status())->toBe(200)
        ->and(WeclappClient::documents()->delete(1)->status())->toBe(200);
});

it('allows reads on a read-only resource', function () {
    Http::fake([
        '*/salesOpenItem/count*' => Http::response(['result' => 5], 200),
        '*/salesOpenItem/id/7'   => Http::response(['id' => '7'], 200),
        '*/salesOpenItem*'       => Http::response(['result' => [['id' => '1']]], 200),
    ]);

    expect(WeclappClient::salesOpenItems()->query())->toHaveCount(1)
        ->and(WeclappClient::salesOpenItems()->count())->toBe(5)
        ->and(WeclappClient::salesOpenItems()->find(7)?->id)->toBe('7');
});
