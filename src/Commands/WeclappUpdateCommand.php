<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Mindtwo\LaravelWeclappApi\Sync\EntitySynchronizer;
use Mindtwo\LaravelWeclappApi\Sync\SyncDefinition;
use Mindtwo\LaravelWeclappApi\Sync\SyncRegistry;

class WeclappUpdateCommand extends Command
{
    protected $signature = 'weclapp:update {entity? : Entity slug to update; omit for all} {--since= : Only records modified after this date/time (default: 24h ago)}';

    protected $description = 'Incrementally sync Weclapp entities changed since a given time.';

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

        $sinceMs = $this->since()->getTimestampMs();

        foreach ($definitions as $slug => $definition) {
            // Only entities that expose a modification timestamp can be filtered
            // server-side; the rest fall back to a full sync.
            $filters = isset($definition->dates['last_modified'])
                ? ['lastModifiedDate-gt' => $sinceMs]
                : [];

            $count = $this->synchronizer->sync($definition, $filters);

            $this->info(sprintf('Updated %d %s.', $count, $slug));
        }

        return self::SUCCESS;
    }

    protected function since(): Carbon
    {
        $since = $this->option('since');

        return is_string($since) && $since !== ''
            ? Carbon::parse($since)
            : Carbon::now()->subDay();
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
