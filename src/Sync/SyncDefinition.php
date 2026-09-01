<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Sync;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Declarative mapping from a Weclapp collection endpoint onto a mirror model.
 *
 * `$derive` is the one escape hatch from the declarative maps. `$paths` can only
 * address a nested entry by position, so picking the single flagged entry out of a
 * collection — the articleImages row with `mainImage` true — needs real code. It
 * takes plain closures rather than a matcher mini-language, so there is nothing to
 * learn beyond PHP; the trade is that a definition carrying one cannot be
 * serialised, which is fine while SyncRegistry builds them all in-process.
 */
final readonly class SyncDefinition
{
    /**
     * @param class-string<Model> $model The mirror model to upsert into
     * @param array<string, string> $map column => API field (scalar copy)
     * @param array<string, string> $dates column => API field (epoch-ms datetime)
     * @param array<string, string> $paths column => dot-path to a scalar nested inside
     *                                     the record, e.g. `reductionAdditions.0.value`
     * @param array<string, callable(object): mixed> $derive column => callback, for values no
     *                                                       dot-path can address; see the note below
     * @param array<string, mixed> $defaults column => static value applied to every record
     * @param array<string, mixed> $filters query filters narrowing a shared resource to this entity
     * @param string $key The mirror column used to match existing rows (its API field must be in $map)
     * @param bool $reconciles Whether a full sync may soft-delete mirror rows Weclapp
     *                         no longer returns. Only safe when this definition owns
     *                         every row in the model's table — two definitions sharing
     *                         a model would each treat the other's rows as stale.
     */
    public function __construct(
        public string $endpoint,
        public string $model,
        public array $map,
        public array $dates = [],
        public array $paths = [],
        public array $derive = [],
        public array $defaults = [],
        public array $filters = [],
        public string $key = 'weclapp_id',
        public bool $reconciles = false,
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

        foreach ($this->paths as $column => $path) {
            $value = data_get($record, $path);

            if ($value !== null) {
                $attributes[$column] = $value;
            }
        }

        // Unlike the maps above, a derived null is written through rather than
        // skipped. The others skip because Weclapp omits null fields from JSON
        // entirely, so an absent key says nothing about the value. A closure runs
        // against the whole record and answers definitively, so its null means
        // "there is none" — and keeping the previous value instead would leave a
        // mirror claiming Weclapp still has an image it no longer has.
        foreach ($this->derive as $column => $callback) {
            $attributes[$column] = $callback($record);
        }

        return [...$attributes, ...$this->defaults];
    }
}
