<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Mindtwo\LaravelWeclappApi\Database\Factories\ProjectFactory;

/**
 * @property int $id
 * @property int|null $customer_id
 * @property int|null $weclapp_id
 * @property string|null $customer_number
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $last_modified
 * @property string|null $project_number
 * @property \Illuminate\Support\Carbon|null $project_start_date
 * @property string|null $status
 * @property string|null $title
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected $table = 'weclapp_projects';

    protected $fillable = [
        'customer_id',
        'customer_number',
        'description',
        'last_modified',
        'project_number',
        'project_start_date',
        'status',
        'title',
        'weclapp_id',
    ];

    protected static function newFactory(): ProjectFactory
    {
        return ProjectFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_id'        => 'integer',
            'last_modified'      => 'datetime',
            'project_start_date' => 'datetime',
            'weclapp_id'         => 'integer',
        ];
    }
}
