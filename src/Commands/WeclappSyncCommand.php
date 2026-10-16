<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Mindtwo\LaravelWeclappApi\Commands\Concerns\RunsSyncDefinitions;
use Mindtwo\LaravelWeclappApi\Sync\EntitySynchronizer;
use Mindtwo\LaravelWeclappApi\Sync\SyncDefinition;

class WeclappSyncCommand extends Command implements Isolatable
{
    use RunsSyncDefinitions;

    protected $signature = 'weclapp:sync {entity? : Entity slug to sync; omit to sync all}';

    protected $description = 'Sync Weclapp entities into their mirror tables.';

    public function __construct(private readonly EntitySynchronizer $synchronizer)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        return $this->runSyncDefinitions(function (SyncDefinition $definition, string $slug): void {
            $result = $this->synchronizer->sync($definition);

            $this->info(sprintf('Synced %d %s.', $result['synced'], $slug));

            if ($result['archived'] > 0) {
                $this->warn(sprintf('Archived %d %s no longer in Weclapp.', $result['archived'], $slug));
            }
        });
    }
}
