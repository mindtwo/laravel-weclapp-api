<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Spec;

use RuntimeException;

/**
 * Reads the vendored OpenAPI spec.
 *
 * Schemas are composed with `allOf` + `$ref`, so a direct read of
 * `components.schemas.<x>.properties` is empty for most resources — `articlePrice`
 * reports 0 properties unresolved and 16 resolved. Everything that derives facts
 * from the spec goes through here so that resolution is written once.
 */
class SpecReader
{
    /** @var array<string, mixed>|null */
    private static ?array $spec = null;

    public static function path(): string
    {
        return __DIR__.'/../../docs/specifications/weclapp-openapi_v2.json';
    }

    /**
     * Whether the vendored spec is present.
     *
     * `.gitattributes` export-ignores /docs, so a `composer require` install has
     * no spec. Anything spec-derived is therefore development-only tooling and
     * has to check before assuming the file is there.
     */
    public static function available(): bool
    {
        return is_file(self::path());
    }

    /**
     * @return array<string, mixed>
     */
    public static function spec(): array
    {
        if (self::$spec !== null) {
            return self::$spec;
        }

        $path = self::path();

        if (! is_file($path)) {
            throw new RuntimeException(
                'The vendored OpenAPI spec is missing. It lives under docs/, which is '
                .'export-ignored, so it is only present in a git checkout of the package '
                ."— not in a composer install. Expected it at {$path}."
            );
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        return self::$spec = $decoded;
    }

    /**
     * Every property of a schema, with `allOf`/`$ref` composition flattened.
     *
     * @param array<string, true> $seen guards against recursive $refs
     *
     * @return array<string, array<string, mixed>>
     */
    public static function properties(string $schema, array $seen = []): array
    {
        if (isset($seen[$schema])) {
            return [];
        }

        $seen[$schema] = true;

        /** @var array<string, array<string, mixed>> $schemas */
        $schemas = self::spec()['components']['schemas'] ?? [];
        $definition = $schemas[$schema] ?? [];
        $properties = [];

        foreach ($definition['allOf'] ?? [] as $part) {
            $properties += isset($part['$ref'])
                ? self::properties(self::refName($part['$ref']), $seen)
                : ($part['properties'] ?? []);
        }

        return $properties + ($definition['properties'] ?? []);
    }

    /**
     * The allowed values of an enum-typed property, or null if it is not an enum.
     *
     * Enums are `$ref`s to standalone schemas rather than inline lists, so
     * `salesInvoice.status` has to be followed to `salesInvoiceStatusType`.
     *
     * @param array<string, mixed> $property
     *
     * @return list<string>|null
     */
    public static function enumValues(array $property): ?array
    {
        if (isset($property['enum'])) {
            /** @var list<string> */
            return $property['enum'];
        }

        if (! isset($property['$ref'])) {
            return null;
        }

        /** @var array<string, array<string, mixed>> $schemas */
        $schemas = self::spec()['components']['schemas'] ?? [];
        $target = $schemas[self::refName($property['$ref'])] ?? [];

        /** @var list<string>|null */
        return $target['enum'] ?? null;
    }

    /**
     * Resource names that are addressable as /{resource}/id/{id}.
     *
     * @return list<string>
     */
    public static function resources(): array
    {
        $resources = [];

        foreach (array_keys(self::spec()['paths'] ?? []) as $path) {
            $segments = explode('/', trim((string) $path, '/'));

            if (count($segments) === 3 && $segments[1] === 'id') {
                $resources[$segments[0]] = true;
            }
        }

        return array_keys($resources);
    }

    private static function refName(string $ref): string
    {
        $parts = explode('/', $ref);

        return end($parts);
    }
}
