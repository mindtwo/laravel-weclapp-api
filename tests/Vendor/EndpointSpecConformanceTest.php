<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Mindtwo\LaravelWeclappApi\Http\Endpoints\Endpoint;
use Mindtwo\LaravelWeclappApi\WeclappClient;

// There is one trivial endpoint class per Weclapp resource, so the risk is not
// logic but drift: a typo in $path, a resource that does not exist, a write
// method offered where the API has none, or a class with no accessor. These
// tests check every class against the vendored OpenAPI spec instead of a
// hand-maintained list.

// The `project` resource is absent from the spec entirely (no path, no schema)
// but responds live, so it is exempt from the spec-derived assertions.
const UNDOCUMENTED = ['project'];

function spec(): array
{
    static $spec;

    return $spec ??= json_decode(
        (string) file_get_contents(__DIR__.'/../../docs/specifications/weclapp-openapi_v2.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
}

/**
 * Resources the spec exposes as CRUD, i.e. addressable as /{resource}/id/{id}.
 *
 * @return list<string>
 */
function specResources(): array
{
    $resources = [];

    foreach (array_keys(spec()['paths']) as $path) {
        $segments = explode('/', trim($path, '/'));

        if (count($segments) === 3 && $segments[1] === 'id' && $segments[2] === '{id}') {
            $resources[$segments[0]] = true;
        }
    }

    return array_keys($resources);
}

/**
 * Write operations the spec documents for a resource.
 *
 * @return list<string>
 */
function specWrites(string $resource): array
{
    $paths = spec()['paths'];
    $writes = [];

    if (isset($paths['/'.$resource]['post'])) {
        $writes[] = 'create';
    }

    foreach (['put' => 'update', 'delete' => 'delete'] as $verb => $operation) {
        if (isset($paths['/'.$resource.'/id/{id}'][$verb])) {
            $writes[] = $operation;
        }
    }

    return $writes;
}

/**
 * Every concrete endpoint class, as class-string => instance.
 *
 * @return array<class-string<Endpoint>, Endpoint>
 */
function endpoints(): array
{
    $out = [];

    foreach (glob(__DIR__.'/../../src/Http/Endpoints/*.php') ?: [] as $file) {
        $class = 'Mindtwo\\LaravelWeclappApi\\Http\\Endpoints\\'.basename($file, '.php');

        if ($class !== Endpoint::class) {
            $out[$class] = app($class);
        }
    }

    return $out;
}

function endpointPath(Endpoint $endpoint): string
{
    return (string) (new ReflectionProperty($endpoint, 'path'))->getValue($endpoint);
}

it('has a class for every documented CRUD resource', function () {
    $covered = array_values(array_map(endpointPath(...), endpoints()));

    expect(array_values(array_diff(specResources(), $covered)))->toBe([]);
});

it('points every endpoint at a documented resource', function () {
    $undocumented = [];

    foreach (endpoints() as $class => $endpoint) {
        $path = endpointPath($endpoint);

        if (in_array($path, UNDOCUMENTED, true)) {
            continue;
        }

        if (! array_key_exists('/'.$path.'/id/{id}', spec()['paths'])) {
            $undocumented[] = class_basename($class).' => '.$path;
        }
    }

    expect($undocumented)->toBe([]);
});

it('offers exactly the write operations the spec documents', function () {
    $mismatched = [];

    foreach (endpoints() as $class => $endpoint) {
        $path = endpointPath($endpoint);

        if (in_array($path, UNDOCUMENTED, true)) {
            continue;
        }

        $expected = specWrites($path);

        if ($endpoint->writes() !== $expected) {
            $mismatched[] = sprintf(
                '%s: has [%s], spec says [%s]',
                class_basename($class),
                implode(',', $endpoint->writes()),
                implode(',', $expected),
            );
        }
    }

    expect($mismatched)->toBe([]);
});

it('exposes every endpoint through a client accessor and a facade annotation', function () {
    $client = new ReflectionClass(WeclappClient::class);
    $facade = (string) file_get_contents(__DIR__.'/../../src/Facades/WeclappClient.php');

    $accessorFor = [];

    foreach ($client->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        $type = (string) $method->getReturnType();

        if (str_starts_with($type, 'Mindtwo\\LaravelWeclappApi\\Http\\Endpoints\\')) {
            $accessorFor[$type] = $method->getName();
        }
    }

    $problems = [];

    foreach (array_keys(endpoints()) as $class) {
        if (! isset($accessorFor[$class])) {
            $problems[] = class_basename($class).': no accessor on WeclappClient';

            continue;
        }

        $annotation = '@method static '.class_basename($class).' '.$accessorFor[$class].'()';

        if (! str_contains($facade, $annotation)) {
            $problems[] = class_basename($class).': facade missing "'.$annotation.'"';
        }
    }

    expect($problems)->toBe([]);
});

it('registers every endpoint as a singleton in the service provider', function () {
    $registered = (new ReflectionClass(Mindtwo\LaravelWeclappApi\WeclappApiServiceProvider::class))
        ->getConstant('ENDPOINTS');

    expect(array_values(array_diff(array_keys(endpoints()), $registered)))->toBe([]);

    // Registration is what makes the accessors return a shared instance.
    foreach (array_keys(endpoints()) as $class) {
        expect(app($class))->toBe(app($class));
    }
});

it('queries the resource path each endpoint declares', function () {
    Http::fake(['*' => Http::response(['result' => []], 200)]);

    $wrong = [];

    foreach (endpoints() as $class => $endpoint) {
        $endpoint->query();

        $path = endpointPath($endpoint);
        $url = Http::recorded()->last()[0]->url();

        if (! str_contains($url, '/webapp/api/v2/'.$path.'?')) {
            $wrong[] = class_basename($class).' hit '.$url;
        }
    }

    expect($wrong)->toBe([]);
});

// Mirror factories seed enum-backed columns with literal strings. Those were
// guessed from live records once and two of them were wrong (STANDARD instead of
// STANDARD_INVOICE, OPEN instead of a real salesInvoice status). The spec lists
// the valid values, so pin them rather than trusting a sample.
it('seeds mirror factories with values the spec allows', function (string $factory, string $column, string $schema) {
    $value = (new $factory)->definition()[$column];
    $enum = spec()['components']['schemas'][$schema]['enum'] ?? null;

    expect($enum)->toBeArray("spec schema {$schema} has no enum")
        ->and($enum)->toContain($value);
})->with([
    [Mindtwo\LaravelWeclappApi\Database\Factories\ArticlePriceFactory::class, 'price_scale_type', 'priceScaleType'],
    [Mindtwo\LaravelWeclappApi\Database\Factories\ArticlePriceFactory::class, 'sales_channel', 'distributionChannel'],
    [Mindtwo\LaravelWeclappApi\Database\Factories\SalesInvoiceFactory::class, 'sales_channel', 'distributionChannel'],
    [Mindtwo\LaravelWeclappApi\Database\Factories\SalesInvoiceFactory::class, 'sales_invoice_type', 'salesInvoiceType'],
    [Mindtwo\LaravelWeclappApi\Database\Factories\SalesInvoiceFactory::class, 'status', 'salesInvoiceStatusType'],
    [Mindtwo\LaravelWeclappApi\Database\Factories\SalesInvoiceFactory::class, 'payment_status', 'paymentStatus'],
]);
