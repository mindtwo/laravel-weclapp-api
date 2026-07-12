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
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->unsignedBigInteger('weclapp_id')->nullable()->index();
            $table->boolean('active')->default(true);
            $table->string('article_number')->nullable();
            $table->text('description')->nullable();
            $table->datetime('last_modified')->nullable();
            $table->string('name')->nullable();
            $table->string('unit_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weclapp_articles');
    }
};
