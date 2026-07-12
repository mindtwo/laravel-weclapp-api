<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weclapp_projects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('weclapp_id')->nullable()->index();
            $table->string('customer_number')->nullable();
            $table->text('description')->nullable();
            $table->datetime('last_modified')->nullable();
            $table->string('project_number')->nullable();
            $table->datetime('project_start_date')->nullable();
            $table->string('status')->nullable();
            $table->string('title')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weclapp_projects');
    }
};
