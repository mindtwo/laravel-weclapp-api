<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weclapp_sales_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedBigInteger('payment_method_id')->nullable();
            $table->unsignedBigInteger('responsible_user_id')->nullable();
            $table->unsignedBigInteger('sales_order_id')->nullable()->index();
            $table->unsignedBigInteger('term_of_payment_id')->nullable();
            $table->unsignedBigInteger('weclapp_id')->nullable()->index();
            $table->string('description')->nullable();
            $table->decimal('gross_amount', 12, 2)->nullable();
            $table->datetime('invoice_date')->nullable();
            $table->string('invoice_number')->nullable();
            $table->datetime('last_modified')->nullable();
            $table->decimal('net_amount', 12, 2)->nullable();
            $table->boolean('paid')->nullable();
            $table->string('payment_status')->nullable();
            $table->datetime('pricing_date')->nullable();
            $table->text('record_free_text')->nullable();
            $table->string('sales_channel')->nullable();
            $table->string('sales_invoice_type')->nullable();
            $table->datetime('service_period_from')->nullable();
            $table->datetime('service_period_to')->nullable();
            $table->datetime('shipping_date')->nullable();
            $table->string('status')->nullable();
            $table->integer('version')->nullable();
            $table->timestamps();
            // Reconciliation marks rows Weclapp no longer returns; see EntitySynchronizer.
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weclapp_sales_invoices');
    }
};
