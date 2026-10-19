<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
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
    $expected = array_keys(SyncRegistry::all());

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

it('publishes one migration per table whichever tags are used, in any order', function () {
    // publishesMigrations() stamps each copy with the publish date while
    // vendor:publish checks for an existing file under the original name, so a
    // second tag, or the same tag a second later, copied the migration again and
    // migrate failed on the duplicate create. Published into a directory of its
    // own so parallel test processes never see the files.
    $dir = sys_get_temp_dir().'/weclapp-publish-'.uniqid();
    mkdir($dir.'/migrations', 0777, true);

    // The publish targets were computed at boot; point the app at the temp
    // directory and register the provider again with the registries cleared.
    foreach (['publishes', 'publishGroups', 'publishableMigrationPaths'] as $registry) {
        $property = new ReflectionProperty(ServiceProvider::class, $registry);
        $property->setValue(null, []);
    }
    app()->useDatabasePath($dir);
    app()->register(WeclappApiServiceProvider::class, force: true)->boot();

    try {
        Artisan::call('vendor:publish', ['--tag' => 'weclapp-api-migrations-articles']);
        $this->travel(2)->seconds();
        Artisan::call('vendor:publish', ['--tag' => 'weclapp-api-migrations']);
        $this->travel(2)->seconds();
        Artisan::call('vendor:publish', ['--tag' => 'weclapp-api-migrations-articles']);

        $published = array_map('basename', glob($dir.'/migrations/*.php') ?: []);
        sort($published);

        expect($published)->toBe(array_values(array_unique(array_values(migrationMap()))));
    } finally {
        foreach (glob($dir.'/migrations/*.php') ?: [] as $file) {
            unlink($file);
        }
        rmdir($dir.'/migrations');
        rmdir($dir);
    }
});
