<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Mindtwo\LaravelWeclappApi\Models\Article;
use Mindtwo\LaravelWeclappApi\Models\Party;
use Mindtwo\LaravelWeclappApi\Models\Quotation;

beforeEach(function () {
    config()->set('weclapp-api.base_url', 'https://tenant.weclapp.com/webapp/api/v2/');
    config()->set('weclapp-api.token', 'test-token');
    config()->set('weclapp-api.timezone', 'UTC');
});

it('syncs customers into the parties mirror table', function () {
    Http::fake(['*customer*' => Http::response(['result' => [[
        'id'                => '12345',
        'customerNumber'    => 'C10001',
        'company'           => 'Test GmbH',
        'company2'          => 'Test Title',
        'email'             => 'test@example.com',
        'responsibleUserId' => '8001',
        'sectorId'          => '9001',
        'lastModifiedDate'  => 1700000000000,
    ]]], 200)]);

    $this->artisan('weclapp:sync customers')->assertSuccessful();

    $party = Party::query()->firstOrFail();

    expect($party->weclapp_id)->toBe(12345)
        ->and($party->customer_number)->toBe('C10001')
        ->and($party->company)->toBe('Test GmbH')
        ->and($party->company_2)->toBe('Test Title')
        ->and($party->responsible_user_id)->toBe(8001)
        ->and($party->last_modified)->not->toBeNull();
});

it('syncs suppliers into the parties mirror table', function () {
    Http::fake(['*supplier*' => Http::response(['result' => [[
        'id'             => 70001,
        'supplierNumber' => 'SU-10001',
        'company'        => 'Supplier GmbH',
        'firstName'      => 'Hans',
        'lastName'       => 'Schmidt',
        'salutation'     => 'MR',
        'partyType'      => 'ORGANIZATION',
    ]]], 200)]);

    $this->artisan('weclapp:sync suppliers')->assertSuccessful();

    $party = Party::query()->firstOrFail();

    expect($party->weclapp_id)->toBe(70001)
        ->and($party->supplier_number)->toBe('SU-10001')
        ->and($party->customer_number)->toBeNull()
        ->and($party->last_name)->toBe('Schmidt')
        ->and($party->party_type)->toBe('ORGANIZATION');
});

it('is idempotent, upserting on weclapp_id', function () {
    Http::fake(['*article*' => Http::response(['result' => [[
        'id'            => 20001,
        'articleNumber' => 'ART-001',
        'name'          => 'Widget',
        'active'        => true,
    ]]], 200)]);

    $this->artisan('weclapp:sync articles')->assertSuccessful();
    $this->artisan('weclapp:sync articles')->assertSuccessful();

    expect(Article::query()->count())->toBe(1)
        ->and(Article::query()->firstOrFail()->article_number)->toBe('ART-001');
});

it('fails on an unknown entity', function () {
    $this->artisan('weclapp:sync nope')
        ->expectsOutputToContain('Unknown entity "nope"')
        ->assertFailed();
});

it('sends a delta filter when updating', function () {
    Http::fake(['*quotation*' => Http::response(['result' => []], 200)]);

    $this->artisan('weclapp:update quotations --since=2026-01-01')->assertSuccessful();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'lastModifiedDate-gt='));

    expect(Quotation::query()->count())->toBe(0);
});
