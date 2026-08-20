<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Mindtwo\LaravelWeclappApi\Spec\MirrorBlueprint;
use Mindtwo\LaravelWeclappApi\Spec\SpecReader;

class WeclappMakeMirrorCommand extends Command
{
    protected $signature = 'weclapp:make-mirror
        {resource : Weclapp resource name, e.g. articlePrice}
        {--only= : Comma-separated API fields to keep; omit to take every scalar}
        {--force : Overwrite existing files}
        {--dry : Print what would be written without touching the filesystem}';

    protected $description = 'Scaffold a mirror migration, model and factory from the vendored OpenAPI spec.';

    public function handle(): int
    {
        $resource = (string) $this->argument('resource');

        if (! in_array($resource, SpecReader::resources(), true)) {
            $this->error("Unknown resource [{$resource}].");
            $this->line('It must be addressable as /{resource}/id/{id} in the vendored spec.');

            if ($near = $this->closest($resource)) {
                $this->line("Did you mean [{$near}]?");
            }

            return self::FAILURE;
        }

        $blueprint = MirrorBlueprint::for($resource);

        if ($blueprint->columns === []) {
            $this->error("[{$resource}] resolved to no scalar columns; nothing to mirror.");

            return self::FAILURE;
        }

        $blueprint = $this->applyOnly($blueprint);

        $files = [
            $this->migrationPath($blueprint)                                                 => $this->migration($blueprint),
            $this->packagePath('src/Models/'.$blueprint->modelClass().'.php')                => $this->model($blueprint),
            $this->packagePath('database/factories/'.$blueprint->modelClass().'Factory.php') => $this->factory($blueprint),
        ];

        foreach ($files as $path => $contents) {
            if ($this->option('dry')) {
                $this->line("<info>would write</info> {$this->relative($path)}");

                continue;
            }

            if (file_exists($path) && ! $this->option('force')) {
                $this->error("{$this->relative($path)} already exists; pass --force to overwrite.");

                return self::FAILURE;
            }

            file_put_contents($path, $contents);
            $this->line("<info>wrote</info> {$this->relative($path)}");
        }

        $this->reportSkipped($blueprint);
        $this->reportManualSteps($blueprint);

        return self::SUCCESS;
    }

    private function applyOnly(MirrorBlueprint $blueprint): MirrorBlueprint
    {
        $only = (string) ($this->option('only') ?? '');

        if ($only === '') {
            return $blueprint;
        }

        $keep = array_map(trim(...), explode(',', $only));
        $known = array_column($blueprint->columns, 'source');

        foreach (array_diff($keep, $known) as $unknown) {
            $this->warn("--only lists [{$unknown}], which is not a scalar field of this resource; ignoring.");
        }

        return MirrorBlueprint::filtered($blueprint, $keep);
    }

    private function reportSkipped(MirrorBlueprint $blueprint): void
    {
        if ($blueprint->skipped === []) {
            return;
        }

        $this->newLine();
        $this->line('<comment>Skipped (a SyncDefinition maps flat scalars only):</comment>');

        foreach ($blueprint->skipped as $field => $reason) {
            $this->line(sprintf('  %-32s %s', $field, $reason));
        }
    }

    private function reportManualSteps(MirrorBlueprint $blueprint): void
    {
        $model = $blueprint->modelClass();

        $this->newLine();
        $this->line('<comment>Two edits are left to make by hand:</comment>');
        $this->newLine();
        $this->line('1. Add to <info>Sync\\SyncRegistry::all()</info>:');
        $this->newLine();
        $this->raw($this->registryEntry($blueprint));
        $this->newLine();
        $this->line('2. Add to <info>WeclappApiServiceProvider::MIGRATIONS</info> so it gets a publish tag:');
        $this->newLine();
        $this->raw(sprintf(
            "        '%s' => '%s',",
            Str::kebab(Str::pluralStudly($model)),
            basename($this->migrationPath($blueprint)),
        ));
        $this->newLine();
        $this->line('Then run the suite — MigrationPublishTagTest and FactorySpecEnumTest cover both.');
    }

