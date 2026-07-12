<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Mindtwo\LaravelWeclappApi\Models\Quotation;

/**
 * @extends Factory<Quotation>
 */
class QuotationFactory extends Factory
{
    protected $model = Quotation::class;

    public function definition(): array
    {
        return [
            'customer_id'      => $this->faker->numberBetween(10000, 99999),
            'customer_number'  => 'C'.$this->faker->numberBetween(10000, 99999),
            'gross_amount'     => $this->faker->randomFloat(2, 100, 100000),
            'last_modified'    => $this->faker->dateTime(),
            'net_amount'       => $this->faker->randomFloat(2, 100, 100000),
            'quotation_number' => 'QU-'.$this->faker->unique()->numberBetween(10000, 99999),
            'report_id'        => null,
            'status'           => $this->faker->randomElement(['OPEN', 'ACCEPTED', 'REJECTED']),
            'status_id'        => $this->faker->numberBetween(1, 3),
            'version'          => $this->faker->numberBetween(1, 5),
            'weclapp_id'       => $this->faker->unique()->numberBetween(10000, 99999),
        ];
    }
}
