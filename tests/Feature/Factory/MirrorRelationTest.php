<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Mindtwo\LaravelWeclappApi\Models\ArticlePrice;
use Mindtwo\LaravelWeclappApi\Models\Party;
use Mindtwo\LaravelWeclappApi\Models\Project;
use Mindtwo\LaravelWeclappApi\Models\Quotation;
use Mindtwo\LaravelWeclappApi\Models\SalesInvoice;
use Mindtwo\LaravelWeclappApi\Models\SalesOrder;

// Every mirror that carries a customer_id resolves it to the party mirror, so
// consumers do not join by hand on some mirrors and use a relation on others.
it('resolves its customer to the party mirror', function (string $model) {
    $party = Party::factory()->create(['weclapp_id' => 515151]);
    $record = $model::factory()->create(['customer_id' => 515151]);

    expect($record->customer?->is($party))->toBeTrue();
})->with([
    ArticlePrice::class,
    Project::class,
    Quotation::class,
    SalesInvoice::class,
    SalesOrder::class,
]);

// Each column a relation joins on is indexed, so loading the relation for a
// page of rows is an index lookup rather than a table scan.
it('indexes the columns its relations join on', function (string $table, string $column) {
    $indexed = collect(Schema::getIndexes($table))
        ->contains(fn (array $index): bool => $index['columns'] === [$column]);

    expect($indexed)->toBeTrue("{$table}.{$column} has no index");
})->with([
    ['weclapp_article_prices', 'article_id'],
    ['weclapp_article_prices', 'customer_id'],
    ['weclapp_articles', 'article_category_id'],
    ['weclapp_projects', 'customer_id'],
    ['weclapp_quotations', 'customer_id'],
    ['weclapp_sales_invoices', 'customer_id'],
    ['weclapp_sales_invoices', 'sales_order_id'],
    ['weclapp_sales_orders', 'customer_id'],
    ['weclapp_sales_orders', 'quotation_id'],
]);
