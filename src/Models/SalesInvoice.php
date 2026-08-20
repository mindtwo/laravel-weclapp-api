<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Mindtwo\LaravelWeclappApi\Database\Factories\SalesInvoiceFactory;

/**
 * @property int $id
 * @property int|null $creator_id
 * @property int|null $currency_id
 * @property int|null $customer_id
 * @property int|null $payment_method_id
 * @property int|null $responsible_user_id
 * @property int|null $sales_order_id
 * @property int|null $term_of_payment_id
 * @property int|null $weclapp_id
 * @property string|null $description
 * @property string|null $gross_amount
 * @property \Illuminate\Support\Carbon|null $invoice_date
 * @property string|null $invoice_number
 * @property \Illuminate\Support\Carbon|null $last_modified
 * @property string|null $net_amount
 * @property bool|null $paid
 * @property string|null $payment_status
 * @property \Illuminate\Support\Carbon|null $pricing_date
 * @property string|null $record_free_text
 * @property string|null $sales_channel
 * @property string|null $sales_invoice_type
 * @property \Illuminate\Support\Carbon|null $service_period_from
 * @property \Illuminate\Support\Carbon|null $service_period_to
 * @property \Illuminate\Support\Carbon|null $shipping_date
 * @property string|null $status
 * @property int|null $version
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class SalesInvoice extends Model
{
    /** @use HasFactory<SalesInvoiceFactory> */
    use HasFactory;

    protected $table = 'weclapp_sales_invoices';

    protected $fillable = [
        'creator_id',
        'currency_id',
        'customer_id',
        'description',
        'gross_amount',
        'invoice_date',
        'invoice_number',
        'last_modified',
        'net_amount',
        'paid',
        'payment_method_id',
        'payment_status',
        'pricing_date',
        'record_free_text',
        'responsible_user_id',
        'sales_channel',
        'sales_invoice_type',
        'sales_order_id',
        'service_period_from',
        'service_period_to',
        'shipping_date',
        'status',
        'term_of_payment_id',
        'version',
        'weclapp_id',
    ];

    protected static function newFactory(): SalesInvoiceFactory
    {
        return SalesInvoiceFactory::new();
    }

    /**
     * @return BelongsTo<SalesOrder, $this>
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id', 'weclapp_id');
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
            'creator_id'          => 'integer',
            'currency_id'         => 'integer',
            'customer_id'         => 'integer',
            'gross_amount'        => 'decimal:2',
            'invoice_date'        => 'datetime',
            'last_modified'       => 'datetime',
            'net_amount'          => 'decimal:2',
            'paid'                => 'boolean',
            'payment_method_id'   => 'integer',
            'pricing_date'        => 'datetime',
            'responsible_user_id' => 'integer',
            'sales_order_id'      => 'integer',
            'service_period_from' => 'datetime',
            'service_period_to'   => 'datetime',
            'shipping_date'       => 'datetime',
            'term_of_payment_id'  => 'integer',
            'version'             => 'integer',
            'weclapp_id'          => 'integer',
        ];
    }
}
