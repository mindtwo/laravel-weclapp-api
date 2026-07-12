<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Mindtwo\LaravelWeclappApi\Models\Project;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'customer_id'        => $this->faker->numberBetween(10000, 99999),
            'customer_number'    => 'C'.$this->faker->numberBetween(10000, 99999),
            'description'        => $this->faker->sentence(),
            'last_modified'      => $this->faker->dateTime(),
            'project_number'     => 'PJ'.$this->faker->unique()->numberBetween(1000, 9999),
            'project_start_date' => $this->faker->dateTime(),
            'status'             => $this->faker->randomElement(['PLANNED', 'ACTIVE', 'CLOSED']),
            'title'              => $this->faker->sentence(3),
            'weclapp_id'         => $this->faker->unique()->numberBetween(10000, 99999),
        ];
    }
}
