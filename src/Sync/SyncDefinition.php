<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Sync;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Declarative mapping from a Weclapp collection endpoint onto a mirror model.
 */
final readonly class SyncDefinition
{
    /**
     * @param class-string<Model> $model The mirror model to upsert into
     * @param array<string, string> $map column => API field (scalar copy)
     * @param array<string, string> $dates column => API field (epoch-ms datetime)
     * @param array<string, mixed> $defaults column => static value applied to every record
     * @param string $key The mirror column used to match existing rows (its API field must be in $map)
     */
    public function __construct(
        public string $endpoint,
        public string $model,
        public array $map,
        public array $dates = [],
        public array $defaults = [],
        public string $key = 'weclapp_id',
    ) {}

    /**
     * Build the persistable attribute set from a raw API record.
     *
     * @return array<string, mixed>
     */
    public function attributes(object $record): array
    {
        $attributes = [];

        foreach ($this->map as $column => $field) {
            if (isset($record->{$field})) {
                $attributes[$column] = $record->{$field};
            }
        }

        foreach ($this->dates as $column => $field) {
            if (isset($record->{$field})) {
                $attributes[$column] = Carbon::createFromTimestampMs(
                    (int) $record->{$field},
                    (string) config('weclapp-api.timezone', 'UTC'),
                );
            }
        }

        return [...$attributes, ...$this->defaults];
    }
}
