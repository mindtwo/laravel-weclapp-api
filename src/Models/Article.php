<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Mindtwo\LaravelWeclappApi\Database\Factories\ArticleFactory;

/**
 * @property int $id
 * @property int|null $article_category_id
 * @property int|null $unit_id
 * @property int|null $weclapp_id
 * @property bool $active
 * @property string|null $article_number
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $last_modified
 * @property string|null $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory;

    protected $table = 'weclapp_articles';

    protected $fillable = [
        'active',
        'article_category_id',
        'article_number',
        'description',
        'last_modified',
        'name',
        'unit_id',
        'weclapp_id',
    ];

    protected static function newFactory(): ArticleFactory
    {
        return ArticleFactory::new();
    }

    /**
     * @return BelongsTo<ArticleCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ArticleCategory::class, 'article_category_id', 'weclapp_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active'              => 'boolean',
            'article_category_id' => 'integer',
            'last_modified'       => 'datetime',
            'unit_id'             => 'integer',
            'weclapp_id'          => 'integer',
        ];
    }
}
