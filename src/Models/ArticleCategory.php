<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Mindtwo\LaravelWeclappApi\Database\Factories\ArticleCategoryFactory;

/**
 * @property int $id
 * @property int|null $weclapp_id
 * @property string|null $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ArticleCategory extends Model
{
    /** @use HasFactory<ArticleCategoryFactory> */
    use HasFactory;

    protected $table = 'weclapp_article_categories';

    protected $fillable = [
        'name',
        'weclapp_id',
    ];

    protected static function newFactory(): ArticleCategoryFactory
    {
        return ArticleCategoryFactory::new();
    }

    /**
     * @return HasMany<Article, $this>
     */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'article_category_id', 'weclapp_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weclapp_id' => 'integer',
        ];
    }
}
