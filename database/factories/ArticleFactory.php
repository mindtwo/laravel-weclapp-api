<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Mindtwo\LaravelWeclappApi\Models\Article;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    protected $model = Article::class;

    public function definition(): array
    {
        return [
            'active'              => true,
            'article_category_id' => $this->faker->numberBetween(10000, 99999),
            'article_number'      => 'ART-'.$this->faker->unique()->numberBetween(100, 999),
            'description'         => $this->faker->sentence(),
            'last_modified'       => $this->faker->dateTime(),
            'name'                => $this->faker->words(3, true),
            'unit_id'             => $this->faker->numberBetween(1, 10),
            'weclapp_id'          => $this->faker->unique()->numberBetween(10000, 99999),
        ];
    }
}
