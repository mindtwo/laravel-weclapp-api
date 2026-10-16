<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Support\Carbon;
use Mindtwo\LaravelWeclappApi\Commands\Concerns\RunsSyncDefinitions;
use Mindtwo\LaravelWeclappApi\Sync\EntitySynchronizer;
use Mindtwo\LaravelWeclappApi\Sync\SyncDefinition;

class WeclappUpdateCommand extends Command implements Isolatable
{
    use RunsSyncDefinitions;

    protected $signature = 'weclapp:update {entity? : Entity slug to update; omit for all} {--since= : Only records modified after this date/time (default: 24h ago)}';

    protected $description = 'Incrementally sync Weclapp entities changed since a given time.';

    public function __construct(private readonly EntitySynchronizer $synchronizer)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $sinceMs = $this->since()->getTimestampMs();

        return $this->runSyncDefinitions(function (SyncDefinition $definition, string $slug) use ($sinceMs): void {
            // Only entities that expose a modification timestamp can be filtered
            // server-side; the rest fall back to a full sync.
            $filters = isset($definition->dates['last_modified'])
                ? ['lastModifiedDate-gt' => $sinceMs]
                : [];

            $result = $this->synchronizer->sync($definition, $filters);

            $this->info(sprintf('Updated %d %s.', $result['synced'], $slug));
        });
    }

    protected function since(): Carbon
    {
        $since = $this->option('since');

        return is_string($since) && $since !== ''
            ? Carbon::parse($since)
            : Carbon::now()->subDay();
    }
}
