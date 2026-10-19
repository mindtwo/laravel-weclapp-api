<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

// docs/specifications holds the spec twice. Everything that validates endpoint
// classes reads the JSON, but the JSON is a lossy conversion of the YAML, so a
// re-vendoring that refreshes one file and not the other would leave the tests
// asserting against a stale contract without anything failing. These tests pin
// the parts that must stay identical — the structure $writes and the mirror
// columns are derived from — and document the parts that legitimately differ
// so nobody "fixes" them.

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

/**
 * The parts of a node the conversion legitimately changes, removed; keys sorted;
 * integral floats read as ints, because the YAML writes `maxLength: 1000.0`
 * where the JSON has `1000`.
 *
 * @param list<string> $drop
 */
function structural(mixed $node, array $drop): mixed
{
    if (is_float($node) && floor($node) === $node) {
        return (int) $node;
    }

    if (! is_array($node)) {
        return $node;
    }

    $out = [];

    foreach ($node as $key => $value) {
        if (is_string($key) && in_array($key, $drop, true)) {
            continue;
        }

        $out[$key] = structural($value, $drop);
    }

    if (array_keys($out) !== range(0, count($out) - 1)) {
        ksort($out);
    }

    return $out;
}

const PROSE = ['description', 'summary', 'title', 'example', 'externalDocs'];

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
    // info.version is the API major ("2"), the same for every v2 export; the
    // body comparisons below are what pin one export to the other.
    expect(yamlSpec()['openapi'])->toBe(jsonSpec()['openapi'])
        ->and(yamlSpec()['info']['version'])->toBe(jsonSpec()['info']['version']);
});

// Report only the difference. Asserting the arrays wholesale prints all 698
// paths or 442 schema names on failure, which buries the one that drifted.
it('agrees on every path, its verbs and its operations', function () {
    $yaml = yamlSpec()['paths'];
    $json = jsonSpec()['paths'];

    expect(array_values(array_diff(array_keys($yaml), array_keys($json))))->toBe([], 'paths only in the YAML')
        ->and(array_values(array_diff(array_keys($json), array_keys($yaml))))->toBe([], 'paths only in the JSON');

    // Tags and operation ids are documentation the conversion renames; the verbs
    // and everything under them ($writes, parameters, request and response
    // schemas) must match.
    $drop = [...PROSE, 'tags', 'operationId'];
    $drifted = [];

    foreach ($json as $path => $operations) {
        if (structural($operations, $drop) !== structural($yaml[$path], $drop)) {
            $drifted[] = $path;
        }
    }

    expect($drifted)->toBe([], 'paths whose operations differ between the files');
});

it('agrees on every component schema', function () {
    $yaml = yamlSpec()['components']['schemas'];
    $json = jsonSpec()['components']['schemas'];

    expect(array_values(array_diff(array_keys($yaml), array_keys($json))))->toBe([], 'schemas only in the YAML')
        ->and(array_values(array_diff(array_keys($json), array_keys($yaml))))->toBe([], 'schemas only in the JSON');

    // Enums, maxLength, formats, refs and the allOf chain are what the mirrors
    // and factories are built from.
    $drifted = [];

    foreach ($json as $name => $schema) {
        if (structural($schema, PROSE) !== structural($yaml[$name], PROSE)) {
            $drifted[] = $name;
        }
    }

    expect($drifted)->toBe([], 'schemas whose structure differs between the files');
});

it('agrees on every component parameter and response', function () {
    expect(structural(yamlSpec()['components']['parameters'], PROSE))
        ->toBe(structural(jsonSpec()['components']['parameters'], PROSE))
        ->and(yamlSpec()['components']['responses'])->toBe(jsonSpec()['components']['responses']);
});

it('keeps the prose documentation the JSON conversion drops', function () {
    // The JSON is generated from the YAML and loses info.description and tags.
    // That is expected -- but the YAML must keep them, because they are the only
    // record of the filter operators, `properties=` and partial PUT.
    expect(strlen((string) (yamlSpec()['info']['description'] ?? '')))->toBeGreaterThan(50000)
        ->and(yamlSpec()['tags'] ?? [])->not->toBeEmpty();
});
