<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Mindtwo\LaravelWeclappApi\Models\ArticleCategory;

/**
 * @extends Factory<ArticleCategory>
 */
class ArticleCategoryFactory extends Factory
{
    protected $model = ArticleCategory::class;

    public function definition(): array
    {
        return [
            'name'       => $this->faker->words(2, true),
            'weclapp_id' => $this->faker->unique()->numberBetween(10000, 99999),
        ];
    }
}
