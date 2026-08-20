<?php

declare(strict_types=1);

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
        ->and($blueprint->dates()['start_date'])->toBe('startDate');
});

it('keeps weclapp_id even when --only omits it', function () {
    // Without it the synchronizer has nothing to match on.
    $filtered = MirrorBlueprint::filtered(MirrorBlueprint::for('articlePrice'), ['price']);

    expect(array_column($filtered->columns, 'name'))->toContain('weclapp_id', 'price')
        ->and($filtered->columns)->toHaveCount(2);
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

        if ($blueprint->columns === []) {
            continue; // lookup lists whose response refs the shared customValue schema
        }

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

    expect($checked)->toBeGreaterThan(100);
});

it('generates parseable php for every resource in the spec', function () {
    $parser = (new PhpParser\ParserFactory)->createForNewestSupportedVersion();
    $command = app()->make(Mindtwo\LaravelWeclappApi\Commands\WeclappMakeMirrorCommand::class);
    $parsed = 0;

    foreach (SpecReader::resources() as $resource) {
        $blueprint = MirrorBlueprint::for($resource);

        if ($blueprint->columns === []) {
            continue;
        }

        foreach (['migration', 'model', 'factory'] as $kind) {
            $source = (string) (new ReflectionMethod($command, $kind))->invoke($command, $blueprint);

            expect(fn () => $parser->parse($source))
                ->not->toThrow(Throwable::class, "{$resource} {$kind} does not parse");

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
        // escapeshellarg, not escapeshellcmd: Herd's PHP lives under a path with
        // a space in it, which escapeshellcmd does not quote.
        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file).' 2>&1', $output, $status);
        unlink($file);
        unlink($stub);

        expect($status)->toBe(0, "{$resource} {$kind}: ".implode(' ', $output));
    }
})->with(['articlePrice', 'party']); // php -l cross-check, in case php-parser is absent
