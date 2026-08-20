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
- A typed endpoint class for **every** resource the Weclapp v2 API documents (152
  of them), each with `query()` / `find()` / `count()` (reads) and
  `create()` / `update()` / `delete()` (writes, where the API offers them).
  The generic client still reaches anything else, including nested actions.
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
MINDTWO_WECLAPP_URL="https://your-tenant.weclapp.com/webapp/api/v2/"
MINDTWO_WECLAPP_API_KEY="your_weclapp_api_token"
```

Other supported variables (see [`config/weclapp-api.php`](config/weclapp-api.php)):

```
MINDTWO_WECLAPP_PAGE_SIZE=1000            # records per page (Weclapp caps at 1000)
MINDTWO_WECLAPP_TIMEZONE=UTC              # timezone for epoch-ms date conversion
MINDTWO_WECLAPP_HTTP_TIMEOUT=60
MINDTWO_WECLAPP_HTTP_CONNECT_TIMEOUT=10
MINDTWO_WECLAPP_HTTP_RETRY_TIMES=3
MINDTWO_WECLAPP_HTTP_RETRY_SLEEP=500
MINDTWO_WECLAPP_QUEUE_CONNECTION=         # connection for queued API-call jobs
MINDTWO_WECLAPP_RATE_LIMIT_PER_MINUTE=100 # limit applied to queued API-call jobs
MINDTWO_WECLAPP_WRITES_ENABLED=           # see "Write suppression" below
MINDTWO_WECLAPP_LOG_EVENTS=false          # log every WeclappApiCallCompleted event
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

There is an accessor for every documented resource — from the everyday
`parties()`, `articles()`, `quotations()`, `salesOrders()`, `users()` through to
`tickets()`, `warehouseStocks()`, `productionOrders()`, `timeRecords()` and
`customAttributeDefinitions()`. See the `WeclappClient` facade docblock for the
full list.

Whether your Weclapp plan and API user can actually reach a given resource is a
separate question: an unlicensed module or a restricted token answers `403`. The
class existing does not imply access.

### Read-only and partially writable resources

Reads work on every resource, but writes do not. Roughly a fifth of the API is
read-only (reports and system-maintained lists such as `salesOpenItems()` or
`warehouseStocks()`), and a handful support only some verbs — `users()` has no
delete, `documents()` has no create. Each class declares what the spec
documents, and an unsupported call fails immediately rather than going out as a
request Weclapp would reject:

```php
WeclappClient::salesOpenItems()->query();    // fine
WeclappClient::salesOpenItems()->create([]); // BadMethodCallException

WeclappClient::tickets()->writes();          // ['create', 'update', 'delete']
WeclappClient::salesOpenItems()->writes();   // []
```

Weclapp has no separate customer or supplier resource — both are parties
carrying a boolean flag. `customers()` and `suppliers()` are therefore filtered
views of `/party`, and any filter you pass is merged on top:

```php
WeclappClient::customers()->query(['company-eq' => 'ACME GmbH']);
// GET /party?customer-eq=true&company-eq=ACME+GmbH
```

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

Every documented resource has a typed class, so the generic methods are for what
falls outside that: the undocumented corners of the API, and the ~190 nested
action paths. They hit any resource:

```php
WeclappClient::get('salesChannel');                       // paginated Collection
WeclappClient::find('unit', 5);                           // ?object
WeclappClient::count('article');                          // int
WeclappClient::post('quotation', $payload, dryRun: true); // array
WeclappClient::put('warehouseStock', 7, $payload);        // array
WeclappClient::delete('ticket', 9);                       // bool
```

`find()`, `put()` and `delete()` take the id separately because Weclapp
addresses a single record as `/{resource}/id/{id}` — a bare `/{resource}/{id}`
exists for no resource in the v2 API. Use `recordPath()` if you need to build
that path yourself, e.g. for a nested action:

```php
WeclappClient::post(WeclappClient::recordPath('salesOrder', 42).'/createCustomerReturn', []);
```

### Write suppression

Mutating requests (`POST`/`PUT`/`DELETE`) can be suppressed so no data leaves
your environment. When `weclapp-api.writes_enabled` is explicitly `false`, writes
are logged and skipped (returning a neutral response), while reads continue
normally. The default is live everywhere except the `local` and `testing`
environments; a missing flag never blocks production. Override with:

