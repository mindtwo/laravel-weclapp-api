<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Mindtwo\LaravelWeclappApi\Spec\MirrorBlueprint;
use Mindtwo\LaravelWeclappApi\Spec\SpecReader;

// The generator's whole value is that it derives facts from the spec instead of
// guessing them, so these tests check the derivation rather than the file
// plumbing. `--dry` is used throughout: the command writes into the package
// itself, and a test suite must not leave models behind.

it('resolves composed schemas rather than reading properties directly', function () {
    // articlePrice reports 0 properties unresolved because of allOf/$ref.
    $raw = SpecReader::spec()['components']['schemas']['articlePrice']['properties'] ?? [];

    expect($raw)->toBe([])
        ->and(SpecReader::properties('articlePrice'))->toHaveCount(16);
});

it('derives the article price columns the hand-written mirror uses', function () {
    $columns = collect(MirrorBlueprint::for('articlePrice')->columns)->keyBy('name');

    expect($columns->keys())->toContain(
        'weclapp_id',
        'article_id',
        'customer_id',
        'currency_id',
        'price',
        'price_scale_type',
        'price_scale_value',
        'sales_channel',
        'description',
        'start_date',
        'end_date',
        'version',
    );

    // Weclapp serialises ids and the lock counter as strings; a naive type read
    // would make them varchar.
    expect($columns['weclapp_id']['migration'])->toBe('unsignedBigInteger')
        ->and($columns['customer_id']['migration'])->toBe('unsignedBigInteger')
        ->and($columns['version']['migration'])->toBe('integer')
        ->and($columns['price']['migration'])->toBe('decimal')
        ->and($columns['start_date']['is_date'])->toBeTrue();
});

it('types a field as an id from the spec, not from its name', function () {
    $party = collect(MirrorBlueprint::for('party')->columns)->keyBy('name');
    $category = collect(MirrorBlueprint::for('articleCategory')->columns)->keyBy('name');
    $rule = collect(MirrorBlueprint::for('taxDeterminationRule')->columns)->keyBy('name');

    // Marked `x-weclapp.entity`, and an unmarked unbounded `*Id` string: references.
    expect($party['responsible_user_id']['migration'])->toBe('unsignedBigInteger')
        ->and($party['responsible_user_id']['is_id'])->toBeTrue()
        ->and($category['image_id']['migration'])->toBe('unsignedBigInteger')
        // A Leitweg-ID is a bounded string like 04011000-1234512345-06; an integer
        // column would refuse it on MySQL and mangle it elsewhere.
        ->and($party['x_rechnung_leitweg_id']['migration'])->toBe('string')
        ->and($party['x_rechnung_leitweg_id']['max_length'])->toBe(46)
        ->and($party['x_rechnung_leitweg_id']['is_id'])->toBeFalse()
        ->and($rule['valid_vat_id']['migration'])->toBe('boolean');
});

it('skips nested collections and entity references', function () {
    $blueprint = MirrorBlueprint::for('salesInvoice');

    expect($blueprint->skipped)->toHaveKey('salesInvoiceItems')
        ->and($blueprint->skipped['salesInvoiceItems'])->toBe('nested collection')
        ->and($blueprint->skipped)->toHaveKey('deliveryAddress')
        ->and($blueprint->skipped['deliveryAddress'])->toBe('reference to another entity')
        ->and(array_column($blueprint->columns, 'name'))->not->toContain('sales_invoice_items');
});

it('captures enum values from the schema a property refs', function () {
    $columns = collect(MirrorBlueprint::for('salesInvoice')->columns)->keyBy('name');

    expect($columns['status']['enum'])->toContain('NEW', 'OPEN_ITEM_CREATED')
        ->and($columns['status']['enum'])->not->toContain('OPEN')
        ->and($columns['sales_invoice_type']['enum'])->toContain('STANDARD_INVOICE');
});

it('splits date fields out of the field map', function () {
    $blueprint = MirrorBlueprint::for('articlePrice');

    expect($blueprint->map())->toHaveKey('weclapp_id')
        ->and($blueprint->map())->not->toHaveKey('start_date')
        ->and($blueprint->dates())->toHaveKey('start_date')
        ->and($blueprint->dates()['start_date'])->toBe('startDate')
        // Named like the shipped mirrors, which the update command's delta relies on.
        ->and($blueprint->dates())->toHaveKey('last_modified')
        ->and($blueprint->dates()['last_modified'])->toBe('lastModifiedDate');
});

it('keeps weclapp_id even when --only omits it', function () {
    // Without it the synchronizer has nothing to match on.
    $filtered = MirrorBlueprint::filtered(MirrorBlueprint::for('articlePrice'), ['price']);

    expect(array_column($filtered->columns, 'name'))->toContain('weclapp_id', 'price')
        ->and($filtered->columns)->toHaveCount(2);
});

