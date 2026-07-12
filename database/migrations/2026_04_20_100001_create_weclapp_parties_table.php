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
            $table->unsignedBigInteger('weclapp_id')->nullable()->index();
            $table->string('company')->nullable();
            $table->string('company_2')->nullable();
            $table->string('customer_number')->nullable();
            $table->text('description')->nullable();
            $table->string('email')->nullable();
            $table->string('first_name')->nullable();
            $table->datetime('last_modified')->nullable();
            $table->string('last_name')->nullable();
            $table->string('party_type')->nullable();
            $table->string('phone')->nullable();
            $table->string('salutation')->nullable();
            $table->string('supplier_number')->nullable();
            $table->string('website')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weclapp_parties');
    }
};
