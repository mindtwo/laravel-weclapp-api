<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Mindtwo\LaravelWeclappApi\Database\Factories\ReportFactory;

/**
 * @property int $id
 * @property int|null $quotation_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory;

    protected $table = 'weclapp_reports';

    protected $fillable = [
        'quotation_id',
    ];

    protected static function newFactory(): ReportFactory
    {
        return ReportFactory::new();
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
            'quotation_id' => 'integer',
        ];
    }
}
