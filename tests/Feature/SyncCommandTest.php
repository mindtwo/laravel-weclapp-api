<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Mindtwo\LaravelWeclappApi\Models\Article;
use Mindtwo\LaravelWeclappApi\Models\ArticleCategory;
use Mindtwo\LaravelWeclappApi\Models\ArticlePrice;
use Mindtwo\LaravelWeclappApi\Models\Party;
use Mindtwo\LaravelWeclappApi\Models\Project;
use Mindtwo\LaravelWeclappApi\Models\Quotation;
use Mindtwo\LaravelWeclappApi\Models\SalesInvoice;
use Mindtwo\LaravelWeclappApi\Models\SalesOrder;
use Mindtwo\LaravelWeclappApi\Models\User;

beforeEach(function () {
    config()->set('weclapp-api.base_url', 'https://tenant.weclapp.com/webapp/api/v2/');
    config()->set('weclapp-api.token', 'test-token');
    config()->set('weclapp-api.timezone', 'UTC');
});

it('syncs every party with its roles and name into the parties mirror table', function () {
    Http::fakeSequence()
        ->push(['result' => [
            [
                'id'                => '12345',
                'customer'          => true,
                'supplier'          => false,
                'customerNumber'    => 'C10001',
                'company'           => 'Test GmbH',
                'company2'          => 'Test Title',
                'email'             => 'test@example.com',
                'responsibleUserId' => '8001',
                'sectorId'          => '9001',
                'lastModifiedDate'  => 1700000000000,
            ],
            [
                'id'             => '70001',
                'customer'       => false,
                'supplier'       => true,
                'supplierNumber' => 'SU-10001',
                'company'        => 'Supplier GmbH',
                'firstName'      => 'Hans',
                'lastName'       => 'Schmidt',
                'salutation'     => 'MR',
                'partyType'      => 'ORGANIZATION',
            ],
            // A private customer: a person with no company, which the old
            // customers map mirrored without a name.
            [
                'id'        => '80001',
                'customer'  => true,
                'partyType' => 'PERSON',
                'firstName' => 'Erika',
                'lastName'  => 'Mustermann',
            ],
        ]], 200)
        ->push(['result' => []], 200);

    $this->artisan('weclapp:sync parties')->assertSuccessful();

    $customer = Party::query()->where('weclapp_id', 12345)->firstOrFail();
    $supplier = Party::query()->where('weclapp_id', 70001)->firstOrFail();
    $person = Party::query()->where('weclapp_id', 80001)->firstOrFail();

    expect($customer->is_customer)->toBeTrue()
        ->and($customer->is_supplier)->toBeFalse()
        ->and($customer->customer_number)->toBe('C10001')
        ->and($customer->company_2)->toBe('Test Title')
        ->and($customer->responsible_user_id)->toBe(8001)
        ->and($customer->last_modified)->not->toBeNull()
        ->and($supplier->is_supplier)->toBeTrue()
        ->and($supplier->supplier_number)->toBe('SU-10001')
        ->and($supplier->customer_number)->toBeNull()
        ->and($supplier->last_name)->toBe('Schmidt')
        ->and($supplier->party_type)->toBe('ORGANIZATION')
        ->and($person->is_customer)->toBeTrue()
        ->and($person->first_name)->toBe('Erika')
        ->and($person->last_name)->toBe('Mustermann')
        ->and(Party::customers()->count())->toBe(2)
        ->and(Party::suppliers()->count())->toBe(1);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/v2/party?')
        && ! str_contains($request->url(), 'customer-eq'));
});

it('drops a role a party lost in Weclapp', function () {
    Party::factory()->create(['weclapp_id' => 12345, 'is_customer' => true, 'customer_number' => 'C10001']);
    Http::fakeSequence()
        ->push(['result' => [['id' => '12345', 'customer' => false, 'supplier' => false]]], 200)
        ->push(['result' => []], 200);

    $this->artisan('weclapp:sync parties')->assertSuccessful();

    $party = Party::query()->where('weclapp_id', 12345)->firstOrFail();

    expect($party->is_customer)->toBeFalse()
        ->and($party->customer_number)->toBeNull();
});

