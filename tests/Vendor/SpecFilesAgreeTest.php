<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

// docs/specifications holds the spec twice. Everything that validates endpoint
// classes reads the JSON, but the JSON is a lossy conversion of the YAML, so a
// re-vendoring that refreshes one file and not the other would leave the tests
// asserting against a stale contract without anything failing. These tests pin
// the parts that must stay identical, and document the parts that legitimately
// differ so nobody "fixes" them.

// symfony/yaml is present transitively rather than declared, so the parsing
// tests skip rather than fatal if a dependency update drops it. Promote it to
// require-dev if these checks should be mandatory.
function yamlParserMissing(): bool
{
    return ! class_exists(Yaml::class);
}

function specDir(): string
{
    return __DIR__.'/../../docs/specifications';
}

/**
 * @return array<string, mixed>
 */
function jsonSpec(): array
{
    static $spec;

    /** @var array<string, mixed> */
    return $spec ??= json_decode(
        (string) file_get_contents(specDir().'/weclapp-openapi_v2.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
}

/**
 * @return array<string, mixed>
 */
function yamlSpec(): array
{
    static $spec;

    /** @var array<string, mixed> */
    return $spec ??= Yaml::parseFile(specDir().'/weclapp-openapi_v2.yaml');
}

it('vendors both spec formats', function () {
    expect(file_exists(specDir().'/weclapp-openapi_v2.json'))->toBeTrue()
        ->and(file_exists(specDir().'/weclapp-openapi_v2.yaml'))->toBeTrue();
});

it('has no example bodies in either file', function () {
    // Documents the limitation the whole validation strategy rests on: the spec
    // gives shape, never values. If a future spec version adds examples, this
    // fails and the CONTRIBUTING guidance should be revisited.
    foreach (['weclapp-openapi_v2.json', 'weclapp-openapi_v2.yaml'] as $file) {
        $raw = (string) file_get_contents(specDir().'/'.$file);

        expect(preg_match('/^\s*"?examples?"?\s*:/m', $raw))
            ->toBe(0, "{$file} now contains example bodies");
    }
});

it('vendors the same spec version in both files', function () {
    expect(yamlSpec()['openapi'])->toBe(jsonSpec()['openapi'])
        ->and(yamlSpec()['info']['version'])->toBe(jsonSpec()['info']['version']);
})->skip(yamlParserMissing(...), 'symfony/yaml not installed');

// Report only the difference. Asserting the arrays wholesale prints all 698
// paths or 442 schema names on failure, which buries the one that drifted.
it('agrees on every path', function () {
    $yaml = array_keys(yamlSpec()['paths']);
    $json = array_keys(jsonSpec()['paths']);

    expect(array_values(array_diff($yaml, $json)))->toBe([], 'paths only in the YAML')
        ->and(array_values(array_diff($json, $yaml)))->toBe([], 'paths only in the JSON');
})->skip(yamlParserMissing(...), 'symfony/yaml not installed');

it('agrees on every component schema', function () {
    $yaml = array_keys(yamlSpec()['components']['schemas']);
    $json = array_keys(jsonSpec()['components']['schemas']);

    expect(array_values(array_diff($yaml, $json)))->toBe([], 'schemas only in the YAML')
        ->and(array_values(array_diff($json, $yaml)))->toBe([], 'schemas only in the JSON');
})->skip(yamlParserMissing(...), 'symfony/yaml not installed');

it('agrees on every component response', function () {
    expect(yamlSpec()['components']['responses'])->toBe(jsonSpec()['components']['responses']);
})->skip(yamlParserMissing(...), 'symfony/yaml not installed');

it('keeps the prose documentation the JSON conversion drops', function () {
    // The JSON is generated from the YAML and loses info.description and tags.
    // That is expected -- but the YAML must keep them, because they are the only
    // record of the filter-operator, `properties=` and PATCH semantics.
    expect(strlen((string) (yamlSpec()['info']['description'] ?? '')))->toBeGreaterThan(50000)
        ->and(yamlSpec()['tags'] ?? [])->not->toBeEmpty();
})->skip(yamlParserMissing(...), 'symfony/yaml not installed');
