# Laravel Weclapp API

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mindtwo/laravel-weclapp-api.svg?style=flat-square)](https://packagist.org/packages/mindtwo/laravel-weclapp-api)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/mindtwo/laravel-weclapp-api/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/mindtwo/laravel-weclapp-api/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/mindtwo/laravel-weclapp-api.svg?style=flat-square)](https://packagist.org/packages/mindtwo/laravel-weclapp-api)

A thin, typed Laravel client for the [Weclapp REST API v2](https://www.weclapp.com/api/v2.html).
It centralises authentication, pagination, retry and rate-limiting, exposes a
fluent per-entity endpoint API, and can mirror selected Weclapp entities into
local tables via ready-made sync commands.

Highlights:

- A single configured HTTP client (`WeclappClient`) with the `AuthenticationToken`
  header, automatic page-merging, timeouts and retry.
- Typed endpoint classes for the common sales/CRM entities, each with
  `query()` / `find()` / `count()` (reads) and `create()` / `update()` / `delete()`
  (writes). The generic client still reaches every one of Weclapp's ~150 endpoints.
- Writes return a lazy response proxy that runs synchronously **or** hands back an
  undispatched, rate-limited queue job; every call emits a `WeclappApiCallCompleted`
  event.
- Publishable `weclapp_*` mirror models/migrations and `weclapp:sync` /
  `weclapp:update` commands.
- A `writes_enabled` toggle that suppresses (and logs) mutating requests in
  local/testing without touching reads.

## Installation

```bash
composer require mindtwo/laravel-weclapp-api
```

Publish the config file:

```bash
php artisan vendor:publish --tag="weclapp-api-config"
```

The mirror-table migrations are **publish-only** (they are company-wide data and
must not collide with a host app's own tables). Publish and run them only if you
intend to use the mirror models / sync commands:

```bash
php artisan vendor:publish --tag="weclapp-api-migrations"
php artisan migrate
```

## Configuration

Set your Weclapp instance URL and personal API token in `.env`. Create a token
under *My settings → API* in Weclapp.

```
WECLAPP_URL="https://your-tenant.weclapp.com/webapp/api/v2/"
WECLAPP_TOKEN="your_weclapp_api_token"
```

Other supported variables (see [`config/weclapp-api.php`](config/weclapp-api.php)):

```
WECLAPP_PAGE_SIZE=1000            # records per page (Weclapp caps at 1000)
WECLAPP_TIMEZONE=UTC              # timezone for epoch-ms date conversion
WECLAPP_HTTP_TIMEOUT=60
WECLAPP_HTTP_CONNECT_TIMEOUT=10
WECLAPP_HTTP_RETRY_TIMES=3
WECLAPP_HTTP_RETRY_SLEEP=500
WECLAPP_QUEUE_CONNECTION=         # connection for queued API-call jobs
WECLAPP_RATE_LIMIT_PER_MINUTE=100 # limit applied to queued API-call jobs
WECLAPP_WRITES_ENABLED=           # see "Write suppression" below
WECLAPP_LOG_EVENTS=false          # log every WeclappApiCallCompleted event
```

## Usage

```php
use Mindtwo\LaravelWeclappApi\Facades\WeclappClient;
```

### Typed endpoints

Each entity accessor returns an endpoint object. Reads execute immediately:

```php
// Collections are fully paginated and merged into one Collection
$parties = WeclappClient::parties()->query(['company-eq' => 'ACME GmbH']);

// A single record, or null on 404
$article = WeclappClient::articles()->find(20001);

// A count
$open = WeclappClient::quotations()->count(['status-eq' => 'OPEN']);
```

Available typed accessors include `parties()`, `customers()`, `suppliers()`,
`projects()`, `articles()`, `articleCategories()`, `quotations()`,
`salesOrders()`, `users()`, plus common neighbours (`salesInvoices()`,
`purchaseOrders()`, `contracts()`, `opportunities()`, `units()`, `taxes()`,
`paymentMethods()`, `currencies()`, and more). See the `WeclappClient` facade
docblock for the full list.

### Writes and the lazy response proxy

`create()`, `update()` and `delete()` return a `LazyResponseProxy`. It behaves
like an `Illuminate\Http\Client\Response` when you read from it, executing the
request on first access:

```php
$response = WeclappClient::quotations()->create($payload);

$id = $response->json()['id']; // executes here
```

Or hand back an undispatched, rate-limited job instead of executing inline:

```php
$job = WeclappClient::quotations()->create($payload)->getJob();

dispatch($job); // or batch several with Bus::batch([...])
```

Either path emits a `Mindtwo\LaravelWeclappApi\Events\WeclappApiCallCompleted`
event on success and failure, so consumers can react without threading return
values through the call stack.

### Low-level client

For endpoints without a typed class, use the generic methods (they hit any
Weclapp resource):

```php
WeclappClient::get('salesChannel');                 // paginated Collection
WeclappClient::find('unit', 5);                      // ?object
WeclappClient::count('article');                     // int
WeclappClient::post('quotation', $payload, dryRun: true); // array
```

### Write suppression

Mutating requests (`POST`/`PUT`/`DELETE`) can be suppressed so no data leaves
your environment. When `weclapp-api.writes_enabled` is explicitly `false`, writes
are logged and skipped (returning a neutral response), while reads continue
normally. The default is live everywhere except the `local` and `testing`
environments; a missing flag never blocks production. Override with:

```
WECLAPP_WRITES_ENABLED=true
```

## Mirroring entities

The package ships Eloquent models and publishable migrations for
`weclapp_*` tables, plus two commands that pull data into them.

```bash
# Full sync of one entity, or omit the argument to sync all supported entities
php artisan weclapp:sync customers
php artisan weclapp:sync

# Incremental sync of records changed since a time (default: 24h ago)
php artisan weclapp:update articles --since="2026-01-01 00:00:00"
```

Supported sync entities: `customers`, `suppliers`, `article-categories`,
`articles`, `users`, `quotations`, `sales-orders`, `projects`. Customers and
suppliers are both stored in the unified `weclapp_parties` table.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [mindtwo GmbH](https://github.com/mindtwo)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
