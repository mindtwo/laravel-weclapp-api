<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Mindtwo\LaravelWeclappApi\Database\Factories\ArticlePriceFactory;

/**
 * @property int $id
 * @property int|null $article_id
 * @property int|null $currency_id
 * @property int|null $customer_id
 * @property int|null $last_modified_by_user_id
 * @property int|null $weclapp_id
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property \Illuminate\Support\Carbon|null $last_modified
 * @property string|null $price
 * @property string|null $price_scale_type
 * @property string|null $price_scale_value
 * @property string|null $reduction_type
 * @property string|null $reduction_value
 * @property string|null $sales_channel
 * @property \Illuminate\Support\Carbon|null $start_date
 * @property int|null $version
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ArticlePrice extends Model
{
    /** @use HasFactory<ArticlePriceFactory> */
    use HasFactory;

    protected $table = 'weclapp_article_prices';

    protected $fillable = [
        'article_id',
        'currency_id',
        'customer_id',
        'description',
        'end_date',
        'last_modified',
        'last_modified_by_user_id',
        'price',
        'price_scale_type',
        'price_scale_value',
        'reduction_type',
        'reduction_value',
        'sales_channel',
        'start_date',
        'version',
        'weclapp_id',
    ];

    protected static function newFactory(): ArticlePriceFactory
    {
        return ArticlePriceFactory::new();
    }

    /**
     * @return BelongsTo<Article, $this>
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'article_id', 'weclapp_id');
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'customer_id', 'weclapp_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'article_id'               => 'integer',
            'currency_id'              => 'integer',
            'customer_id'              => 'integer',
            'end_date'                 => 'datetime',
            'last_modified'            => 'datetime',
            'last_modified_by_user_id' => 'integer',
            'price'                    => 'decimal:4',
            'price_scale_value'        => 'decimal:4',
            'reduction_value'          => 'decimal:4',
            'start_date'               => 'datetime',
            'version'                  => 'integer',
            'weclapp_id'               => 'integer',
        ];
    }
}
