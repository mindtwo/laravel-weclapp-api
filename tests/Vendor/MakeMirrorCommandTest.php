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

it('generates syntactically valid php for every mirrored resource', function (string $resource) {
    $command = app()->make(Mindtwo\LaravelWeclappApi\Commands\WeclappMakeMirrorCommand::class);
    $blueprint = MirrorBlueprint::for($resource);

    foreach (['migration', 'model', 'factory'] as $kind) {
        $method = new ReflectionMethod($command, $kind);
        $source = (string) $method->invoke($command, $blueprint);

        $file = tempnam(sys_get_temp_dir(), 'mirror').'.php';
        file_put_contents($file, $source);
        // escapeshellarg, not escapeshellcmd: Herd's PHP lives under a path with
        // a space in it, which escapeshellcmd does not quote.
        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file).' 2>&1', $output, $status);
        unlink($file);

        expect($status)->toBe(0, "{$resource} {$kind}: ".implode(' ', $output));
    }
})->with(['articlePrice', 'salesInvoice', 'opportunity', 'party', 'ticket']);
