<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Mindtwo\LaravelWeclappApi\Models\Article;
use Mindtwo\LaravelWeclappApi\Models\ArticleCategory;
use Mindtwo\LaravelWeclappApi\Models\ArticlePrice;
use Mindtwo\LaravelWeclappApi\Models\Party;
use Mindtwo\LaravelWeclappApi\Models\Project;
use Mindtwo\LaravelWeclappApi\Models\Quotation;
use Mindtwo\LaravelWeclappApi\Models\SalesInvoice;
use Mindtwo\LaravelWeclappApi\Models\SalesOrder;
use Mindtwo\LaravelWeclappApi\Models\User;

it('creates a persisted record via its factory', function (string $model) {
    $record = $model::factory()->create();

    expect($record->exists)->toBeTrue()
        ->and($record->getKey())->not->toBeNull()
        ->and($model::query()->count())->toBe(1);
})->with([
    Article::class,
    ArticleCategory::class,
    ArticlePrice::class,
    Party::class,
    Project::class,
    Quotation::class,
    SalesInvoice::class,
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

it('resolves the sales order to quotation relationship by weclapp id', function () {
    $quotation = Quotation::factory()->create(['weclapp_id' => 777]);
    $order = SalesOrder::factory()->create(['quotation_id' => 777]);

    expect($order->quotation->is($quotation))->toBeTrue();
});

it('leaves article prices without a customer by default', function () {
    // Weclapp omits customerId entirely on list prices, so the mirror column
    // stays null unless a customer-specific price is synced.
    $price = ArticlePrice::factory()->create();

    expect($price->customer_id)->toBeNull()
        ->and($price->article_id)->toBeInt()
        ->and($price->price)->not->toBeNull();
});

it('supports the customer-specific article price state', function () {
    $price = ArticlePrice::factory()->forCustomer(12345)->create();

    expect($price->customer_id)->toBe(12345);
});

it('supports the time-limited article price state', function () {
    $price = ArticlePrice::factory()->timeLimited()->create();

    expect($price->start_date)->toBeInstanceOf(Carbon::class)
        ->and($price->end_date)->toBeInstanceOf(Carbon::class)
        ->and($price->start_date->lessThan($price->end_date))->toBeTrue();
});

it('supports the paid sales invoice state', function () {
    $invoice = SalesInvoice::factory()->paid()->create();

    expect($invoice->paid)->toBeTrue()
        ->and($invoice->payment_status)->toBe('PAID');
});
