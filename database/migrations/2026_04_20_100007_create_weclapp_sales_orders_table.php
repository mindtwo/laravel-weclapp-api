<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weclapp_sales_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('quotation_id')->nullable();
            $table->unsignedBigInteger('responsible_user_id')->nullable();
            $table->unsignedBigInteger('weclapp_id')->nullable()->index();
            $table->string('customer_number')->nullable();
            $table->decimal('gross_amount', 12, 2)->nullable();
            $table->datetime('last_modified')->nullable();
            $table->decimal('net_amount', 12, 2)->nullable();
            $table->datetime('order_date')->nullable();
            $table->string('order_number')->nullable();
            $table->datetime('pricing_date')->nullable();
            $table->string('quotation_number')->nullable();
            $table->text('record_free_text')->nullable();
            $table->datetime('service_period_from')->nullable();
            $table->datetime('service_period_to')->nullable();
            $table->string('status')->nullable();
            $table->integer('version')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weclapp_sales_orders');
    }
};
