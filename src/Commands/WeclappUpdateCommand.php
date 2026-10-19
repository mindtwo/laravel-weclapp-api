<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Mindtwo\LaravelWeclappApi\Commands\Concerns\RunsSyncDefinitions;
use Mindtwo\LaravelWeclappApi\Sync\EntitySynchronizer;
use Mindtwo\LaravelWeclappApi\Sync\SyncDefinition;

class WeclappUpdateCommand extends Command
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
            // The delta is a server-side filter on lastModifiedDate, which every
            // Weclapp entity carries. A definition that does not map it gets no
            // filter, and a sync without filters is a full one that reconciles —
            // the opposite of what this command promises — so it is skipped
            // rather than run silently as something else.
            if (! in_array('lastModifiedDate', $definition->dates, true)) {
                $this->warn(sprintf('Skipped %s: lastModifiedDate is not mapped; run weclapp:sync %s instead.', $slug, $slug));

                return;
            }

            $result = $this->synchronizer->sync($definition, ['lastModifiedDate-gt' => $sinceMs]);

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
