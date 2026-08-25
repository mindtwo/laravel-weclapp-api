<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Sync;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Mindtwo\LaravelWeclappApi\WeclappClient;

final readonly class EntitySynchronizer
{
    public function __construct(private WeclappClient $client) {}

    /**
     * Fetch every record for the definition's endpoint and upsert it into the
     * mirror table, matched on the definition's key column.
     *
     * When the definition opts into reconciliation and this is a full sync, rows
     * Weclapp no longer returns are soft-deleted afterwards, so the mirror stops
     * drifting in one direction.
     *
     * @param array<string, mixed> $filters extra query filters (e.g. a delta filter)
     *
     * @return array{synced: int, archived: int}
     */
    public function sync(SyncDefinition $definition, array $filters = []): array
    {
        $records = $this->client->get($definition->endpoint, [...$definition->filters, ...$filters]);

        $seen = [];

        foreach ($records as $record) {
            $attributes = $definition->attributes($record);

            if (! isset($attributes[$definition->key])) {
                continue;
            }

            $seen[] = $attributes[$definition->key];

            $model = new $definition->model;

            // Dropping the soft-delete scope is what withTrashed() does, spelled so
            // it also holds for a mirror model that does not soft-delete. A record
            // that disappeared and came back has to be restored onto its existing
            // row; matching only live rows would insert a duplicate and leave the
            // archived one behind.
            $target = $model->newQuery()
                ->withoutGlobalScope(SoftDeletingScope::class)
                ->where($definition->key, $attributes[$definition->key])
                ->first() ?? $model;

            foreach ($attributes as $column => $value) {
                $target->setAttribute($column, $value);
            }

            // Only touched when the row is actually archived, so a model without the
            // column is never handed an attribute it cannot store.
            if ($target->getAttribute('deleted_at') !== null) {
                $target->setAttribute('deleted_at', null);
            }

            $target->save();
        }

        return [
            'synced'   => $records->count(),
            'archived' => $this->reconcile($definition, $records, $seen, $filters),
        ];
    }

    /**
     * Soft-delete the mirror rows this run did not see.
     *
     * Three things have to be true before anything is archived, because each one
     * on its own would turn a normal run into a mass deletion:
     *
     *  - the definition owns the whole table (`$reconciles`), otherwise the
     *    customers sync would archive every supplier sharing the Party model;
     *  - no extra filters were applied, because a delta sync deliberately returns
     *    a fraction of the data and everything else would look stale;
     *  - the response was not empty, because a revoked permission or a Weclapp
     *    outage that answers `{"result": []}` is indistinguishable here from a
     *    genuinely emptied resource, and archiving the entire mirror on that
     *    basis is the worse guess.
     *
     * A truncated page is not a risk: WeclappClient::get() throws on a failed
     * page rather than returning a short list, so this is never reached with a
     * partial set.
     *
     * Opting a definition into reconciliation implies its model soft-deletes;
     * every mirror model does. On a model without the trait `delete()` would
     * remove the rows outright rather than archive them.
     *
     * @param Collection<int, object> $records
     * @param list<mixed> $seen
     * @param array<string, mixed> $filters
     */
    private function reconcile(SyncDefinition $definition, Collection $records, array $seen, array $filters): int
    {
        if (! $definition->reconciles || $filters !== [] || $records->isEmpty()) {
            return 0;
        }

        /** @var Model $model */
        $model = new $definition->model;

        return $model->newQuery()
            ->whereNotIn($definition->key, $seen)
            ->delete();
    }
}
