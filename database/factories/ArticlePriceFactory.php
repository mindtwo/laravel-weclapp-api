<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Mindtwo\LaravelWeclappApi\Models\ArticlePrice;

/**
 * @extends Factory<ArticlePrice>
 */
class ArticlePriceFactory extends Factory
{
    protected $model = ArticlePrice::class;

    public function definition(): array
    {
        return [
            'article_id'               => $this->faker->numberBetween(10000, 99999),
            'currency_id'              => $this->faker->numberBetween(1000, 9999),
            'customer_id'              => null,
            'description'              => $this->faker->optional()->sentence(3),
            'end_date'                 => null,
            'last_modified'            => $this->faker->dateTime(),
            'last_modified_by_user_id' => $this->faker->numberBetween(1000, 9999),
            'price'                    => $this->faker->randomFloat(4, 1, 5000),
            'price_scale_type'         => 'SCALE_FROM',
            'price_scale_value'        => $this->faker->randomFloat(4, 1, 1000),
            'reduction_type'           => null,
            'reduction_value'          => null,
            'sales_channel'            => 'NET1',
            'start_date'               => null,
            'version'                  => $this->faker->numberBetween(1, 5),
            'weclapp_id'               => $this->faker->unique()->numberBetween(10000, 99999),
        ];
    }

    /**
     * A price negotiated for one customer rather than a list price.
     */
    public function forCustomer(?int $customerId = null): static
    {
        return $this->state(fn (): array => [
            'customer_id' => $customerId ?? $this->faker->numberBetween(10000, 99999),
        ]);
    }

    /**
     * A price carrying the percentage reduction Weclapp keeps in
     * reductionAdditions — the shape 96% of customer-specific prices have live.
     */
    public function reduced(?float $percent = null): static
    {
        return $this->state(fn (): array => [
            'reduction_type'  => 'REDUCTION_PERCENT',
            'reduction_value' => $percent ?? $this->faker->randomElement([6.5, 7.5, 12, 15, 25, 30]),
        ]);
    }

    /**
     * A price that is only valid inside a date window.
     */
    public function timeLimited(): static
    {
        return $this->state(fn (): array => [
            'start_date' => $this->faker->dateTimeBetween('-1 year', '-1 month'),
            'end_date'   => $this->faker->dateTimeBetween('+1 month', '+1 year'),
        ]);
    }
}