    private function registryEntry(MirrorBlueprint $blueprint): string
    {
        $slug = Str::kebab(Str::pluralStudly($blueprint->modelClass()));
        $lines = [
            "            '{$slug}' => new SyncDefinition(",
            "                endpoint: '{$blueprint->resource}',",
            '                model: '.$blueprint->modelClass().'::class,',
            '                map: [',
        ];

        foreach ($blueprint->map() as $column => $field) {
            $lines[] = sprintf("                    '%s' => '%s',", $column, $field);
        }

        $lines[] = '                ],';

        if ($blueprint->dates() !== []) {
            $lines[] = '                dates: [';

            foreach ($blueprint->dates() as $column => $field) {
                $lines[] = sprintf("                    '%s' => '%s',", $column, $field);
            }

            $lines[] = '                ],';
        }

        $lines[] = '            ),';

        return implode(PHP_EOL, $lines);
    }

    private function migration(MirrorBlueprint $blueprint): string
    {
        $lines = [];

        foreach ($blueprint->columns as $column) {
            $call = match ($column['migration']) {
                'decimal' => sprintf("\$table->decimal('%s', 15, 4)", $column['name']),
                'string'  => $column['max_length'] !== null
                    ? sprintf("\$table->string('%s', %d)", $column['name'], $column['max_length'])
                    : sprintf("\$table->string('%s')", $column['name']),
                default => sprintf("\$table->%s('%s')", $column['migration'], $column['name']),
            };

            $call .= '->nullable()';

            if ($column['is_id']) {
                $call .= '->index()';
            }

            $lines[] = '            '.$call.';';
        }

        $body = implode(PHP_EOL, $lines);
        $table = $blueprint->table();

        return <<<PHP
        <?php

        declare(strict_types=1);

        use Illuminate\\Database\\Migrations\\Migration;
        use Illuminate\\Database\\Schema\\Blueprint;
        use Illuminate\\Support\\Facades\\Schema;

        // Generated by `weclapp:make-mirror {$blueprint->resource}` from the vendored
        // OpenAPI spec. Trim columns you do not read before publishing.
        return new class extends Migration
        {
            public function up(): void
            {
                Schema::create('{$table}', function (Blueprint \$table) {
                    \$table->id();
        {$body}
                    \$table->timestamps();
                });
            }

            public function down(): void
            {
                Schema::dropIfExists('{$table}');
            }
        };

        PHP;
    }

    private function model(MirrorBlueprint $blueprint): string
    {
        $class = $blueprint->modelClass();
        $fillable = [];
        $casts = [];
        $docs = [];

        foreach ($blueprint->columns as $column) {
            $fillable[] = sprintf("        '%s',", $column['name']);

            if ($column['cast'] !== null) {
                $casts[$column['name']] = $column['cast'];
            }

            $docs[] = ' * @property '.match (true) {
                $column['cast'] === 'integer'  => 'int|null',
                $column['cast'] === 'boolean'  => 'bool|null',
                $column['cast'] === 'datetime' => '\Illuminate\Support\Carbon|null',
                default                        => 'string|null',
            }.' $'.$column['name'];
        }

        sort($fillable);
        ksort($casts);

        $width = max(array_map(strlen(...), array_keys($casts) ?: ['']));
        $castLines = [];

        foreach ($casts as $column => $cast) {
            $castLines[] = sprintf("            '%s'%s => '%s',", $column, str_repeat(' ', $width - strlen($column)), $cast);
        }

        $fillableBody = implode(PHP_EOL, $fillable);
        $castsBody = implode(PHP_EOL, $castLines);
        $docBlock = implode(PHP_EOL, $docs);
        $table = $blueprint->table();

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace Mindtwo\\LaravelWeclappApi\\Models;

        use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;
        use Illuminate\\Database\\Eloquent\\Model;
        use Mindtwo\\LaravelWeclappApi\\Database\\Factories\\{$class}Factory;

        /**
         * @property int \$id
        {$docBlock}
         * @property \\Illuminate\\Support\\Carbon|null \$created_at
         * @property \\Illuminate\\Support\\Carbon|null \$updated_at
         */
        class {$class} extends Model
        {
            /** @use HasFactory<{$class}Factory> */
            use HasFactory;

            protected \$table = '{$table}';

            protected \$fillable = [
        {$fillableBody}
            ];

            protected static function newFactory(): {$class}Factory
            {
                return {$class}Factory::new();
            }

            /**
             * @return array<string, string>
             */
            protected function casts(): array
            {
                return [
        {$castsBody}
                ];
            }
        }

        PHP;
    }