it('reads the schema from the GET-by-id response, which is not always the resource name', function () {
    expect(SpecReader::schemaFor('articlePrice'))->toBe('articlePrice')
        ->and(SpecReader::schemaFor('articleAccountingCode'))->toBe('customValue')
        ->and(SpecReader::schemaFor('shipmentReturnReason'))->toBe('shipmentReturnDescription')
        ->and(SpecReader::schemaFor('notARealResource'))->toBeNull();

    // Both used to come back empty, and the command refused them as having nothing to mirror.
    expect(array_column(MirrorBlueprint::for('shipmentReturnReason')->columns, 'name'))->toContain('weclapp_id', 'name')
        ->and(array_column(MirrorBlueprint::for('articleAccountingCode')->columns, 'name'))->toContain('weclapp_id', 'name');
});

it('names the table and model from the resource', function () {
    expect(MirrorBlueprint::for('salesInvoice')->table())->toBe('weclapp_sales_invoices')
        ->and(MirrorBlueprint::for('salesInvoice')->modelClass())->toBe('SalesInvoice');
});

it('rejects a resource the spec does not expose', function () {
    $this->artisan('weclapp:make-mirror', ['resource' => 'notARealResource', '--dry' => true])
        ->expectsOutputToContain('Unknown resource')
        ->assertExitCode(1);
});

it('suggests a near match on a typo', function () {
    $this->artisan('weclapp:make-mirror', ['resource' => 'articlePric', '--dry' => true])
        ->expectsOutputToContain('Did you mean [articlePrice]?')
        ->assertExitCode(1);
});

it('reports the files it would write without touching the filesystem', function () {
    $model = __DIR__.'/../../src/Models/Opportunity.php';

    $this->artisan('weclapp:make-mirror', ['resource' => 'opportunity', '--dry' => true])
        ->expectsOutputToContain('src/Models/Opportunity.php')
        ->assertExitCode(0);

    expect(file_exists($model))->toBeFalse();
});

it('prints package-relative paths on every platform', function () {
    // realpath() returns backslashes on Windows. The first version of relative()
    // string-matched an unnormalised root, so the prefix never matched there and
    // the command printed absolute paths -- green on macOS, red on windows-latest.
    $command = app()->make(Mindtwo\LaravelWeclappApi\Commands\WeclappMakeMirrorCommand::class);
    $method = new ReflectionMethod($command, 'relative');

    foreach (['/pkg/src/Models/Party.php', '\\pkg\\src\\Models\\Party.php'] as $separatorStyle) {
        $root = (new ReflectionMethod($command, 'packageRoot'))->invoke($command);
        $result = $method->invoke($command, $root.$separatorStyle);

        expect($result)->not->toContain('\\')
            ->and($result)->not->toStartWith('/')
            ->and($result)->toBe('pkg/src/Models/Party.php');
    }
});

// The package deliberately ships mirrors for only a handful of entities, but the
// generator is the reason that stays a choice rather than a constraint. These two
// prove bulk generation would work on demand, without committing 357 files nobody
// reads. Both are exhaustive and cost well under a second.

it('derives a usable blueprint for every resource in the spec', function () {
    $checked = 0;

    foreach (SpecReader::resources() as $resource) {
        $blueprint = MirrorBlueprint::for($resource);

        // Every resource resolves to a schema with scalars, the lookup lists and
        // the shipmentReturn* ones through the schema their GET-by-id answers with.
        expect($blueprint->columns)->not->toBe([], "{$resource}: no columns");

        $names = array_column($blueprint->columns, 'name');

        expect($names)->toBe(array_unique($names), "{$resource}: duplicate column name")
            ->and($blueprint->table())->toStartWith('weclapp_');

        foreach ($blueprint->columns as $column) {
            expect($column['name'])->toMatch('/^[a-z][a-z0-9_]*$/', "{$resource}: bad column name");
            expect($column['migration'])->toBeIn([
                'unsignedBigInteger', 'integer', 'boolean', 'decimal', 'string', 'text', 'datetime',
            ]);
        }

        // Every column is reachable by the synchronizer through exactly one of the
        // two maps; a column in neither would silently never be filled.
        expect(count($blueprint->map()) + count($blueprint->dates()))->toBe(count($blueprint->columns));

        $checked++;
    }

    expect($checked)->toBe(count(SpecReader::resources()));
});

