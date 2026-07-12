<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Mindtwo\LaravelWeclappApi\Models\User;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'email'         => $this->faker->unique()->safeEmail(),
            'first_name'    => $this->faker->firstName(),
            'last_modified' => $this->faker->dateTime(),
            'last_name'     => $this->faker->lastName(),
            'weclapp_id'    => $this->faker->unique()->numberBetween(1000, 9999),
        ];
    }
}
