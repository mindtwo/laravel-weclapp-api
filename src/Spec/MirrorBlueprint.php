<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Spec;

use Illuminate\Support\Str;

/**
 * Turns a resolved OpenAPI schema into the column plan for a mirror table.
 *
 * A SyncDefinition maps flat scalars of one record into one row, so nested
 * collections and object references are dropped here rather than guessed at —
 * they are the consumer's job. Every decision is derived from the spec: types
 * from `type`/`format`, string lengths from `maxLength`, factory values from the
 * enum a property `$ref`s.
 */
class MirrorBlueprint
{
    /** @param  list<array<string, mixed>>  $columns */
    private function __construct(
        public readonly string $resource,
        public readonly array $columns,
        /** @var array<string, string> */
        public readonly array $skipped,
    ) {}

    public static function for(string $resource): self
    {
        $columns = [];
        $skipped = [];

        foreach (SpecReader::properties($resource) as $name => $property) {
            $column = self::column($name, $property);

            if (is_string($column)) {
                $skipped[$name] = $column;

                continue;
            }

            $columns[] = $column;
        }

        usort($columns, fn (array $a, array $b): int => [$b['is_id'], $a['name']] <=> [$a['is_id'], $b['name']]);

        return new self($resource, $columns, $skipped);
    }

    /**
     * The same blueprint narrowed to a chosen set of API fields. `id` is always
     * kept — without weclapp_id the synchronizer has nothing to match on.
     *
     * @param list<string> $fields
     */
    public static function filtered(self $blueprint, array $fields): self
    {
        $keep = array_merge($fields, ['id']);

        $columns = array_values(array_filter(
            $blueprint->columns,
            fn (array $column): bool => in_array($column['source'], $keep, true),
        ));

        return new self($blueprint->resource, $columns, $blueprint->skipped);
    }

    public function table(): string
    {
        return 'weclapp_'.Str::snake(Str::pluralStudly(ucfirst($this->resource)));
    }

    public function modelClass(): string
    {
        return ucfirst($this->resource);
    }

    /**
     * Columns filled from a plain field, keyed column => API field.
     *
     * @return array<string, string>
     */
    public function map(): array
    {
        $map = [];

        foreach ($this->columns as $column) {
            if (! $column['is_date']) {
                $map[$column['name']] = $column['source'];
            }
        }

        ksort($map);

        return $map;
    }

    /**
     * Columns filled from an epoch-millisecond field, keyed column => API field.
     *
     * @return array<string, string>
     */
    public function dates(): array
    {
        $dates = [];

        foreach ($this->columns as $column) {
            if ($column['is_date']) {
                $dates[$column['name']] = $column['source'];
            }
        }

        ksort($dates);

        return $dates;
    }

    /**
     * @param array<string, mixed> $property
     *
     * @return array<string, mixed>|string a column definition, or the reason it was skipped
     */
    private static function column(string $name, array $property): array|string
    {
        $type = $property['type'] ?? null;

        if ($type === 'array') {
            return 'nested collection';
        }

        if ($type === 'object') {
            return 'nested object';
        }

        $enum = SpecReader::enumValues($property);

        if ($type === null && isset($property['$ref']) && $enum === null) {
            return 'reference to another entity';
        }

        // The API's own id becomes weclapp_id; the table keeps its own key.
        $column = $name === 'id' ? 'weclapp_id' : Str::snake($name);
        $format = $property['format'] ?? null;
        $maxLength = $property['maxLength'] ?? null;

        // Weclapp serialises every numeric id and the optimistic-lock counter as
        // a string, so type alone would give varchar columns for integers.
        $isIdentifier = $name === 'id' || str_ends_with($name, 'Id');

        [$migration, $cast] = match (true) {
            $isIdentifier                                  => ['unsignedBigInteger', 'integer'],
            $name === 'version'                            => ['integer', 'integer'],
            $type === 'integer' && $format === 'timestamp' => ['datetime', 'datetime'],
            $type === 'integer'                            => ['integer', 'integer'],
            $type === 'boolean'                            => ['boolean', 'boolean'],
            $type === 'number' || $format === 'decimal'    => ['decimal', 'decimal:4'],
            $enum !== null                                 => ['string', null],
            is_int($maxLength) && $maxLength <= 255        => ['string', null],
            default                                        => ['text', null],
        };

        return [
            'name'       => $column,
            'source'     => $name,
            'migration'  => $migration,
            'cast'       => $cast,
            'enum'       => $enum,
            'max_length' => is_int($maxLength) && $maxLength <= 255 && $maxLength !== 255 ? $maxLength : null,
            'is_date'    => $migration === 'datetime',
            'is_id'      => $isIdentifier,
        ];
    }
}
