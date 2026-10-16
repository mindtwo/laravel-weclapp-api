<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Commands\Concerns;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Schema;
use Mindtwo\LaravelWeclappApi\Sync\SyncDefinition;
use Mindtwo\LaravelWeclappApi\Sync\SyncRegistry;

/**
 * The entity loop shared by weclapp:sync and weclapp:update.
 *
 * Without an entity argument the command runs every registered definition.
 * Consumers publish only the mirror tables they read, so a definition whose
 * table is not migrated is skipped before anything is downloaded, and one
 * entity failing — a 403 on an unlicensed module, a schema mismatch — does not
 * stop the entities behind it. The run still exits non-zero so a scheduler
 * notices. A named entity gets no skip: asking for it explicitly means its
 * table is expected to exist.
 */
trait RunsSyncDefinitions
{
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

            try {
                $sync($definition, $slug);
            } catch (RequestException|QueryException $e) {
                $this->error(sprintf('%s: %s', $slug, $e->getMessage()));
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
