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

        // An absent key is written through as null, for every map. Weclapp omits
        // empty fields from its JSON rather than sending them as null, so on a full
        // record "not there" is the only way it can say "cleared". Skipping absent
        // keys instead kept whatever the mirror held last: a price row whose end
        // date a user cleared in Weclapp stayed expired in the mirror, and a
        // consumer went on reading no list price for the article. The same held
        // for a customer id dropped, a start date removed, a reduction deleted —
        // every field a user can clear in Weclapp.
        foreach ($this->map as $column => $field) {
            $attributes[$column] = $record->{$field} ?? null;
        }

        foreach ($this->dates as $column => $field) {
            $attributes[$column] = isset($record->{$field})
                ? Carbon::createFromTimestampMs(
                    (int) $record->{$field},
                    (string) config('weclapp-api.timezone', 'UTC'),
                )
                : null;
        }

        foreach ($this->paths as $column => $path) {
            $attributes[$column] = data_get($record, $path);
        }

        foreach ($this->derive as $column => $callback) {
            $attributes[$column] = $callback($record);
        }

        return [...$attributes, ...$this->defaults];
    }
}
