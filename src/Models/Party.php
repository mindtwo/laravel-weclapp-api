<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Mindtwo\LaravelWeclappApi\Database\Factories\PartyFactory;

/**
 * A Weclapp party. Customer and supplier are roles a party carries, not kinds
 * of party: `is_customer` and `is_supplier` mirror Weclapp's flags, while
 * `party_type` says whether it is an organisation or a person.
 *
 * @property int $id
 * @property int|null $responsible_user_id
 * @property int|null $sector_id
 * @property int $weclapp_id
 * @property string|null $company
 * @property string|null $company_2
 * @property string|null $customer_number
 * @property string|null $description
 * @property string|null $email
 * @property string|null $first_name
 * @property bool $is_customer
 * @property bool $is_supplier
 * @property \Illuminate\Support\Carbon|null $last_modified
 * @property string|null $last_name
 * @property string|null $party_type
 * @property string|null $phone
 * @property string|null $salutation
 * @property string|null $supplier_number
 * @property string|null $website
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Party extends Model
{
    /** @use HasFactory<PartyFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'weclapp_parties';

    protected $fillable = [
        'company',
        'company_2',
        'customer_number',
        'description',
        'email',
        'first_name',
        'is_customer',
        'is_supplier',
        'last_modified',
        'last_name',
        'party_type',
        'phone',
        'responsible_user_id',
        'salutation',
        'sector_id',
        'supplier_number',
        'website',
        'weclapp_id',
    ];

    protected static function newFactory(): PartyFactory
    {
        return PartyFactory::new();
    }

    /**
     * @param Builder<Party> $query
     */
    public function scopeCustomers(Builder $query): void
    {
        $query->where('is_customer', true);
    }

    /**
     * @param Builder<Party> $query
     */
    public function scopeSuppliers(Builder $query): void
    {
        $query->where('is_supplier', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_customer'         => 'boolean',
            'is_supplier'         => 'boolean',
            'last_modified'       => 'datetime',
            'responsible_user_id' => 'integer',
            'sector_id'           => 'integer',
            'weclapp_id'          => 'integer',
        ];
    }
}
