<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weclapp_article_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('article_id')->nullable()->index();
            // Set only on customer-specific prices; absent from the API response
            // entirely for list prices.
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->unsignedBigInteger('last_modified_by_user_id')->nullable();
            $table->unsignedBigInteger('weclapp_id')->unique();
            $table->string('description')->nullable();
            $table->datetime('end_date')->nullable();
            $table->datetime('last_modified')->nullable();
            // Amounts take the spec's decimal shape: up to 13 integer and 5
            // fractional digits.
            $table->decimal('price', 18, 5)->nullable();
            $table->string('price_scale_type')->nullable();
            $table->decimal('price_scale_value', 18, 5)->nullable();
            // Flattened from the nested reductionAdditions collection. The type is
            // one of REDUCTION_PERCENT / ADDITION_PERCENT (a percentage of `price`)
            // or REDUCTION_ABS / ADDITIONAL_ABS (an amount in the price's currency);
            // a consumer applying the value has to branch on it.
            $table->string('reduction_type')->nullable();
            $table->decimal('reduction_value', 18, 5)->nullable();
            $table->string('sales_channel')->nullable();
            $table->datetime('start_date')->nullable();
            $table->integer('version')->nullable();
            $table->timestamps();
            // Reconciliation marks rows Weclapp no longer returns; see EntitySynchronizer.
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weclapp_article_prices');
    }
};