    private function factory(MirrorBlueprint $blueprint): string
    {
        $class = $blueprint->modelClass();
        $lines = [];
        $widths = array_map(fn (array $c): int => strlen((string) $c['name']), $blueprint->columns);
        $width = $widths === [] ? 0 : max($widths);

        foreach ($blueprint->columns as $column) {
            $value = match (true) {
                $column['name'] === 'weclapp_id' => '$this->faker->unique()->numberBetween(10000, 99999)',
                // Enum values come from the spec, never from a guess.
                $column['enum'] !== null        => "'".$column['enum'][0]."'",
                $column['is_id']                => '$this->faker->numberBetween(10000, 99999)',
                $column['cast'] === 'integer'   => '$this->faker->numberBetween(1, 100)',
                $column['cast'] === 'boolean'   => '$this->faker->boolean()',
                $column['cast'] === 'datetime'  => '$this->faker->dateTime()',
                $column['cast'] === 'decimal:4' => '$this->faker->randomFloat(4, 1, 10000)',
                $column['migration'] === 'text' => '$this->faker->sentence()',
                default                         => '$this->faker->word()',
            };

            $lines[] = sprintf(
                "            '%s'%s => %s,",
                $column['name'],
                str_repeat(' ', $width - strlen((string) $column['name'])),
                $value,
            );
        }

        $body = implode(PHP_EOL, $lines);

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace Mindtwo\\LaravelWeclappApi\\Database\\Factories;

        use Illuminate\\Database\\Eloquent\\Factories\\Factory;
        use Mindtwo\\LaravelWeclappApi\\Models\\{$class};

        /**
         * @extends Factory<{$class}>
         */
        class {$class}Factory extends Factory
        {
            protected \$model = {$class}::class;

            public function definition(): array
            {
                return [
        {$body}
                ];
            }
        }

        PHP;
    }

    /**
     * Write preserving leading whitespace.
     *
     * `$this->line()` goes through SymfonyStyle, which strips indentation — fine
     * for prose, useless for a snippet meant to be pasted into a nested array.
     */
    private function raw(string $text): void
    {
        $this->getOutput()->getOutput()->writeln($text);
    }

    private function closest(string $resource): ?string
    {
        $best = null;
        $distance = PHP_INT_MAX;

        foreach (SpecReader::resources() as $candidate) {
            $current = levenshtein(strtolower($resource), strtolower($candidate));

            if ($current < $distance) {
                $distance = $current;
                $best = $candidate;
            }
        }

        return $distance <= 3 ? $best : null;
    }

    private ?string $migrationPath = null;

    /**
     * Memoised: the sequence is derived from what is on disk, so recomputing it
     * after the file is written would report a different name than was created.
     */
    private function migrationPath(MirrorBlueprint $blueprint): string
    {
        return $this->migrationPath ??= $this->packagePath(
            'database/migrations/2026_04_20_'.$this->sequence().'_create_'.$blueprint->table().'_table.php'
        );
    }

    /**
     * Keep the 1000xx sequence the hand-written migrations use, continuing past
     * the highest one on disk so ordering stays stable.
     */
    private function sequence(): string
    {
        $highest = 100000;

        foreach (glob($this->packagePath('database/migrations/*.php')) ?: [] as $file) {
            if (preg_match('/_(\d{6})_create_/', basename($file), $matches)) {
                $highest = max($highest, (int) $matches[1]);
            }
        }

        return (string) ($highest + 1);
    }

    private function packagePath(string $relative): string
    {
        return __DIR__.'/../../'.$relative;
    }

    private function relative(string $path): string
    {
        return str_replace(realpath($this->packagePath('')).'/', '', realpath(dirname($path)).'/'.basename($path));
    }
}