it('generates parseable php for every resource in the spec', function () {
    $parser = (new PhpParser\ParserFactory)->createForNewestSupportedVersion();
    $command = app()->make(Mindtwo\LaravelWeclappApi\Commands\WeclappMakeMirrorCommand::class);
    $parsed = 0;

    foreach (SpecReader::resources() as $resource) {
        $blueprint = MirrorBlueprint::for($resource);

        foreach (['migration', 'model', 'factory'] as $kind) {
            $source = (string) (new ReflectionMethod($command, $kind))->invoke($command, $blueprint);

            // Not `->not->toThrow(..., $label)`: that second argument is a message
            // filter, and a real parse error never matches it, so the opposite
            // expectation passed on any output.
            try {
                $parser->parse($source);
            } catch (PhpParser\Error $e) {
                $this->fail("{$resource} {$kind} does not parse: {$e->getMessage()}");
            }

            $parsed++;
        }
    }

    expect($parsed)->toBeGreaterThan(300);
    // nikic/php-parser arrives with phpstan rather than being declared here.
})->skip(! class_exists(PhpParser\ParserFactory::class), 'nikic/php-parser not installed');

it('generates syntactically valid php for every mirrored resource', function (string $resource) {
    $command = app()->make(Mindtwo\LaravelWeclappApi\Commands\WeclappMakeMirrorCommand::class);
    $blueprint = MirrorBlueprint::for($resource);

    foreach (['migration', 'model', 'factory'] as $kind) {
        $method = new ReflectionMethod($command, $kind);
        $source = (string) $method->invoke($command, $blueprint);

        $stub = (string) tempnam(sys_get_temp_dir(), 'mirror');
        $file = $stub.'.php';
        file_put_contents($file, $source);

        // proc_open with an array command bypasses shell parsing on both
        // platforms. Building a string for exec() means quoting it for cmd.exe,
        // which mangles a line that both starts and ends with a quote -- and
        // PHP_BINARY contains a space under Herd.
        $process = proc_open(
            [PHP_BINARY, '-l', $file],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        array_map(fclose(...), $pipes);
        $status = proc_close($process);

        unlink($file);
        unlink($stub);

        expect($status)->toBe(0, "{$resource} {$kind}: ".trim($output));
    }
    // A php -l cross-check, kept in case php-parser is absent.
})->with(['articlePrice', 'party']);

it('registers the generator only when the vendored spec is present', function () {
    // docs/ is export-ignored, so a composer install has no spec and the command
    // would fail on read -- and would write into vendor/ if it did not. It is
    // development tooling; a git checkout has the spec, a consumer install does not.
    expect(SpecReader::available())->toBeTrue('spec missing from this checkout')
        ->and(array_keys(Artisan::all()))->toContain('weclapp:make-mirror');
});

it('explains where the spec should be when it is missing', function () {
    expect(SpecReader::path())->toEndWith('docs/specifications/weclapp-openapi_v2.json');
});

describe('writing', function () {
    it('refuses before writing anything when a target exists', function () {
        $before = glob(__DIR__.'/../../database/migrations/*.php');

        $this->artisan('weclapp:make-mirror', ['resource' => 'articlePrice'])
            ->expectsOutputToContain('src/Models/ArticlePrice.php already exists; pass --force to overwrite.')
            ->assertExitCode(1);

        // The migration used to be written first and left behind as an orphan.
        expect(glob(__DIR__.'/../../database/migrations/*.php'))->toBe($before);
    });

    it('regenerates an existing mirror into its own migration rather than a second one', function () {
        $this->artisan('weclapp:make-mirror', ['resource' => 'articlePrice', '--dry' => true, '--force' => true])
            ->expectsOutputToContain('would overwrite database/migrations/2026_04_20_100010_create_weclapp_article_prices_table.php')
            ->expectsOutputToContain('would overwrite src/Models/ArticlePrice.php')
            ->assertExitCode(0);
    });

    it('takes the next sequence for a table that has no migration yet', function () {
        $this->artisan('weclapp:make-mirror', ['resource' => 'opportunity', '--dry' => true])
            ->expectsOutputToContain('would write database/migrations/2026_04_20_100012_create_weclapp_opportunities_table.php')
            ->assertExitCode(0);
    });
});

it('scaffolds a mirror that soft-deletes and reconciles, like the shipped ones', function () {
    $command = app()->make(Mindtwo\LaravelWeclappApi\Commands\WeclappMakeMirrorCommand::class);
    $blueprint = MirrorBlueprint::for('opportunity');
    $render = fn (string $kind): string => (string) (new ReflectionMethod($command, $kind))->invoke($command, $blueprint);

    expect($render('migration'))->toContain('$table->softDeletes();')
        ->and($render('model'))->toContain('use SoftDeletes;')
        ->and($render('model'))->toContain('$deleted_at')
        ->and($render('registryEntry'))->toContain('reconciles: true,');
});
