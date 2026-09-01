<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weclapp_articles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('article_category_id')->nullable();
            // Flattened from the nested articleImages collection: the entry flagged
            // mainImage. Present so a consumer can ask whether Weclapp holds an image
            // for many articles at once without an API call per article.
            $table->unsignedBigInteger('main_image_id')->nullable();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->unsignedBigInteger('weclapp_id')->nullable()->index();
            $table->boolean('active')->default(true);
            $table->string('article_number')->nullable();
            $table->text('description')->nullable();
            $table->datetime('last_modified')->nullable();
            $table->text('long_text')->nullable();
            $table->string('main_image_filename', 1000)->nullable();
            $table->string('name', 300)->nullable();
            $table->text('short_description_1')->nullable();
            // Count of the nested supplySources collection. Flattened for the same
            // reason as the main image: a consumer listing many articles has to know
            // this without an API call each. It decides whether the article can be
            // written at all — Weclapp requires primarySupplySourceId once a
            // supplySource exists, and does not return that field on a read.
            $table->unsignedInteger('supply_source_count')->default(0);
            $table->timestamps();
            // Reconciliation marks rows Weclapp no longer returns; see EntitySynchronizer.
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weclapp_articles');
    }
};
