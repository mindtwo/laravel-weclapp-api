<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Commands;

use Illuminate\Console\Command;
use Mindtwo\LaravelWeclappApi\Sync\EntitySynchronizer;
use Mindtwo\LaravelWeclappApi\Sync\SyncDefinition;
use Mindtwo\LaravelWeclappApi\Sync\SyncRegistry;

class WeclappSyncCommand extends Command
{
    protected $signature = 'weclapp:sync {entity? : Entity slug to sync; omit to sync all}';

    protected $description = 'Sync Weclapp entities into their mirror tables.';

    public function __construct(private readonly EntitySynchronizer $synchronizer)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $definitions = $this->resolveDefinitions();

        if ($definitions === null) {
            return self::FAILURE;
        }

        foreach ($definitions as $slug => $definition) {
            $count = $this->synchronizer->sync($definition);

            $this->info(sprintf('Synced %d %s.', $count, $slug));
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, SyncDefinition>|null
     */
    protected function resolveDefinitions(): ?array
    {
        $all = SyncRegistry::all();

        $entity = $this->argument('entity');
        $entity = is_string($entity) ? $entity : null;

        if ($entity === null) {
            return $all;
        }

        if (! isset($all[$entity])) {
            $this->error(sprintf('Unknown entity "%s". Available: %s', $entity, implode(', ', array_keys($all))));

            return null;
        }

        return [$entity => $all[$entity]];
    }
}
