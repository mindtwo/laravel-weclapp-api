<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weclapp_quotations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedBigInteger('weclapp_id')->unique();
            // Amounts take the spec's decimal shape: up to 13 integer and 5
            // fractional digits.
            $table->decimal('gross_amount', 18, 5)->nullable();
            $table->datetime('last_modified')->nullable();
            $table->decimal('net_amount', 18, 5)->nullable();
            $table->string('quotation_number')->nullable();
            $table->string('status')->nullable();
            $table->integer('quotation_version')->nullable();
            $table->integer('version')->nullable();
            $table->timestamps();
            // Reconciliation marks rows Weclapp no longer returns; see EntitySynchronizer.
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weclapp_quotations');
    }
};