it('sends the delta filter when updating parties', function () {
    Http::fake(['*party*' => Http::response(['result' => []], 200)]);

    $this->artisan('weclapp:update parties --since=2026-01-01')->assertSuccessful();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'lastModifiedDate-gt='));
});

it('is idempotent, upserting on weclapp_id', function () {
    Http::fake(['*article*' => Http::response(['result' => [[
        'id'            => 20001,
        'articleNumber' => 'ART-001',
        'name'          => 'Widget',
        'active'        => true,
    ]]], 200)]);

    $this->artisan('weclapp:sync articles')->assertSuccessful();

    // The second run must leave an unchanged row alone: no write, so no
    // updated_at bump that would make every row look changed on every sync.
    $before = Article::query()->firstOrFail()->updated_at;
    $this->travel(1)->hour();

    $this->artisan('weclapp:sync articles')->assertSuccessful();

    expect(Article::query()->count())->toBe(1)
        ->and(Article::query()->firstOrFail()->article_number)->toBe('ART-001')
        ->and(Article::query()->firstOrFail()->updated_at)->toEqual($before);
});

it('looks existing rows up per chunk rather than per record', function () {
    $records = collect(range(1, 1200))->map(fn (int $i) => ['id' => 40000 + $i, 'articleNumber' => "A-{$i}"])->all();
    // 1200 is a full page at the default page size, so the client asks for a
    // second page; answer it with nothing.
    Http::fakeSequence()
        ->push(['result' => $records], 200)
        ->push(['result' => []], 200);

    $selects = 0;
    DB::listen(function (QueryExecuted $query) use (&$selects): void {
        if (str_starts_with(ltrim($query->sql), 'select') && str_contains($query->sql, 'from "weclapp_articles"')) {
            $selects++;
        }
    });

    $this->artisan('weclapp:sync articles')->assertSuccessful();

    $lookups = $selects;

    expect(Article::query()->count())->toBe(1200)
        ->and($lookups)->toBe(3);
});

it('never archives when no record carried an id, which would otherwise match every row', function () {
    Article::factory()->create(['weclapp_id' => 20001]);
    Http::fake(['*article*' => Http::response(['result' => [['articleNumber' => 'NO-ID']]], 200)]);

    $this->artisan('weclapp:sync articles')->assertSuccessful();

    expect(Article::query()->count())->toBe(1);
});

it('maps a quotation, keeping the revision apart from the lock version', function () {
    Http::fake(['*quotation*' => Http::response(['result' => [[
        'id'               => 40001,
        'quotationNumber'  => 'QU-10001',
        'customerId'       => 12345,
        'grossAmount'      => 29750.00,
        'netAmount'        => 25000.00,
        'quotationVersion' => 3,
        'version'          => '7',
        'status'           => 'OPEN',
    ]]], 200)]);

    $this->artisan('weclapp:sync quotations')->assertSuccessful();

    $quotation = Quotation::query()->firstOrFail();

    expect($quotation->weclapp_id)->toBe(40001)
        ->and($quotation->quotation_number)->toBe('QU-10001')
        ->and($quotation->quotation_version)->toBe(3)
        ->and($quotation->version)->toBe(7);
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
        ->and($price->price)->toEqual('3500.00000')
        ->and($price->reduction_type)->toBe('REDUCTION_PERCENT')
        ->and($price->reduction_value)->toEqual('22.00000');
});

it('keeps an absolute reduction, a large amount and the fifth decimal', function () {
    Http::fakeSequence()
        ->push(['result' => [[
            'id'                 => 152798,
            'articleId'          => 7371,
            'price'              => '0.12345',
            'reductionAdditions' => [['id' => 152799, 'type' => 'REDUCTION_ABS', 'value' => '12345.5']],
        ]]], 200)
        ->push(['result' => []], 200);

    $this->artisan('weclapp:sync article-prices')->assertSuccessful();

    $price = ArticlePrice::query()->firstOrFail();

    expect($price->price)->toEqual('0.12345')
        ->and($price->reduction_type)->toBe('REDUCTION_ABS')
        ->and($price->reduction_value)->toEqual('12345.50000');
});