```
MINDTWO_WECLAPP_WRITES_ENABLED=true
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
suppliers are both read from `/party` (filtered on the respective flag) and
stored in the unified `weclapp_parties` table.

> **`projects` is undocumented but real.** The `project` resource appears nowhere
> in the official OpenAPI v2 spec, yet it responds and is populated
> (live-confirmed). Because it is undocumented, Weclapp gives no compatibility
> guarantee for it.

### What the sync layer does not cover

A `SyncDefinition` maps the flat scalar fields of one Weclapp record into one row
of one mirror table. That is the whole model, and it is deliberate. The following
are **left to the consuming application**:

- **Nested collections** — a party's addresses and contacts, an order's items,
  bank accounts. These stay in the API response; the mirror does not unpack them.
- **Fan-out to several models from one response** — a definition targets exactly
  one model.
- **Cross-entity resolution** — resolving a foreign key by looking up another
  local table before saving.
- **Domain side effects** — a mirror row is passive data, not application state.
- **Derived data** — anything computed from Weclapp records rather than returned
  by Weclapp.
- **Reconciliation** — the sync is additive and upserts by weclapp id. It does not
  prune rows that were deleted or archived remotely.

If you need any of these, use the client and typed endpoints directly and project
the response into your own schema. The mirror tables are for consumers that want a
faithful local copy of Weclapp; they are not a prerequisite for using the package.

## Testing

```bash
composer test
```

## Upgrading

### Environment variables are now `MINDTWO_WECLAPP_*` prefixed

Every setting is read from a `MINDTWO_WECLAPP_`-prefixed variable. Previously the
config read unprefixed `WECLAPP_*` names while `.env.example` already documented
the prefixed ones, so a token placed under the documented name resolved to an
empty string and requests went out unauthenticated. Rename the following in your
`.env`:

| Old | New |
| --- | --- |
| `WECLAPP_URL` | `MINDTWO_WECLAPP_URL` |
| `WECLAPP_TOKEN` | `MINDTWO_WECLAPP_API_KEY` |
| `WECLAPP_PAGE_SIZE` | `MINDTWO_WECLAPP_PAGE_SIZE` |
| `WECLAPP_TIMEZONE` | `MINDTWO_WECLAPP_TIMEZONE` |
| `WECLAPP_WRITES_ENABLED` | `MINDTWO_WECLAPP_WRITES_ENABLED` |
| `WECLAPP_HTTP_TIMEOUT` | `MINDTWO_WECLAPP_HTTP_TIMEOUT` |
| `WECLAPP_HTTP_CONNECT_TIMEOUT` | `MINDTWO_WECLAPP_HTTP_CONNECT_TIMEOUT` |
| `WECLAPP_HTTP_RETRY_TIMES` | `MINDTWO_WECLAPP_HTTP_RETRY_TIMES` |
| `WECLAPP_HTTP_RETRY_SLEEP` | `MINDTWO_WECLAPP_HTTP_RETRY_SLEEP` |
| `WECLAPP_QUEUE_CONNECTION` | `MINDTWO_WECLAPP_QUEUE_CONNECTION` |
| `WECLAPP_RATE_LIMIT_PER_MINUTE` | `MINDTWO_WECLAPP_RATE_LIMIT_PER_MINUTE` |
| `WECLAPP_LOG_EVENTS` | `MINDTWO_WECLAPP_LOG_EVENTS` |
| `WECLAPP_LOG_LEVEL` | `MINDTWO_WECLAPP_LOG_LEVEL` |
| `WECLAPP_LOG_CHANNEL` | `MINDTWO_WECLAPP_LOG_CHANNEL` |
| `WECLAPP_LOG_INCLUDE_PAYLOAD` | `MINDTWO_WECLAPP_LOG_INCLUDE_PAYLOAD` |

Note that `WECLAPP_TOKEN` is not simply prefixed — it becomes
`MINDTWO_WECLAPP_API_KEY`.

**If you have published `config/weclapp-api.php`**, your copy takes precedence
over the package's, so nothing breaks until you re-publish — but the two have
silently diverged. Rename the `env()` keys in your published config as well,
otherwise re-publishing swaps in the new names and leaves your old `.env`
unread. `tests/Vendor/ConfigEnvNamesTest.php` pins the package config and
`.env.example` to each other.

### `Amount` and `Report` have been removed

The `Amount` and `Report` models, their factories and their migrations are gone,
along with `Quotation::report()`. Neither was a Weclapp resource — no endpoint,
no entry in the OpenAPI v2 spec — and `SyncRegistry` never populated them, so
`weclapp_amounts` and `weclapp_reports` could not receive a row. If you
published the migrations, drop both tables.

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
