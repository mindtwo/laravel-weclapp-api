<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weclapp_article_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('weclapp_id')->nullable()->index();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weclapp_article_categories');
    }
};
