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
            // No image and no short description by default, because that is what
            // almost every real article looks like: a live full read found an image
            // on 3 of 748 and a shortDescription1 on 40. Use withMainImage() when a
            // test needs the rarer shape.
            'long_text'           => null,
            'main_image_filename' => null,
            'main_image_id'       => null,
            'name'                => $this->faker->words(3, true),
            'short_description_1' => null,
            'unit_id'             => $this->faker->numberBetween(1, 10),
            'weclapp_id'          => $this->faker->unique()->numberBetween(10000, 99999),
        ];
    }

    public function withMainImage(): static
    {
        return $this->state(fn (): array => [
            'main_image_filename' => $this->faker->word().'.jpg',
            'main_image_id'       => $this->faker->unique()->numberBetween(10000, 99999),
        ]);
    }
}
