<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Mindtwo\LaravelWeclappApi\Database\Factories\ArticleFactory;

/**
 * @property int $id
 * @property int|null $article_category_id
 * @property int|null $main_image_id
 * @property int|null $primary_supply_source_id
 * @property int $supply_source_count
 * @property int|null $unit_id
 * @property int|null $weclapp_id
 * @property bool $active
 * @property string|null $article_number
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $last_modified
 * @property string|null $long_text
 * @property string|null $main_image_filename
 * @property string|null $name
 * @property string|null $short_description_1
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'weclapp_articles';

    protected $fillable = [
        'active',
        'article_category_id',
        'article_number',
        'description',
        'last_modified',
        'long_text',
        'main_image_filename',
        'main_image_id',
        'name',
        'primary_supply_source_id',
        'short_description_1',
        'supply_source_count',
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
            'active'                   => 'boolean',
            'article_category_id'      => 'integer',
            'last_modified'            => 'datetime',
            'main_image_id'            => 'integer',
            'primary_supply_source_id' => 'integer',
            'supply_source_count'      => 'integer',
            'unit_id'                  => 'integer',
            'weclapp_id'               => 'integer',
        ];
    }
}
