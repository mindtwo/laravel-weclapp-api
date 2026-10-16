<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Mindtwo\LaravelWeclappApi\Models\Article;
use Mindtwo\LaravelWeclappApi\Models\ArticleCategory;
use Mindtwo\LaravelWeclappApi\Models\ArticlePrice;
use Mindtwo\LaravelWeclappApi\Models\Party;
use Mindtwo\LaravelWeclappApi\Models\Project;
use Mindtwo\LaravelWeclappApi\Models\Quotation;
use Mindtwo\LaravelWeclappApi\Models\SalesInvoice;
use Mindtwo\LaravelWeclappApi\Models\SalesOrder;
use Mindtwo\LaravelWeclappApi\Models\User;

// The sync matches rows on weclapp_id and inserts when it finds none, so two
// overlapping runs could both insert the same record. The unique index turns
// that into a failed insert rather than a duplicate the sync never cleans up.
it('rejects a second mirror row for the same weclapp id', function (string $model) {
    $model::factory()->create(['weclapp_id' => 424242]);

    expect(fn () => $model::factory()->create(['weclapp_id' => 424242]))->toThrow(QueryException::class);
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
