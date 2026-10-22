<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Factories\Factory;
use Mindtwo\LaravelWeclappApi\Spec\SpecReader;
use Mindtwo\LaravelWeclappApi\Sync\SyncDefinition;
use Mindtwo\LaravelWeclappApi\Sync\SyncRegistry;

// The spec gives shape, never values, so an enum literal in a factory is easy to
// guess wrong — salesInvoice has no `OPEN` status, for one. A wrong literal still
// makes a row, so only checking against the spec catches it.

/**
 * The definition plus every state callable without arguments, sampled enough
 * times that a randomElement() over a handful of values shows each of them.
 *
 * @param Factory<Illuminate\Database\Eloquent\Model> $factory
 *
 * @return list<array<string, mixed>>
 */
function factorySamples(Factory $factory): array
{
    $variants = [$factory];

    foreach ((new ReflectionClass($factory))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->class === $factory::class && $method->name !== 'definition' && $method->getNumberOfRequiredParameters() === 0) {
            $variants[] = $method->invoke($factory);
        }
    }

    return array_merge(...array_map(fn (Factory $variant): array => $variant->count(50)->raw(), $variants));
}

it('seeds only enum values the spec allows', function (SyncDefinition $definition) {
    if (! in_array($definition->endpoint, SpecReader::resources(), true)) {
        $this->markTestSkipped("[{$definition->endpoint}] is absent from the spec; its values come from live reads.");
    }

    $properties = SpecReader::properties($definition->endpoint);
    $enums = [];

    foreach ($definition->map as $column => $field) {
        $allowed = SpecReader::enumValues($properties[$field] ?? []);

        if ($allowed !== null) {
            $enums[$column] = $allowed;
        }
    }

    $violations = [];

    foreach (factorySamples($definition->model::factory()) as $attributes) {
        foreach ($enums as $column => $allowed) {
            $value = $attributes[$column];

            if ($value !== null && ! in_array($value, $allowed, true)) {
                $violations[$column][$value] = $value;
            }
        }
    }

    expect($violations)->toBe([], "{$definition->model} seeds values outside the spec enum");
})->with(fn (): array => SyncRegistry::all());

it('finds enum columns to check', function () {
    // Guards the test above against passing vacuously if enum resolution breaks.
    $definition = SyncRegistry::all()['sales-invoices'];
    $properties = SpecReader::properties($definition->endpoint);

    expect(SpecReader::enumValues($properties['status']))->toContain('NEW')
        ->and(SpecReader::enumValues($properties['salesInvoiceType']))->toContain('STANDARD_INVOICE');
});
