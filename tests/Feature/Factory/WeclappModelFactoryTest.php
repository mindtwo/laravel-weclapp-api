<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Mindtwo\LaravelWeclappApi\Models\Amount;
use Mindtwo\LaravelWeclappApi\Models\Article;
use Mindtwo\LaravelWeclappApi\Models\ArticleCategory;
use Mindtwo\LaravelWeclappApi\Models\Party;
use Mindtwo\LaravelWeclappApi\Models\Project;
use Mindtwo\LaravelWeclappApi\Models\Quotation;
use Mindtwo\LaravelWeclappApi\Models\Report;
use Mindtwo\LaravelWeclappApi\Models\SalesOrder;
use Mindtwo\LaravelWeclappApi\Models\User;

it('creates a persisted record via its factory', function (string $model) {
    $record = $model::factory()->create();

    expect($record->exists)->toBeTrue()
        ->and($record->getKey())->not->toBeNull()
        ->and($model::query()->count())->toBe(1);
})->with([
    Amount::class,
    Article::class,
    ArticleCategory::class,
    Party::class,
    Project::class,
    Quotation::class,
    Report::class,
    SalesOrder::class,
    User::class,
]);

it('casts party identifiers and dates', function () {
    $party = Party::factory()->create();

    expect($party->weclapp_id)->toBeInt()
        ->and($party->last_modified)->toBeInstanceOf(Carbon::class);
});

it('supports the supplier factory state', function () {
    $supplier = Party::factory()->supplier()->create();

    expect($supplier->party_type)->toBe('ORGANIZATION')
        ->and($supplier->customer_number)->toBeNull()
        ->and($supplier->supplier_number)->not->toBeNull();
});

it('resolves the article to category relationship by weclapp id', function () {
    $category = ArticleCategory::factory()->create(['weclapp_id' => 555]);
    $article = Article::factory()->create(['article_category_id' => 555]);

    expect($article->category->is($category))->toBeTrue();
});

it('resolves quotation, report and sales order relationships by weclapp id', function () {
    $quotation = Quotation::factory()->create(['weclapp_id' => 777]);
    $report = Report::factory()->create(['quotation_id' => 777]);
    $order = SalesOrder::factory()->create(['quotation_id' => 777]);

    expect($report->quotation->is($quotation))->toBeTrue()
        ->and($order->quotation->is($quotation))->toBeTrue()
        ->and($quotation->report->is($report))->toBeTrue();
});
