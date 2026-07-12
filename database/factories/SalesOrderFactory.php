<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Mindtwo\LaravelWeclappApi\Models\SalesOrder;

/**
 * @extends Factory<SalesOrder>
 */
class SalesOrderFactory extends Factory
{
    protected $model = SalesOrder::class;

    public function definition(): array
    {
        return [
            'customer_id'         => $this->faker->numberBetween(10000, 99999),
            'customer_number'     => 'C'.$this->faker->numberBetween(10000, 99999),
            'gross_amount'        => $this->faker->randomFloat(2, 100, 100000),
            'last_modified'       => $this->faker->dateTime(),
            'net_amount'          => $this->faker->randomFloat(2, 100, 100000),
            'order_date'          => $this->faker->dateTime(),
            'order_number'        => (string) $this->faker->unique()->numberBetween(100000, 999999),
            'pricing_date'        => $this->faker->dateTime(),
            'quotation_id'        => $this->faker->numberBetween(10000, 99999),
            'quotation_number'    => 'QU-'.$this->faker->numberBetween(10000, 99999),
            'record_free_text'    => $this->faker->sentence(),
            'responsible_user_id' => $this->faker->numberBetween(1000, 9999),
            'service_period_from' => $this->faker->dateTime(),
            'service_period_to'   => $this->faker->dateTime(),
            'status'              => $this->faker->randomElement(['ORDER_ENTRY_IN_PROGRESS', 'ORDER_CONFIRMATION_PRINTED']),
            'version'             => $this->faker->numberBetween(1, 5),
            'weclapp_id'          => $this->faker->unique()->numberBetween(10000, 99999),
        ];
    }
}
