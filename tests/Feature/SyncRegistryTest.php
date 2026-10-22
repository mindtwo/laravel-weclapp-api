<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Mindtwo\LaravelWeclappApi\Spec\SpecReader;
use Mindtwo\LaravelWeclappApi\Sync\SyncDefinition;
use Mindtwo\LaravelWeclappApi\Sync\SyncRegistry;

// A SyncDefinition names an API field on one side and a mirror column on the
// other, and nothing else ties the two to reality. Absent fields are written
// through as null, so a misspelled API field silently empties its column on
// every run; a column missing from $fillable is silently dropped by Eloquent.
// These pin every definition to the spec and to its table.

it('maps only API fields the spec documents', function (SyncDefinition $definition) {
    if (! in_array($definition->endpoint, SpecReader::resources(), true)) {
        $this->markTestSkipped("[{$definition->endpoint}] is absent from the spec; its fields come from live reads.");
    }

    $properties = SpecReader::properties($definition->endpoint);
    $unknown = [];

    foreach ([...$definition->map, ...$definition->dates] as $column => $field) {
        if (! isset($properties[$field])) {
            $unknown[$column] = $field;
        }
    }

    $notTimestamps = [];

    foreach ($definition->dates as $column => $field) {
        if (($properties[$field]['format'] ?? null) !== 'timestamp') {
            $notTimestamps[$column] = $field;
        }
    }

    expect($unknown)->toBe([], "{$definition->endpoint}: fields missing from the resolved schema")
        ->and($notTimestamps)->toBe([], "{$definition->endpoint}: date columns whose field is not a timestamp");
})->with(fn (): array => SyncRegistry::all());

// SQLite ignores varchar lengths, so a column shorter than the spec allows only
// fails on MySQL or PostgreSQL — mid-sync, on every run. SQLite does not report
// the declared length either, hence reading the create migration itself.
it('sizes every mapped string column for the longest value the spec allows', function (SyncDefinition $definition) {
    $properties = SpecReader::properties(SpecReader::schemaFor($definition->endpoint) ?? $definition->endpoint);
    $table = (new $definition->model)->getTable();
    $migration = (string) file_get_contents((string) glob(__DIR__."/../../database/migrations/*_create_{$table}_table.php")[0]);

    $tooShort = [];

    foreach ($definition->map as $column => $field) {
        $maxLength = $properties[$field]['maxLength'] ?? null;

        if (! is_int($maxLength) || ! preg_match("/->string\('{$column}'(?:,\s*(\d+))?\)/", $migration, $match)) {
            continue;
        }

        $length = (int) ($match[1] ?? 255);

        if ($length < $maxLength) {
            $tooShort[$column] = "{$length} < {$maxLength}";
        }
    }

    expect($tooShort)->toBe([], "{$table}: string columns shorter than the spec's maxLength");
})->with(fn (): array => SyncRegistry::all());

it('writes only columns the mirror table and model accept', function (SyncDefinition $definition) {
    $model = new $definition->model;
    $columns = array_keys([...$definition->map, ...$definition->dates, ...$definition->paths, ...$definition->derive, ...$definition->defaults]);

    $missingInTable = array_values(array_filter($columns, fn (string $column): bool => ! Schema::hasColumn($model->getTable(), $column)));
    $missingInFillable = array_values(array_diff($columns, $model->getFillable()));

    expect($missingInTable)->toBe([], "{$model->getTable()}: columns the definition writes but the table lacks")
        ->and($missingInFillable)->toBe([], "{$definition->model}: columns the definition writes but \$fillable omits")
        ->and(array_key_exists($definition->key, $definition->map))->toBeTrue("{$definition->endpoint}: the match key has to come from the API");
})->with(fn (): array => SyncRegistry::all());
