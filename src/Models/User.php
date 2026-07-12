<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Mindtwo\LaravelWeclappApi\Database\Factories\UserFactory;

/**
 * @property int $id
 * @property int|null $weclapp_id
 * @property string|null $email
 * @property string|null $first_name
 * @property \Illuminate\Support\Carbon|null $last_modified
 * @property string|null $last_name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class User extends Model
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected $table = 'weclapp_users';

    protected $fillable = [
        'email',
        'first_name',
        'last_modified',
        'last_name',
        'weclapp_id',
    ];

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
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
