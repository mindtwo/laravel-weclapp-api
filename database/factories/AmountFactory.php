<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Mindtwo\LaravelWeclappApi\Models\Amount;

/**
 * @extends Factory<Amount>
 */
class AmountFactory extends Factory
{
    protected $model = Amount::class;

    public function definition(): array
    {
        return [
            'category_id'         => $this->faker->numberBetween(1, 10),
            'customer_id'         => $this->faker->numberBetween(10000, 99999),
            'customer_number'     => 'C'.$this->faker->numberBetween(10000, 99999),
            'monthly_amount'      => $this->faker->randomFloat(2, 10, 5000),
            'net_amount'          => $this->faker->randomFloat(2, 100, 60000),
            'net_amount_sidecost' => $this->faker->randomFloat(2, 0, 5000),
            'order_id'            => $this->faker->numberBetween(10000, 99999),
            'year'                => $this->faker->numberBetween(2020, 2030),
        ];
    }
}
