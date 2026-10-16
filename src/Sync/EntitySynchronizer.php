<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Sync;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Mindtwo\LaravelWeclappApi\WeclappClient;

final readonly class EntitySynchronizer
{
    /**
     * Records looked up per query. Well under every driver's placeholder cap,
     * large enough that a full sync of a big table is a few hundred lookups.
     */
    private const int CHUNK = 500;

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

        // Rows are saved one model at a time so Eloquent's dirty check decides
        // what is written and updated_at only moves on a real change; the lookup
        // is what is batched, one query per chunk instead of one per record.
        foreach ($records->chunk(self::CHUNK) as $chunk) {
            $attributes = [];

            foreach ($chunk as $record) {
                $candidate = $definition->attributes($record);

                if (isset($candidate[$definition->key])) {
                    $attributes[] = $candidate;
                }
            }

            $existing = $this->existing($definition, array_column($attributes, $definition->key));

            foreach ($attributes as $row) {
                $seen[] = $row[$definition->key];

                $target = $existing[$row[$definition->key]] ?? new $definition->model;

                foreach ($row as $column => $value) {
                    $target->setAttribute($column, $value);
                }

                // Only touched when the row is actually archived, so a model without the
                // column is never handed an attribute it cannot store.
                if ($target->getAttribute('deleted_at') !== null) {
                    $target->setAttribute('deleted_at', null);
                }

                $target->save();
            }
        }

        return [
            'synced'   => $records->count(),
            'archived' => $this->reconcile($definition, $seen, $filters),
        ];
    }

    /**
     * The mirror rows already holding any of the given keys, keyed by that key.
     *
     * Dropping the soft-delete scope is what withTrashed() does, spelled so it
     * also holds for a mirror model that does not soft-delete. A record that
     * disappeared and came back has to be restored onto its existing row;
     * matching only live rows would insert a duplicate and leave the archived
     * one behind.
     *
     * @param list<mixed> $keys
     *
     * @return array<int|string, Model>
     */
    private function existing(SyncDefinition $definition, array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        /** @var Model $model */
        $model = new $definition->model;

        return $model->newQuery()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->whereIn($definition->key, $keys)
            ->get()
            ->keyBy($definition->key)
            ->all();
    }

    /**
     * Soft-delete the mirror rows this run did not see.
     *
     * Three things have to be true before anything is archived, because each one
     * on its own would turn a normal run into a mass deletion:
     *
     *  - the definition owns the whole table (`$reconciles`); two definitions
     *    sharing one model would each archive the other's rows;
     *  - no extra filters were applied, because a delta sync deliberately returns
     *    a fraction of the data and everything else would look stale;
     *  - at least one record carried a key, because a revoked permission or a
     *    Weclapp outage that answers `{"result": []}` is indistinguishable here
     *    from a genuinely emptied resource, and archiving the entire mirror on
     *    that basis is the worse guess. The check is on the keys, not the
     *    records: a response full of records without an id would otherwise
     *    compile to `NOT IN ()`, which matches every row.
     *
     * A partial set is not a risk: WeclappClient::get() throws on a failed page
     * rather than returning a short list, and it walks the collection by id
     * cursor, so a record deleted or inserted mid-read cannot shift a later
     * page and drop a live record out of the result.
     *
     * The keys are inlined rather than bound: a tenant with more records than
     * the driver's placeholder limit (65 535 on MySQL and PostgreSQL, 32 766 on
     * SQLite) would otherwise fail here, after every row had been upserted.
     *
     * Opting a definition into reconciliation implies its model soft-deletes;
     * every mirror model does. On a model without the trait `delete()` would
     * remove the rows outright rather than archive them.
     *
     * @param list<mixed> $seen
     * @param array<string, mixed> $filters
     */
    private function reconcile(SyncDefinition $definition, array $seen, array $filters): int
    {
        if (! $definition->reconciles || $filters !== [] || $seen === []) {
            return 0;
        }

        /** @var Model $model */
        $model = new $definition->model;

        return $model->newQuery()
            ->whereIntegerNotInRaw($definition->key, collect($seen))
            ->delete();
    }
}
