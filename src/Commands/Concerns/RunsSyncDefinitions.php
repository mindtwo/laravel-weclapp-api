<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Commands\Concerns;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Mindtwo\LaravelWeclappApi\Sync\SyncDefinition;
use Mindtwo\LaravelWeclappApi\Sync\SyncRegistry;
use UnexpectedValueException;

/**
 * The entity loop shared by weclapp:sync and weclapp:update.
 *
 * Without an entity argument the command runs every registered definition.
 * Consumers publish only the mirror tables they read, so a definition whose
 * table is not migrated is skipped before anything is downloaded, and one
 * entity failing — a 403 on an unlicensed module, a timeout, an ignored id
 * cursor, a schema mismatch — does not stop the entities behind it. The run
 * still exits non-zero so a scheduler notices. A missing URL or key stays
 * fatal, since every entity would fail the same way. A named entity gets no
 * skip: asking for it explicitly means its table is expected to exist.
 *
 * Both commands take the same lock per entity, so a full sync and a delta
 * update never write one mirror at once: racing, they would collide on the
 * unique weclapp_id, and the sync would archive a row the update had just
 * inserted. A held lock skips the entity and fails the run.
 */
trait RunsSyncDefinitions
{
    private const string LOCK_PREFIX = 'weclapp-api:sync:';

    /**
     * Long enough for a full sync of a large tenant; a crashed run frees it
     * after a day, as Laravel's withoutOverlapping() does.
     */
    private const int LOCK_SECONDS = 86400;

    /**
     * @param Closure(SyncDefinition, string): void $sync runs one definition and prints its result
     */
    protected function runSyncDefinitions(Closure $sync): int
    {
        $all = SyncRegistry::all();

        $entity = $this->argument('entity');
        $entity = is_string($entity) ? $entity : null;

        if ($entity !== null && ! isset($all[$entity])) {
            $this->error(sprintf('Unknown entity "%s". Available: %s', $entity, implode(', ', array_keys($all))));

            return self::FAILURE;
        }

        $definitions = $entity === null ? $all : [$entity => $all[$entity]];
        $failed = false;

        foreach ($definitions as $slug => $definition) {
            $table = (new $definition->model)->getTable();

            if ($entity === null && ! Schema::hasTable($table)) {
                $this->line(sprintf('Skipped %s: %s is not migrated.', $slug, $table));

                continue;
            }

            $lock = Cache::lock(self::LOCK_PREFIX.$slug, self::LOCK_SECONDS);

            if (! $lock->get()) {
                $this->warn(sprintf('Skipped %s: another weclapp:sync or weclapp:update is running it.', $slug));
                $failed = true;

                continue;
            }

            try {
                $sync($definition, $slug);
            } catch (HttpClientException|QueryException|UnexpectedValueException $e) {
                $this->error(sprintf('%s: %s', $slug, $e->getMessage()));
                $failed = true;
            } finally {
                $lock->release();
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
