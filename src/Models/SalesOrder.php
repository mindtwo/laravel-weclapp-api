<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Mindtwo\LaravelWeclappApi\Database\Factories\SalesOrderFactory;

/**
 * @property int $id
 * @property int|null $customer_id
 * @property int|null $quotation_id
 * @property int|null $responsible_user_id
 * @property int|null $weclapp_id
 * @property string|null $gross_amount
 * @property \Illuminate\Support\Carbon|null $last_modified
 * @property string|null $net_amount
 * @property \Illuminate\Support\Carbon|null $order_date
 * @property string|null $order_number
 * @property \Illuminate\Support\Carbon|null $pricing_date
 * @property string|null $record_free_text
 * @property \Illuminate\Support\Carbon|null $service_period_from
 * @property \Illuminate\Support\Carbon|null $service_period_to
 * @property string|null $status
 * @property int|null $version
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class SalesOrder extends Model
{
    /** @use HasFactory<SalesOrderFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'weclapp_sales_orders';

    protected $fillable = [
        'customer_id',
        'gross_amount',
        'last_modified',
        'net_amount',
        'order_date',
        'order_number',
        'pricing_date',
        'quotation_id',
        'record_free_text',
        'responsible_user_id',
        'service_period_from',
        'service_period_to',
        'status',
        'version',
        'weclapp_id',
    ];

    protected static function newFactory(): SalesOrderFactory
    {
        return SalesOrderFactory::new();
    }

    /**
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'quotation_id', 'weclapp_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_id'         => 'integer',
            'gross_amount'        => 'decimal:2',
            'last_modified'       => 'datetime',
            'net_amount'          => 'decimal:2',
            'order_date'          => 'datetime',
            'pricing_date'        => 'datetime',
            'quotation_id'        => 'integer',
            'responsible_user_id' => 'integer',
            'service_period_from' => 'datetime',
            'service_period_to'   => 'datetime',
            'version'             => 'integer',
            'weclapp_id'          => 'integer',
        ];
    }
}
