<?php

namespace Mindtwo\LaravelWeclappApi\Commands;

use Illuminate\Console\Command;

class GeneralCommand extends Command
{
    public $signature = 'weclapp';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