it('mirrors the first of several reductionAdditions and says so in the log', function () {
    Log::spy();
    Http::fakeSequence()
        ->push(['result' => [[
            'id'                 => 152801,
            'articleId'          => 7371,
            'price'              => '100',
            'reductionAdditions' => [
                ['id' => 1, 'type' => 'REDUCTION_PERCENT', 'value' => '10'],
                ['id' => 2, 'type' => 'ADDITIONAL_ABS', 'value' => '5'],
            ],
        ]]], 200)
        ->push(['result' => []], 200);

    $this->artisan('weclapp:sync article-prices')->assertSuccessful();

    expect(ArticlePrice::query()->firstOrFail()->reduction_type)->toBe('REDUCTION_PERCENT');
    Log::shouldHaveReceived('warning')->once()->withArgs(
        fn (string $message, array $context) => str_contains($message, 'several reductionAdditions') && $context['price_id'] === 152801,
    );
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

it('clears a field the customer removed in Weclapp, which the payload then simply omits', function () {
    // Weclapp drops empty fields from its JSON instead of sending null. Skipping
    // absent keys kept the last mirrored value: an end date cleared in Weclapp on
    // 2026-10-14 stayed in the mirror, and the resolver went on reporting the row
    // as expired. Every clearable field is covered — a scalar, a date, a path.
    ArticlePrice::factory()->create([
        'weclapp_id'      => 1099544,
        'article_id'      => 1099541,
        'customer_id'     => 1360,
        'price'           => 109,
        'end_date'        => '2026-10-12 21:59:59',
        'reduction_type'  => 'REDUCTION_PERCENT',
        'reduction_value' => 30,
    ]);

    Http::fake(['*articlePrice*' => Http::response(['result' => [[
        'id'                 => 1099544,
        'articleId'          => 1099541,
        'price'              => '109',
        'reductionAdditions' => [],
    ]]], 200)]);

    $this->artisan('weclapp:sync article-prices')->assertSuccessful();

    expect(ArticlePrice::query()->firstOrFail())
        ->customer_id->toBeNull()
        ->end_date->toBeNull()
        ->reduction_type->toBeNull()
        ->reduction_value->toBeNull()
        ->price->toEqual('109.00000');
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

it('counts the supplySources collection, which decides whether the article is writable', function () {
    // Weclapp demands primarySupplySourceId once a supplySource exists and does not
    // return that field on a read, so a non-zero count means the record cannot be
    // written back at all. A consumer has to be able to see that without an API call.
    Http::fake(['*article*' => Http::response(['result' => [
        [
            'id'            => 20001,
            'supplySources' => [
                ['id' => 1, 'articleSupplySourceId' => 9001, 'positionNumber' => 1],
                ['id' => 2, 'articleSupplySourceId' => 9002, 'positionNumber' => 2],
            ],
        ],
        [
            'id'            => 20002,
            'supplySources' => [],
        ],
        [
            'id' => 20003,
        ],
    ]], 200)]);

    $this->artisan('weclapp:sync articles')->assertSuccessful();

    expect(Article::query()->where('weclapp_id', 20001)->firstOrFail()->supply_source_count)->toBe(2)
        ->and(Article::query()->where('weclapp_id', 20002)->firstOrFail()->supply_source_count)->toBe(0)
        ->and(Article::query()->where('weclapp_id', 20003)->firstOrFail()->supply_source_count)->toBe(0);
});

it('mirrors primarySupplySourceId, whose absence is what blocks a write', function () {
    // Weclapp hides the field from reads when the token lacks articleSupplySource
    // access, and demands it on write once a supplySource exists. A null here beside a
    // non-zero count is therefore the precise "cannot be written" signal.
    Http::fake(['*article*' => Http::response(['result' => [
        [
            'id'                    => 20001,
            'primarySupplySourceId' => 7496304,
            'supplySources'         => [['id' => 1, 'articleSupplySourceId' => 7496304, 'positionNumber' => 1]],
        ],
        [
            'id'            => 20002,
            'supplySources' => [['id' => 2, 'articleSupplySourceId' => 9002, 'positionNumber' => 1]],
        ],
    ]], 200)]);

    $this->artisan('weclapp:sync articles')->assertSuccessful();

    $writable = Article::query()->where('weclapp_id', 20001)->firstOrFail();
    $blocked = Article::query()->where('weclapp_id', 20002)->firstOrFail();

    expect($writable->primary_supply_source_id)->toBe(7496304)
        ->and($writable->supply_source_count)->toBe(1)
        // Weclapp withheld the field, so the mirror records that as null.
        ->and($blocked->primary_supply_source_id)->toBeNull()
        ->and($blocked->supply_source_count)->toBe(1);
});

it('treats every article as visible when no tag is configured', function () {
    config()->set('weclapp-api.visible_tag', null);

    Http::fake(['*article*' => Http::response(['result' => [
        ['id' => 20001, 'tags' => ['Cloud']],
        ['id' => 20003, 'tags' => []],
        ['id' => 20004],
    ]], 200)]);

    $this->artisan('weclapp:sync articles')->assertSuccessful();

    expect(Article::query()->where('visible', true)->count())->toBe(3);
});

it('flags the configured tag as visible, and keeps mirroring the articles without it', function () {
    // The tag decides whether a consumer may offer the article at all, so it has to be
    // answerable from the mirror. Untagged articles are still mirrored: filtering the
    // sync would archive rows other tables reference by weclapp_id.
    Http::fake(['*article*' => Http::response(['result' => [
        ['id' => 20001, 'tags' => ['Cloud']],
        ['id' => 20002, 'tags' => ['clo']],
        ['id' => 20003, 'tags' => []],
        ['id' => 20004],
    ]], 200)]);

    $this->artisan('weclapp:sync articles')->assertSuccessful();

    expect(Article::query()->where('weclapp_id', 20001)->firstOrFail()->visible)->toBeTrue()
        // A mistyped tag reads as absent rather than as a near-match, so it surfaces
        // as a missing article somebody notices.
        ->and(Article::query()->where('weclapp_id', 20002)->firstOrFail()->visible)->toBeFalse()
        ->and(Article::query()->where('weclapp_id', 20003)->firstOrFail()->visible)->toBeFalse()
        // Weclapp omits empty collections entirely; that is not the same as unknown.
        ->and(Article::query()->where('weclapp_id', 20004)->firstOrFail()->visible)->toBeFalse()
        ->and(Article::query()->count())->toBe(4);
});

it('flips visible off when the tag is removed, without losing the mirror row', function () {
    // One record page and the empty page that ends the id cursor, per sync.
    Http::fake(['*article*' => Http::sequence()
        ->push(['result' => [['id' => 20001, 'articleNumber' => 'A-1', 'tags' => ['Cloud']]]], 200)
        ->push(['result' => []], 200)
        ->push(['result' => [['id' => 20001, 'articleNumber' => 'A-1', 'tags' => []]]], 200)
        ->push(['result' => []], 200)]);

    $this->artisan('weclapp:sync articles')->assertSuccessful();

    $id = Article::query()->where('weclapp_id', 20001)->firstOrFail()->id;

    $this->artisan('weclapp:sync articles')->assertSuccessful();

    $article = Article::query()->withTrashed()->findOrFail($id);

    expect($article->visible)->toBeFalse()
        ->and($article->trashed())->toBeFalse();
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

it('archives parties Weclapp no longer returns', function () {
    Party::factory()->create(['weclapp_id' => 70001, 'supplier_number' => 'SU-1']);
    Http::fakeSequence()
        ->push(['result' => [['id' => 12345, 'customer' => true, 'customerNumber' => 'C10001']]], 200)
        ->push(['result' => []], 200);

    $this->artisan('weclapp:sync parties')
        ->expectsOutputToContain('Archived 1 parties no longer in Weclapp.')
        ->assertSuccessful();

    expect(Party::query()->count())->toBe(1)
        ->and(Party::withTrashed()->where('weclapp_id', 70001)->firstOrFail()->trashed())->toBeTrue();
});

it('archives nothing when a later page fails, since the read is then partial', function () {
    config()->set('weclapp-api.page_size', 1);
    config()->set('weclapp-api.http.retry_times', 1);
    Party::factory()->create(['weclapp_id' => 70001]);
    Http::fakeSequence()
        ->push(['result' => [['id' => 12345, 'customer' => true]]], 200)
        ->push(['error' => 'unavailable'], 503);

    $this->artisan('weclapp:sync parties')->assertFailed();

    // 70001 may well sit on the page that failed; archiving it would delete a
    // live record.
    expect(Party::query()->where('weclapp_id', 70001)->exists())->toBeTrue();
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

describe('without an entity argument', function () {
    it('skips entities whose table is not migrated and syncs the rest', function () {
        Schema::drop('weclapp_parties');
        Http::fake([
            '*article/count*' => Http::response(['result' => 0], 200),
            '*article*'       => Http::response(['result' => [['id' => 30001, 'articleNumber' => 'A-1']]], 200),
            '*'               => Http::response(['result' => []], 200),
        ]);

        $this->artisan('weclapp:sync')
            ->expectsOutputToContain('Skipped parties: weclapp_parties is not migrated.')
            ->expectsOutputToContain('Synced 1 articles.')
            ->assertSuccessful();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/party'));
        expect(Article::query()->where('weclapp_id', 30001)->exists())->toBeTrue();
    });

    it('reports an entity Weclapp refuses, keeps going, and exits non-zero', function () {
        Http::fake([
            '*quotation*'    => Http::response(['detail' => 'forbidden'], 403),
            '*articlePrice*' => Http::response(['result' => [['id' => 30002, 'articleId' => 1, 'price' => '1.00']]], 200),
            '*'              => Http::response(['result' => []], 200),
        ]);

        $this->artisan('weclapp:sync')
            ->expectsOutputToContain('quotations:')
            ->expectsOutputToContain('Synced 1 article-prices.')
            ->assertFailed();

        expect(ArticlePrice::query()->where('weclapp_id', 30002)->exists())->toBeTrue();
    });

    it('keeps going after an entity times out, and exits non-zero', function () {
        config()->set('weclapp-api.http.retry_times', 1);
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/quotation')) {
                throw new ConnectionException('timed out');
            }

            return str_contains($request->url(), '/articlePrice') && ! str_contains($request->url(), 'id-gt')
                ? Http::response(['result' => [['id' => 30004, 'articleId' => 1, 'price' => '1.00']]], 200)
                : Http::response(['result' => []], 200);
        });

        $this->artisan('weclapp:sync')
            ->expectsOutputToContain('quotations: timed out')
            ->expectsOutputToContain('Synced 1 article-prices.')
            ->assertFailed();

        expect(ArticlePrice::query()->where('weclapp_id', 30004)->exists())->toBeTrue();
    });

    it('applies the same guard to the incremental update', function () {
        Schema::drop('weclapp_parties');
        Http::fake(['*' => Http::response(['result' => []], 200)]);

        $this->artisan('weclapp:update')
            ->expectsOutputToContain('Skipped parties: weclapp_parties is not migrated.')
            ->assertSuccessful();
    });
});

it('does not skip a named entity whose table is missing', function () {
    Schema::drop('weclapp_parties');
    Http::fake(['*' => Http::response(['result' => [['id' => 30003, 'company' => 'ACME']]], 200)]);

    $this->artisan('weclapp:sync parties')
        ->expectsOutputToContain('parties:')
        ->assertFailed();
});

it('updates article categories incrementally, since they carry lastModifiedDate like every entity', function () {
    Http::fake(['*articleCategory*' => Http::response(['result' => []], 200)]);

    $this->artisan('weclapp:update article-categories --since=2026-01-01')
        ->expectsOutputToContain('Updated 0 article-categories.')
        ->assertSuccessful();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'lastModifiedDate-gt='));
});

it('maps lastModifiedDate onto article categories', function () {
    Http::fakeSequence()
        ->push(['result' => [['id' => 50001, 'name' => 'Pumps', 'lastModifiedDate' => 1791842399000]]], 200)
        ->push(['result' => []], 200);

    $this->artisan('weclapp:sync article-categories')->assertSuccessful();

    expect(ArticleCategory::query()->firstOrFail()->last_modified?->toIso8601ZuluString())->toBe('2026-10-12T21:59:59Z');
});

describe('mirrors every remaining entity', function () {
    it('maps a user', function () {
        Http::fakeSequence()
            ->push(['result' => [['id' => '301', 'email' => 'jane@example.com', 'firstName' => 'Jane', 'lastName' => 'Doe', 'lastModifiedDate' => 1791842399000]]], 200)
            ->push(['result' => []], 200);

        $this->artisan('weclapp:sync users')->assertSuccessful();

        $user = User::query()->firstOrFail();

        expect($user->weclapp_id)->toBe(301)
            ->and($user->email)->toBe('jane@example.com')
            ->and($user->last_name)->toBe('Doe')
            ->and($user->last_modified?->toIso8601ZuluString())->toBe('2026-10-12T21:59:59Z');
    });

    it('maps a sales order with its dates', function () {
        Http::fakeSequence()
            ->push(['result' => [[
                'id'                => '401',
                'customerId'        => '12345',
                'quotationId'       => '40001',
                'orderNumber'       => 'SO-10001',
                'status'            => 'ORDER_CONFIRMATION_PRINTED',
                'grossAmount'       => '119.00',
                'netAmount'         => '100.00',
                'orderDate'         => 1791842399000,
                'servicePeriodFrom' => 1791842399000,
                'version'           => '2',
            ]]], 200)
            ->push(['result' => []], 200);

        $this->artisan('weclapp:sync sales-orders')->assertSuccessful();

        $order = SalesOrder::query()->firstOrFail();

        expect($order->weclapp_id)->toBe(401)
            ->and($order->customer_id)->toBe(12345)
            ->and($order->quotation_id)->toBe(40001)
            ->and($order->order_number)->toBe('SO-10001')
            ->and($order->net_amount)->toEqual('100.00')
            ->and($order->order_date?->toIso8601ZuluString())->toBe('2026-10-12T21:59:59Z')
            ->and($order->service_period_from)->not->toBeNull()
            ->and($order->service_period_to)->toBeNull();
    });

    it('maps a project, taking title from name', function () {
        Http::fakeSequence()
            ->push(['result' => [['id' => '501', 'customerId' => '12345', 'projectNumber' => 'P-1', 'name' => 'Relaunch', 'status' => 'OPEN', 'projectStartDate' => 1791842399000]]], 200)
            ->push(['result' => []], 200);

        $this->artisan('weclapp:sync projects')->assertSuccessful();

        $project = Project::query()->firstOrFail();

        expect($project->weclapp_id)->toBe(501)
            ->and($project->title)->toBe('Relaunch')
            ->and($project->project_number)->toBe('P-1')
            ->and($project->project_start_date)->not->toBeNull();
    });

    it('maps a sales invoice, taking currency_id from recordCurrencyId', function () {
        Http::fakeSequence()
            ->push(['result' => [[
                'id'               => '601',
                'customerId'       => '12345',
                'salesOrderId'     => '401',
                'recordCurrencyId' => '248',
                'invoiceNumber'    => 'RE-10001',
                'salesInvoiceType' => 'STANDARD_INVOICE',
                'status'           => 'NEW',
                'paymentStatus'    => 'OPEN',
                'paid'             => false,
                'grossAmount'      => '119.00',
                'netAmount'        => '100.00',
                'invoiceDate'      => 1791842399000,
                'shippingDate'     => 1791842399000,
                'servicePeriodTo'  => 1791842399000,
            ]]], 200)
            ->push(['result' => []], 200);

        $this->artisan('weclapp:sync sales-invoices')->assertSuccessful();

        $invoice = SalesInvoice::query()->firstOrFail();

        expect($invoice->weclapp_id)->toBe(601)
            ->and($invoice->currency_id)->toBe(248)
            ->and($invoice->sales_order_id)->toBe(401)
            ->and($invoice->paid)->toBeFalse()
            ->and($invoice->sales_invoice_type)->toBe('STANDARD_INVOICE')
            ->and($invoice->invoice_date?->toIso8601ZuluString())->toBe('2026-10-12T21:59:59Z')
            ->and($invoice->shipping_date)->not->toBeNull()
            ->and($invoice->service_period_to)->not->toBeNull()
            ->and($invoice->pricing_date)->toBeNull();
    });
});
