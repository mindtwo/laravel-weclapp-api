<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weclapp_parties', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('responsible_user_id')->nullable();
            $table->unsignedBigInteger('sector_id')->nullable();
            $table->unsignedBigInteger('weclapp_id')->unique();
            $table->text('company')->nullable();
            $table->text('company_2')->nullable();
            $table->text('customer_number')->nullable();
            $table->text('description')->nullable();
            $table->text('email')->nullable();
            $table->text('first_name')->nullable();
            $table->boolean('is_customer')->default(false)->index();
            $table->boolean('is_supplier')->default(false)->index();
            $table->datetime('last_modified')->nullable();
            $table->text('last_name')->nullable();
            $table->string('party_type')->nullable();
            $table->text('phone')->nullable();
            $table->string('salutation')->nullable();
            $table->text('supplier_number')->nullable();
            $table->text('website')->nullable();
            $table->timestamps();
            // Reconciliation marks rows Weclapp no longer returns; see EntitySynchronizer.
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weclapp_parties');
    }
};
