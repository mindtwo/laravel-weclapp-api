<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Mindtwo\LaravelWeclappApi\Database\Factories\QuotationFactory;

/**
 * @property int $id
 * @property int|null $customer_id
 * @property int|null $report_id
 * @property int|null $status_id
 * @property int|null $weclapp_id
 * @property string|null $customer_number
 * @property string|null $gross_amount
 * @property \Illuminate\Support\Carbon|null $last_modified
 * @property string|null $net_amount
 * @property string|null $quotation_number
 * @property string|null $status
 * @property int|null $version
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Quotation extends Model
{
    /** @use HasFactory<QuotationFactory> */
    use HasFactory;

    protected $table = 'weclapp_quotations';

    protected $fillable = [
        'customer_id',
        'customer_number',
        'gross_amount',
        'last_modified',
        'net_amount',
        'quotation_number',
        'report_id',
        'status',
        'status_id',
        'version',
        'weclapp_id',
    ];

    protected static function newFactory(): QuotationFactory
    {
        return QuotationFactory::new();
    }

    /**
     * @return HasOne<Report, $this>
     */
    public function report(): HasOne
    {
        return $this->hasOne(Report::class, 'quotation_id', 'weclapp_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_id'   => 'integer',
            'gross_amount'  => 'decimal:2',
            'last_modified' => 'datetime',
            'net_amount'    => 'decimal:2',
            'report_id'     => 'integer',
            'status_id'     => 'integer',
            'version'       => 'integer',
            'weclapp_id'    => 'integer',
        ];
    }
}
