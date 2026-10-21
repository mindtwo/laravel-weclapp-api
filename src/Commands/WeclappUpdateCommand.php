<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Commands;

use Carbon\Exceptions\InvalidFormatException;
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
        $option = $this->option('since');
        $since = Carbon::now()->subDay();

        if (is_string($option) && $option !== '') {
            $parsed = $this->parseSince($option);

            if ($parsed === null) {
                $this->error(sprintf('Invalid --since value "%s"; pass a date or time such as "2026-10-01" or "-2 hours".', $option));

                return self::FAILURE;
            }

            $since = $parsed;
        }

        $sinceMs = $since->getTimestampMs();

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

    /**
     * Null when the value cannot be read as a date — a typo in a scheduler entry
     * should fail with a message, not a stack trace.
     */
    private function parseSince(string $value): ?Carbon
    {
        try {
            return Carbon::parse($value);
        } catch (InvalidFormatException) {
            return null;
        }
    }
}
