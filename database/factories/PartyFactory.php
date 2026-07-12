<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Mindtwo\LaravelWeclappApi\Models\Party;

/**
 * @extends Factory<Party>
 */
class PartyFactory extends Factory
{
    protected $model = Party::class;

    public function definition(): array
    {
        return [
            'company'             => $this->faker->company(),
            'company_2'           => null,
            'customer_number'     => 'C'.$this->faker->unique()->numberBetween(10000, 99999),
            'description'         => $this->faker->sentence(),
            'email'               => $this->faker->safeEmail(),
            'first_name'          => $this->faker->firstName(),
            'last_modified'       => $this->faker->dateTime(),
            'last_name'           => $this->faker->lastName(),
            'party_type'          => $this->faker->randomElement(['ORGANIZATION', 'PERSON']),
            'phone'               => $this->faker->phoneNumber(),
            'responsible_user_id' => $this->faker->numberBetween(1000, 9999),
            'salutation'          => $this->faker->randomElement(['MR', 'MRS']),
            'sector_id'           => $this->faker->numberBetween(1000, 9999),
            'supplier_number'     => null,
            'website'             => $this->faker->url(),
            'weclapp_id'          => $this->faker->unique()->numberBetween(10000, 99999),
        ];
    }

    public function supplier(): static
    {
        return $this->state(fn (): array => [
            'party_type'      => 'ORGANIZATION',
            'customer_number' => null,
            'supplier_number' => 'SU-'.$this->faker->unique()->numberBetween(10000, 99999),
        ]);
    }
}
