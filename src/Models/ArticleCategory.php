<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Mindtwo\LaravelWeclappApi\Database\Factories\ArticleCategoryFactory;

/**
 * @property int $id
 * @property int $weclapp_id
 * @property \Illuminate\Support\Carbon|null $last_modified
 * @property string|null $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class ArticleCategory extends Model
{
    /** @use HasFactory<ArticleCategoryFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'weclapp_article_categories';

    protected $fillable = [
        'last_modified',
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
            'last_modified' => 'datetime',
            'weclapp_id'    => 'integer',
        ];
    }
}
