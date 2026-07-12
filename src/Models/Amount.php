<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Mindtwo\LaravelWeclappApi\Database\Factories\AmountFactory;

/**
 * A per-year revenue amount derived from a sales order, grouped by category.
 *
 * @property int $id
 * @property int|null $category_id
 * @property int|null $customer_id
 * @property int|null $order_id
 * @property string|null $customer_number
 * @property string|null $monthly_amount
 * @property string|null $net_amount
 * @property string|null $net_amount_sidecost
 * @property int|null $year
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Amount extends Model
{
    /** @use HasFactory<AmountFactory> */
    use HasFactory;

    protected $table = 'weclapp_amounts';

    protected $fillable = [
        'category_id',
        'customer_id',
        'customer_number',
        'monthly_amount',
        'net_amount',
        'net_amount_sidecost',
        'order_id',
        'year',
    ];

    protected static function newFactory(): AmountFactory
    {
        return AmountFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category_id'         => 'integer',
            'customer_id'         => 'integer',
            'monthly_amount'      => 'decimal:2',
            'net_amount'          => 'decimal:2',
            'net_amount_sidecost' => 'decimal:2',
            'order_id'            => 'integer',
            'year'                => 'integer',
        ];
    }
}
