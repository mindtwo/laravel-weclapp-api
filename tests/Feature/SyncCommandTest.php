<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Mindtwo\LaravelWeclappApi\Models\Article;
use Mindtwo\LaravelWeclappApi\Models\ArticlePrice;
use Mindtwo\LaravelWeclappApi\Models\Party;
use Mindtwo\LaravelWeclappApi\Models\Quotation;

beforeEach(function () {
    config()->set('weclapp-api.base_url', 'https://tenant.weclapp.com/webapp/api/v2/');
    config()->set('weclapp-api.token', 'test-token');
    config()->set('weclapp-api.timezone', 'UTC');
});

it('syncs customers into the parties mirror table', function () {
    Http::fake(['*party*' => Http::response(['result' => [[
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

    Http::assertSent(fn ($request) => str_contains($request->url(), '/v2/party?')
        && str_contains($request->url(), 'customer-eq=true'));
});

it('syncs suppliers into the parties mirror table', function () {
    Http::fake(['*party*' => Http::response(['result' => [[
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

    Http::assertSent(fn ($request) => str_contains($request->url(), '/v2/party?')
        && str_contains($request->url(), 'supplier-eq=true'));
});

it('combines the entity filter with a delta filter when updating', function () {
    Http::fake(['*party*' => Http::response(['result' => []], 200)]);

    $this->artisan('weclapp:update customers --since=2026-01-01')->assertSuccessful();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'customer-eq=true')
        && str_contains($request->url(), 'lastModifiedDate-gt='));
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

it('maps a quotation, taking version from quotationVersion', function () {
    Http::fake(['*quotation*' => Http::response(['result' => [[
        'id'               => 40001,
        'quotationNumber'  => 'QU-10001',
        'customerId'       => 12345,
        'grossAmount'      => 29750.00,
        'netAmount'        => 25000.00,
        'quotationVersion' => 3,
        'status'           => 'OPEN',
    ]]], 200)]);

    $this->artisan('weclapp:sync quotations')->assertSuccessful();

    $quotation = Quotation::query()->firstOrFail();

    expect($quotation->weclapp_id)->toBe(40001)
        ->and($quotation->quotation_number)->toBe('QU-10001')
        ->and($quotation->version)->toBe(3);
});

it('flattens the price-relevant reductionAdditions entry onto the price row', function () {
    Http::fake(['*articlePrice*' => Http::response(['result' => [[
        'id'                 => 152796,
        'articleId'          => 7371,
        'customerId'         => 4983,
        'currencyId'         => 248,
        'price'              => '3500',
        'priceScaleType'     => 'SCALE_FROM',
        'priceScaleValue'    => '0',
        'salesChannel'       => 'NET1',
        'reductionAdditions' => [[
            'id'    => 152797,
            'type'  => 'REDUCTION_PERCENT',
            'value' => '22',
        ]],
    ]]], 200)]);

    $this->artisan('weclapp:sync article-prices')->assertSuccessful();

    $price = ArticlePrice::query()->firstOrFail();

    expect($price->weclapp_id)->toBe(152796)
        ->and($price->customer_id)->toBe(4983)
        ->and($price->price)->toEqual('3500.0000')
        ->and($price->reduction_type)->toBe('REDUCTION_PERCENT')
        ->and($price->reduction_value)->toEqual('22.0000');
});

it('leaves the reduction columns null on a price without reductionAdditions', function () {
    Http::fake(['*articlePrice*' => Http::response(['result' => [[
        'id'                 => 152800,
        'articleId'          => 7371,
        'price'              => '4000',
        'reductionAdditions' => [],
    ]]], 200)]);

    $this->artisan('weclapp:sync article-prices')->assertSuccessful();

    $price = ArticlePrice::query()->firstOrFail();

    expect($price->customer_id)->toBeNull()
        ->and($price->reduction_type)->toBeNull()
        ->and($price->reduction_value)->toBeNull();
});

it('flattens the flagged main image onto the article row', function () {
    Http::fake(['*article*' => Http::response(['result' => [[
        'id'            => 20001,
        'articleNumber' => 'ART-001',
        'articleImages' => [
            [
                'id'        => 1965,
                'fileName'  => 'streetlight-1388418_1920.jpg',
                'mainImage' => false,
            ],
            [
                'id'        => 2052097,
                'fileName'  => 'header.jpeg',
                'mainImage' => true,
            ],
        ],
    ]]], 200)]);

    $this->artisan('weclapp:sync articles')->assertSuccessful();

    // The flagged entry, not the first one — position is not the rule.
    $article = Article::query()->firstOrFail();

    expect($article->main_image_id)->toBe(2052097)
        ->and($article->main_image_filename)->toBe('header.jpeg');
});

it('leaves the main image columns null on an article with no images', function () {
    Http::fake(['*article*' => Http::response(['result' => [[
        'id'            => 20001,
        'articleNumber' => 'ART-001',
        'articleImages' => [],
    ]]], 200)]);

    $this->artisan('weclapp:sync articles')->assertSuccessful();

    $article = Article::query()->firstOrFail();

    expect($article->main_image_id)->toBeNull()
        ->and($article->main_image_filename)->toBeNull();
});

it('leaves the main image columns null when every image is unflagged', function () {
    Http::fake(['*article*' => Http::response(['result' => [[
        'id'            => 20001,
        'articleImages' => [[
            'id'        => 1965,
            'fileName'  => 'streetlight-1388418_1920.jpg',
            'mainImage' => false,
        ]],
    ]]], 200)]);

    $this->artisan('weclapp:sync articles')->assertSuccessful();

    expect(Article::query()->firstOrFail()->main_image_id)->toBeNull();
});

it('clears a stale main image when Weclapp no longer has one', function () {
    // The gap page reads these columns to decide whether Weclapp holds an image.
    // Keeping the old value would make it claim an image that has been deleted.
    Article::factory()->withMainImage()->create(['weclapp_id' => 20001]);

    Http::fake(['*article*' => Http::response(['result' => [[
        'id'            => 20001,
        'articleImages' => [],
    ]]], 200)]);

    $this->artisan('weclapp:sync articles')->assertSuccessful();

    $article = Article::query()->firstOrFail();

    expect($article->main_image_id)->toBeNull()
        ->and($article->main_image_filename)->toBeNull();
});

it('mirrors both text fields Weclapp holds for only some articles', function () {
    Http::fake(['*article*' => Http::response(['result' => [
        [
            'id'                => 20001,
            'shortDescription1' => 'Kurzbeschreibung',
            'longText'          => '<p>Langtext</p>',
        ],
        [
            'id' => 20002,
        ],
    ]], 200)]);

    $this->artisan('weclapp:sync articles')->assertSuccessful();

    $withText = Article::query()->where('weclapp_id', 20001)->firstOrFail();
    $without = Article::query()->where('weclapp_id', 20002)->firstOrFail();

    expect($withText->short_description_1)->toBe('Kurzbeschreibung')
        ->and($withText->long_text)->toBe('<p>Langtext</p>')
        ->and($without->short_description_1)->toBeNull()
        ->and($without->long_text)->toBeNull();
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

it('archives mirror rows Weclapp no longer returns', function () {
    Article::factory()->create(['weclapp_id' => 20001, 'article_number' => 'STILL-THERE']);
    Article::factory()->create(['weclapp_id' => 20002, 'article_number' => 'GONE-FROM-WECLAPP']);

    Http::fake(['*article*' => Http::response(['result' => [[
        'id'            => 20001,
        'articleNumber' => 'STILL-THERE',
    ]]], 200)]);

    $this->artisan('weclapp:sync articles')
        ->expectsOutputToContain('Archived 1 articles no longer in Weclapp.')
        ->assertSuccessful();

    expect(Article::query()->count())->toBe(1)
        ->and(Article::query()->firstOrFail()->weclapp_id)->toBe(20001)
        ->and(Article::withTrashed()->where('weclapp_id', 20002)->firstOrFail()->deleted_at)->not->toBeNull();
});

it('restores an archived row onto itself when the record comes back', function () {
    $article = Article::factory()->create(['weclapp_id' => 20002, 'article_number' => 'OLD']);
    $article->delete();

    Http::fake(['*article*' => Http::response(['result' => [[
        'id'            => 20002,
        'articleNumber' => 'BACK-AGAIN',
    ]]], 200)]);

    $this->artisan('weclapp:sync articles')->assertSuccessful();

    // Restored in place rather than inserted alongside the archived row.
    expect(Article::withTrashed()->count())->toBe(1)
        ->and(Article::query()->firstOrFail()->article_number)->toBe('BACK-AGAIN')
        ->and(Article::query()->firstOrFail()->id)->toBe($article->id);
});

it('never archives on a delta sync, where a partial result is the whole point', function () {
    Article::factory()->create(['weclapp_id' => 20001]);
    Article::factory()->create(['weclapp_id' => 20002]);

    Http::fake(['*article*' => Http::response(['result' => [[
        'id' => 20001,
    ]]], 200)]);

    $this->artisan('weclapp:update articles --since=2026-01-01')->assertSuccessful();

    expect(Article::query()->count())->toBe(2);
});

it('never archives on an empty response, which cannot be told apart from an outage', function () {
    Article::factory()->create(['weclapp_id' => 20001]);
    Article::factory()->create(['weclapp_id' => 20002]);

    Http::fake(['*article*' => Http::response(['result' => []], 200)]);

    $this->artisan('weclapp:sync articles')->assertSuccessful();

    expect(Article::query()->count())->toBe(2);
});

it('does not archive across definitions that share a model', function () {
    // customers and suppliers are both filtered views of /party. A customer sync
    // that archived "everything unseen" would take every supplier with it.
    Party::factory()->create(['weclapp_id' => 70001, 'supplier_number' => 'SU-1', 'customer_number' => null]);

    Http::fake(['*party*' => Http::response(['result' => [[
        'id'             => 12345,
        'customerNumber' => 'C10001',
    ]]], 200)]);

    $this->artisan('weclapp:sync customers')->assertSuccessful();

    expect(Party::query()->count())->toBe(2)
        ->and(Party::query()->where('weclapp_id', 70001)->exists())->toBeTrue();
});

it('reports nothing archived when the mirror already matches', function () {
    Article::factory()->create(['weclapp_id' => 20001]);

    Http::fake(['*article*' => Http::response(['result' => [[
        'id' => 20001,
    ]]], 200)]);

    $this->artisan('weclapp:sync articles')
        ->doesntExpectOutputToContain('Archived')
        ->assertSuccessful();

    expect(Article::withTrashed()->count())->toBe(1);
});
