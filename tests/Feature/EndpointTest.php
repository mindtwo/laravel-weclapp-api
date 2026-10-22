<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Mindtwo\LaravelWeclappApi\Facades\WeclappClient;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Party;
use Mindtwo\LaravelWeclappApi\Http\LazyResponseProxy;

beforeEach(function () {
    config()->set('weclapp-api.base_url', 'https://tenant.weclapp.com/webapp/api/v2/');
    config()->set('weclapp-api.token', 'test-token');
    config()->set('weclapp-api.page_size', 1000);
});

it('exposes each endpoint accessor as a singleton', function () {
    expect(WeclappClient::parties())->toBeInstanceOf(Party::class)
        ->and(WeclappClient::parties())->toBe(app(Party::class));
});

it('queries a collection endpoint against the entity path', function () {
    Http::fake([
        '*/party*' => Http::response(['result' => [['id' => '1'], ['id' => '2']]], 200),
    ]);

    $result = WeclappClient::parties()->query(['company-eq' => 'ACME']);

    expect($result)->toBeInstanceOf(Collection::class)->toHaveCount(2);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/party')
        && str_contains($request->url(), 'company-eq=ACME'));
});

it('finds a single record by id', function () {
    Http::fake(['*/article/id/42' => Http::response(['id' => '42', 'name' => 'Widget'], 200)]);

    expect(WeclappClient::articles()->find(42)?->name)->toBe('Widget');
});

it('counts records via the count sub-resource', function () {
    Http::fake(['*/salesOrder/count*' => Http::response(['result' => 7], 200)]);

    expect(WeclappClient::salesOrders()->count())->toBe(7);
});

it('returns a lazy proxy for writes and posts to the entity path', function () {
    Http::fake(['*/quotation' => Http::response(['id' => 'q-1'], 201)]);

    $proxy = WeclappClient::quotations()->create(['customerId' => '5']);

    expect($proxy)->toBeInstanceOf(LazyResponseProxy::class)
        ->and($proxy->json())->toBe(['id' => 'q-1']);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/quotation')
        && $request->method() === 'POST');
});

describe('customer and supplier views', function () {
    it('answer null from find() for a party outside the view', function () {
        Http::fake(['*/party/id/7' => Http::response(['id' => '7', 'customer' => false, 'supplier' => true], 200)]);

        expect(WeclappClient::customers()->find(7))->toBeNull()
            ->and(WeclappClient::suppliers()->find(7)?->id)->toBe('7')
            ->and(WeclappClient::parties()->find(7)?->id)->toBe('7');
    });

    it('set the role flag on create(), unless the caller decides otherwise', function () {
        Http::fake(['*/party' => Http::response(['id' => '8'], 201)]);

        WeclappClient::customers()->create(['company' => 'ACME'])->status();
        WeclappClient::suppliers()->create(['company' => 'ACME', 'supplier' => false])->status();

        Http::assertSent(fn ($request) => $request['company'] === 'ACME' && ($request['customer'] ?? null) === true);
        Http::assertSent(fn ($request) => $request['company'] === 'ACME' && ($request['supplier'] ?? null) === false);
    });
});
