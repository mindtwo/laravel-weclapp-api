<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Mindtwo\LaravelWeclappApi\Sync\SyncRegistry;
use Mindtwo\LaravelWeclappApi\WeclappApiServiceProvider;

// The per-entity publish tags are driven by a hand-written filename map, so the
// risk is drift: a renamed or added migration silently leaves a tag pointing at
// a file that no longer exists, and `vendor:publish` then copies nothing without
// erroring. These tests pin the map to what is actually on disk.

/**
 * @return array<string, string>
 */
function migrationMap(): array
{
    $property = new ReflectionClassConstant(WeclappApiServiceProvider::class, 'MIGRATIONS');

    /** @var array<string, string> */
    return $property->getValue();
}

function migrationDir(): string
{
    return __DIR__.'/../../database/migrations';
}

it('points every publish tag at a migration that exists', function () {
    foreach (migrationMap() as $tag => $file) {
        expect(file_exists(migrationDir().'/'.$file))
            ->toBeTrue("tag weclapp-api-migrations-{$tag} points at missing file {$file}");
    }
});

it('covers every migration with a publish tag', function () {
    $onDisk = array_map(
        'basename',
        glob(migrationDir().'/*.php') ?: [],
    );

    expect(array_values(array_diff($onDisk, array_values(migrationMap()))))
        ->toBe([], 'migration file has no per-entity publish tag');
});

it('registers a per-entity tag for every sync entity', function () {
    // customers and suppliers both mirror into weclapp_parties, so they share
    // the `parties` tag rather than getting one each.
    $entities = array_keys(SyncRegistry::all());
    $expected = array_values(array_unique(array_map(
        fn (string $entity): string => in_array($entity, ['customers', 'suppliers'], true) ? 'parties' : $entity,
        $entities,
    )));

    sort($expected);
    $tags = array_keys(migrationMap());
    sort($tags);

    expect($tags)->toBe($expected);
});

it('exposes each tag through the publish group registry', function () {
    $groups = ServiceProvider::publishableGroups();

    expect($groups)->toContain('weclapp-api-migrations');

    foreach (array_keys(migrationMap()) as $tag) {
        expect($groups)->toContain('weclapp-api-migrations-'.$tag);
    }
});
