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
            $table->unsignedBigInteger('weclapp_id')->nullable()->index();
            $table->string('description')->nullable();
            $table->datetime('end_date')->nullable();
            $table->datetime('last_modified')->nullable();
            $table->decimal('price', 12, 4)->nullable();
            $table->string('price_scale_type')->nullable();
            $table->decimal('price_scale_value', 12, 4)->nullable();
            $table->string('sales_channel')->nullable();
            $table->datetime('start_date')->nullable();
            $table->integer('version')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weclapp_article_prices');
    }
};
