<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Sync;

use Mindtwo\LaravelWeclappApi\WeclappClient;

final readonly class EntitySynchronizer
{
    public function __construct(private WeclappClient $client) {}

    /**
     * Fetch every record for the definition's endpoint and upsert it into the
     * mirror table, matched on the definition's key column.
     *
     * @param array<string, mixed> $filters extra query filters (e.g. a delta filter)
     *
     * @return int the number of records processed
     */
    public function sync(SyncDefinition $definition, array $filters = []): int
    {
        $records = $this->client->get($definition->endpoint, $filters);

        foreach ($records as $record) {
            $attributes = $definition->attributes($record);

            if (! isset($attributes[$definition->key])) {
                continue;
            }

            $model = new $definition->model;

            $target = $model->newQuery()
                ->where($definition->key, $attributes[$definition->key])
                ->first() ?? $model;

            foreach ($attributes as $column => $value) {
                $target->setAttribute($column, $value);
            }

            $target->save();
        }

        return $records->count();
    }
}
