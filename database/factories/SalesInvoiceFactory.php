<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Mindtwo\LaravelWeclappApi\Models\SalesInvoice;

/**
 * @extends Factory<SalesInvoice>
 */
class SalesInvoiceFactory extends Factory
{
    protected $model = SalesInvoice::class;

    public function definition(): array
    {
        $net = $this->faker->randomFloat(2, 100, 100000);

        return [
            'creator_id'          => $this->faker->numberBetween(1000, 9999),
            'currency_id'         => $this->faker->numberBetween(1000, 9999),
            'customer_id'         => $this->faker->numberBetween(10000, 99999),
            'description'         => $this->faker->optional()->sentence(4),
            'gross_amount'        => round($net * 1.19, 2),
            'invoice_date'        => $this->faker->dateTime(),
            'invoice_number'      => (string) $this->faker->unique()->numberBetween(100000, 999999),
            'last_modified'       => $this->faker->dateTime(),
            'net_amount'          => $net,
            'paid'                => false,
            'payment_method_id'   => $this->faker->numberBetween(1000, 9999),
            'payment_status'      => 'OPEN',
            'pricing_date'        => $this->faker->dateTime(),
            'record_free_text'    => $this->faker->optional()->sentence(),
            'responsible_user_id' => $this->faker->numberBetween(1000, 9999),
            'sales_channel'       => 'NET1',
            'sales_invoice_type'  => 'STANDARD_INVOICE',
            'sales_order_id'      => $this->faker->numberBetween(10000, 99999),
            'service_period_from' => $this->faker->dateTime(),
            'service_period_to'   => $this->faker->dateTime(),
            'shipping_date'       => $this->faker->dateTime(),
            'status'              => 'NEW',
            'term_of_payment_id'  => $this->faker->numberBetween(1000, 9999),
            'version'             => $this->faker->numberBetween(1, 5),
            'weclapp_id'          => $this->faker->unique()->numberBetween(10000, 99999),
        ];
    }

    /**
     * A settled invoice.
     */
    public function paid(): static
    {
        return $this->state(fn (): array => [
            'paid'           => true,
            'payment_status' => 'PAID',
            'status'         => 'OPEN_ITEM_CREATED',
        ]);
    }
}
