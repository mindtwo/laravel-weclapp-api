# Laravel Weclapp API

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mindtwo/laravel-weclapp-api.svg?style=flat-square)](https://packagist.org/packages/mindtwo/laravel-weclapp-api)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/mindtwo/laravel-weclapp-api/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/mindtwo/laravel-weclapp-api/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/mindtwo/laravel-weclapp-api.svg?style=flat-square)](https://packagist.org/packages/mindtwo/laravel-weclapp-api)

A thin, typed Laravel client for the [Weclapp REST API v2](https://www.weclapp.com/api/v2.html).
It centralises authentication, pagination, retry and, for queued calls, rate
limiting, exposes a
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
  undispatched, rate-limited queue job; every answered or suppressed write emits a
  `WeclappApiCallCompleted` event.
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

The mirror-table migrations are **publish-only** (they must not collide with a
host app's own tables). Publish and run them only if you intend to use the mirror
models / sync commands.

**Pick the tables you actually read** — each entity has its own tag, so you are
not forced to take all nine:

```bash
php artisan vendor:publish --tag="weclapp-api-migrations-article-prices"
php artisan vendor:publish --tag="weclapp-api-migrations-articles"
php artisan migrate
```

| Tag suffix | Table |
| --- | --- |
| `parties` | `weclapp_parties` (customers and suppliers, flagged per row) |
| `article-categories` | `weclapp_article_categories` |
| `articles` | `weclapp_articles` |
| `users` | `weclapp_users` |
| `quotations` | `weclapp_quotations` |
| `sales-orders` | `weclapp_sales_orders` |
| `projects` | `weclapp_projects` |
| `article-prices` | `weclapp_article_prices` |
| `sales-invoices` | `weclapp_sales_invoices` |

Or take everything at once:

```bash
php artisan vendor:publish --tag="weclapp-api-migrations"
php artisan migrate
```

Mirror tables have no foreign-key constraints between them — they correlate on
`weclapp_id` — so publishing a subset is safe for the sync: run without an
entity argument, `weclapp:sync` and `weclapp:update` skip every entity whose
table is not migrated. The Eloquent relations are the exception: calling
`ArticlePrice::article()` queries `weclapp_articles`, so publish both sides of
any relation you read.

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
MINDTWO_WECLAPP_QUEUE_CONNECTION=         # connection for queued API-call jobs; empty = default
MINDTWO_WECLAPP_RATE_LIMIT_PER_MINUTE=100 # limit applied to queued API-call jobs
MINDTWO_WECLAPP_WRITES_ENABLED=           # blank = off on local/testing, on elsewhere; see "Write suppression"
MINDTWO_WECLAPP_VISIBLE_TAG=              # article tag mirrored as `visible`; empty = every article
MINDTWO_WECLAPP_LOG_EVENTS=false          # log every write's WeclappApiCallCompleted event
MINDTWO_WECLAPP_LOG_LEVEL=info
MINDTWO_WECLAPP_LOG_CHANNEL=              # empty = the default log channel
MINDTWO_WECLAPP_LOG_INCLUDE_PAYLOAD=false
```

The values shown are the defaults (for the writes switch, blank is the default).
Omit a line or leave it blank (`KEY=`) to keep its default.

Leave `MINDTWO_WECLAPP_TIMEZONE` unset or equal to `APP_TIMEZONE`. Mirrored dates
are built in this timezone, but Eloquent stores them as plain wall-clock values
and reads them back in `APP_TIMEZONE`, so any other value shifts every mirrored
date by the difference.

`MINDTWO_WECLAPP_VISIBLE_TAG` is evaluated when an article row is written, so
after changing it run a full `weclapp:sync articles` — `weclapp:update` only
revisits articles Weclapp reports as modified and would leave the rest with
the old `visible` flag.

## Usage

```php
use Mindtwo\LaravelWeclappApi\Facades\WeclappClient;
```

### Typed endpoints

Each entity accessor returns an endpoint object. Reads execute immediately:

```php
// Collections are fully paginated and merged into one Collection
$parties = WeclappClient::parties()->query(['company-eq' => 'ACME GmbH']);

// …or a single page, sorted by id unless you sort it yourself
$movements = WeclappClient::warehouseStockMovements()->query(['page' => 3, 'pageSize' => 500]);

// A collection too large to hold at once: walk it page by page by id cursor,
// holding one page in memory (the mirror sync reads this way)
WeclappClient::lazy('warehouseStockMovement')->each(fn (object $movement) => /* … */);

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

The view holds for `find()` and `create()` too: `customers()->find($id)` answers
`null` for a party that is not a customer, and `customers()->create($data)`
sets `customer: true` unless `$data` says otherwise. `update()` and `delete()`
address a party by id, whatever its roles.

### What the spec does not tell you about writes

The OpenAPI spec describes shape, not behaviour. Everything below was established
against a live tenant and costs real time to rediscover, so it is recorded here.

**Weclapp omits null fields from JSON entirely.** An absent key is indistinguishable
from a field that does not exist, which is why a sampled response is never evidence
that a field is unsupported — and why the trap below exists at all.

**A single-record GET is not envelope-wrapped.** Collections answer
`{"result": [...]}`; `GET /{resource}/id/{id}` returns the record itself.
`find()` accounts for this, `get()` unwraps the envelope.

**PUT is a full replace, and omitting a field counts as changing it.** This turns a
seemingly harmless partial update into a permission error. The spec's answer is
`ignoreMissingProperties=true`, which `partial: true` sends; include `version` so
Weclapp can refuse a stale write:

```php
// 403 — the payload omits articleType, which reads as changing it
WeclappClient::put('article', $id, ['shortDescription1' => 'x']);

// Changes only the given fields
WeclappClient::put('article', $id, ['version' => $v, 'shortDescription1' => 'x'], partial: true);
WeclappClient::articles()->update($id, ['version' => $v, 'shortDescription1' => 'x'], partial: true);

// The full-replace form: re-read, merge, send the whole record back
$record = (array) WeclappClient::find('article', $id);
WeclappClient::put('article', $id, [...$record, 'shortDescription1' => 'x']);
```

`PUT` and `DELETE` are retried after a `5xx` or a dropped connection. If the
first attempt went through and only its answer was lost, the retry can be
refused although the change was saved — a `version`-guarded `PUT` with a
conflict, a `DELETE` with a `404`. Re-read the record before treating such a
failure as lost.

**Refusals are field-level and come in two flavours**, neither of which is a plain
403 on the endpoint:

| Response | Meaning |
| --- | --- |
| `403 missing permissions for {field}` | the role may not write that field |
| `400 no authorization to access property or referenced entity` | same thing, different field |
| `400 validation failed` + `validationErrors[]` | a real content problem; read `location` and `detail` |

**A field can be required on write yet absent on read — and the absence may be a
permission, not a null.** `article` demands `primarySupplySourceId` as soon as
`supplySources` is non-empty. On one tenant that field was missing from **every** one
of 758 articles, which made 455 of them unwritable: read-modify-write could not satisfy
the validation, supplying a derived value was refused separately, and dropping the
collection was refused too.

It turned out Weclapp was **hiding** the field because the token lacked read access to
the `articleSupplySource` entity. Granting that access made the field appear on all 455
and every article writable. So an absent field can mean "null", "not supported", *or*
"you may not see this" — and only the third is fixable by asking. When a required field
is missing, check whether its referenced entity is readable before concluding the record
cannot be written.

**Nested collections do not share one rule.** On `article`, `articleImages` may be
emptied but `articlePrices` may not — removing a price is a delete, and a separate
permission. Test each collection rather than generalising from one.

**Some resources are GET-only at the HTTP level.** `articlePrice` answers `405` to
both `POST` and `DELETE`; prices exist only as a nested collection on the article, so
every price write goes through `PUT /article`. Whether you may *remove* one is a
separate permission from creating it — on one tenant deletion was refused while creation
was allowed, which made a wrongly created price removable only in the Weclapp UI. Do not
assume that being able to add implies being able to undo.

**Duplicate detection is content-level.** A second list price with the same scale and
sales channel is `400 validation failed`, not a permission problem. The same payload
on a free scale, or scoped to a customer, is accepted.

**`dryRun` genuinely does not persist** — verified on `article` and `party` by comparing
the record byte-for-byte before and after — but it is **not available everywhere**. The
spec documents it on every generic create, update and delete (`POST /{resource}`,
`PUT`/`DELETE /{resource}/id/{id}`), and on none of the action paths: `uploadArticleImage`
has no dry run at all, so an image write cannot be validated before it happens.

**`/system/permissions` is not a reliable account of what a token may do.** It returns
the catalogue of right names and disagrees with observed behaviour in both directions —
on one tenant it claimed read access to resources that answered `403`, and omitted one
that worked. Probe the operation you care about instead of reading the list.

### Writes and the lazy response proxy

`create()`, `update()` and `delete()` return a `LazyResponseProxy`. It behaves
like an `Illuminate\Http\Client\Response` when you read from it, executing the
request on first access — or, if nothing reads from it, when it goes out of
scope, so a write used as a plain statement still happens:

```php
$response = WeclappClient::quotations()->create($payload);

$id = $response->json()['id']; // executes here

WeclappClient::tickets()->delete(9); // executes at the end of the statement
```

A request sent that late has no caller left to throw to, so a failure — a
connection error or a refusal such as a `403` or `422` — is passed to the
application's exception handler (`report()`) instead.

The proxy is sent whenever it goes out of scope unread, also when that scope
ends because an exception was thrown after the proxy was created. Read the
response, or call `getJob()`, before running code that may fail and should
cancel the write.

Or hand back an undispatched, rate-limited job instead of executing inline:

```php
$job = WeclappClient::quotations()->create($payload)->getJob();

dispatch($job); // or batch several with Bus::batch([...])
```

A queued call is throttled by the `weclapp-api-jobs` rate limiter and keeps
being retried — after a throttle or a `429`, and for `PUT`/`DELETE` also after
a `5xx` or a connection error — for up to an hour from dispatch before it
fails. A `POST` that gets a `5xx` or no answer fails at once, as on the
synchronous path: Weclapp may already have created the record, and sending it
again could create a second one. A refusal (`4xx`) fails at once. A call whose
batch was cancelled is skipped. Retrying needs a real queue worker: on the
`sync` driver, or when the job is handled directly, nothing would run a
released job again, so every answer is final there.

The hour counts from dispatch, so a job delayed by more than an hour fails
before it sends anything. A job may run for the HTTP timeout plus the connect
timeout plus 5 seconds (75 s by default); keep the queue connection's
`retry_after` above that, or a second worker picks up a write that is still in
flight and sends it again.

Either path emits a `Mindtwo\LaravelWeclappApi\Events\WeclappApiCallCompleted`
event for every answer Weclapp sends, success or failure, so consumers can react
without threading return values through the call stack. A queued call reports
every answered attempt: an event whose `final` is `false` is a `429` or `5xx`
the job is about to retry, and the attempt that ends the call — success,
refusal, or budget exhausted — carries `final = true`.

A request that gets no answer at all (timeout, dropped connection) throws a
`ConnectionException`. It is reported as an event with status `0` only by the
proxy and by a queued job once it gives up; the low-level methods and a job
attempt it retries emit nothing for it.

A listener that throws on an answered request is reported to the exception
handler and never fails or re-runs the call. Listeners on a suppressed write or
a status-`0` event are not shielded that way, so keep them from throwing.

### Low-level client

Every documented resource has a typed class, so the generic methods are for what
falls outside that: undocumented resources, ad-hoc reads, and the ~190 nested
action paths. `get()`, `find()` and `count()` work on resource collections and
records; actions go through `post()`, `download()` or the raw `client()`:

```php
WeclappClient::get('article', ['articleNumber-eq' => 'A-1']); // paginated Collection
WeclappClient::find('unit', 5);                               // ?object
WeclappClient::count('article');                              // int
WeclappClient::post('quotation', $payload, dryRun: true);     // array
WeclappClient::put('article', 7, $payload);                   // array
WeclappClient::delete('ticket', 9);                           // bool
```

`get()` pages by id cursor (`sort=id`, `id-gt`), so it suits collections whose
records carry an `id`. A one-off action read that takes no paging parameters
goes through the raw client, which sends exactly what you give it:

```php
WeclappClient::client()->get('salesChannel/activeSalesChannels')->json('result');
```

`find()`, `put()` and `delete()` take the id separately because Weclapp
addresses a single record as `/{resource}/id/{id}` — a bare `/{resource}/{id}`
exists for no resource in the v2 API. Use `recordPath()` if you need to build
that path yourself, e.g. for a nested action:

```php
WeclappClient::post(WeclappClient::recordPath('salesOrder', 42).'/createCustomerReturn', []);
```

Binary sub-paths get their own two methods, because Weclapp handles files with
neither JSON nor multipart:

```php
// GET a binary; null on 404 so "there is no image" is not an exception
$response = WeclappClient::download("article/id/{$id}/downloadMainArticleImage");

// POST a binary. The bytes are the raw request body and the filename is a
// required `name` query parameter — it is NOT multipart/form-data.
WeclappClient::upload(
    "article/id/{$id}/uploadArticleImage",
    $bytes,
    'photo.jpg',
    'image/jpeg',
    ['mainImage' => 'true'],
);
```

`post()`, `put()` and `delete()` take a `dryRun` flag, which asks Weclapp to
validate the write without persisting it:

```php
WeclappClient::put('article', $id, $payload, dryRun: true);
WeclappClient::delete('article', $id, dryRun: true);
```

Note that write suppression covers a dry run too — it is still a write against
the live tenant — so check `writesSuppressed()` rather than reading meaning into
the empty response it returns.

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
php artisan weclapp:sync parties
php artisan weclapp:sync

# Incremental sync of records changed since a time (default: 24h ago)
php artisan weclapp:update articles --since="2026-01-01 00:00:00"
```

`weclapp:update` keeps no record of its last run: without `--since` it asks for
the past 24 hours, so a run that was skipped or failed leaves a gap that only
the next full `weclapp:sync` closes. Schedule one periodically.

`weclapp:update` writes only the records Weclapp reports as modified, so a
value derived in the mirror from configuration — the article `visible` flag
from `MINDTWO_WECLAPP_VISIBLE_TAG` — is only recomputed for those. After
changing the tag, run a full `weclapp:sync articles`.

Without an entity argument both commands run every supported entity in turn.
Entities whose table you did not publish are skipped, and an entity Weclapp
refuses (for example a `403` on an unlicensed module) is reported and does not
stop the ones after it; the command then exits non-zero so a scheduler notices.

Two runs of the same entity never overlap: both commands take the same cache
lock per entity, so a slow nightly `weclapp:sync` and an hourly
`weclapp:update` cannot write one mirror at once. An entity whose lock is held
is skipped with a warning and the run exits non-zero; other entities still
run. The lock expires after 24 hours, so a crashed run cannot block the mirror
for longer. It needs a cache store that supports locks (`file`, `database`,
`redis`, `memcached`, `dynamodb` or `array`).

Supported sync entities: `parties`, `article-categories`, `articles`, `users`,
`quotations`, `sales-orders`, `projects`, `article-prices`, `sales-invoices`.
Customers and suppliers are both `/party` records; the mirror keeps every party
in `weclapp_parties` with Weclapp's `customer`/`supplier` flags as
`is_customer`/`is_supplier`, and `Party::customers()` / `Party::suppliers()`
scope a query to one role.

> **`projects` is undocumented but real.** The `project` resource appears nowhere
> in the official OpenAPI v2 spec, yet it responds and is populated
> (live-confirmed). Because it is undocumented, Weclapp gives no compatibility
> guarantee for it, and `WeclappClient::projects()` offers reads only — no write
> has been verified against a tenant.

### What the sync layer covers, and what it leaves to you

A `SyncDefinition` copies one Weclapp record into one row of one mirror table:
scalar fields through its `map`, epoch-millisecond dates through `dates`, and a
single value from inside nested data through a `derive` closure. The shipped
definitions derive:

- `articles`: `main_image_id` / `main_image_filename` (the flagged entry of
  `articleImages`), `supply_source_count` (the size of `supplySources`),
  `active` (Weclapp's flag, `true` when absent), and `visible` (whether `tags`
  holds `MINDTWO_WECLAPP_VISIBLE_TAG`);
- `article-prices`: `reduction_type` / `reduction_value` (the first entry of
  `reductionAdditions`; a second one is logged, not mirrored);
- `parties`: `is_customer` / `is_supplier` (Weclapp's role flags).

**Reconciliation.** A full `weclapp:sync` soft-deletes the mirror rows Weclapp
no longer returns, for every entity, and reports how many it archived. It never
archives on `weclapp:update`, on a filtered run, or when the response carried no
record ids — each of those would make live rows look gone. Archived rows are
hidden from default Eloquent queries; read them with `withTrashed()`. A record
that comes back is restored onto its old row.

Left to the consuming application:

- **Whole nested collections** — a party's addresses and contacts, an order's
  items, bank accounts. Only the single values listed above are flattened.
- **Fan-out to several models from one response** — a definition targets exactly
  one model.
- **Cross-entity resolution** — resolving a foreign key by looking up another
  local table before saving.
- **Domain side effects** — a mirror row is passive data, not application state.

If you need any of these, use the client and typed endpoints directly and project
the response into your own schema. The mirror tables are for consumers that want a
faithful local copy of Weclapp; they are not a prerequisite for using the package.

### Mirrors for other entities

The shipped mirrors are a documented default, not a complete model of Weclapp:
an entity gets one when something reads it. For any other entity, read it
through its typed endpoint or the generic client and project the response into
your own schema. Adding a mirror is a change to this package — the
`weclapp:make-mirror` generator that scaffolds one from the vendored spec is
development tooling, present in a git checkout but not in a Composer install,
and is described in [CONTRIBUTING](CONTRIBUTING.md#adding-a-mirror-entity-or-sync-command).

## Upgrading from a pre-release build

Nothing has been tagged yet. These notes are for applications that installed
this package from its development branch before the first release; a fresh
install can skip them.

### Environment variables are `MINDTWO_WECLAPP_*` prefixed

Early builds read unprefixed `WECLAPP_*` names while `.env.example` documented
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
`MINDTWO_WECLAPP_API_KEY`. `MINDTWO_WECLAPP_QUEUE_API_CALLS` has no
replacement: a call is queued by asking its proxy for `->getJob()`.

**If you have published `config/weclapp-api.php`**, your copy takes precedence
over the package's, so nothing breaks until you re-publish — but the two have
silently diverged. Rename the `env()` keys in your published config as well,
otherwise re-publishing swaps in the new names and leaves your old `.env`
unread. `tests/Vendor/ConfigEnvNamesTest.php` pins the package config and
`.env.example` to each other.

### Migrations are published, not loaded automatically

Early builds loaded the mirror migrations from the package itself. They are now
published like any other package migrations, so an application on such a build
has no migration files of its own after the update. Publish them with
`php artisan vendor:publish --tag=weclapp-api-migrations`, or one table at a
time with the per-entity tags listed under [Installation](#installation).

### Mirror tables changed in place

The create migrations were edited rather than extended while unreleased. A
table published from an earlier build lacks:

- `deleted_at` on every table (every mirror model soft-deletes; a query on a
  table without the column fails);
- `weclapp_id` as `NOT NULL UNIQUE` (it was a nullable index);
- `weclapp_articles`: `visible`, `long_text`, `supply_source_count`,
  `primary_supply_source_id`, `main_image_id`, `main_image_filename`;
- `weclapp_article_prices`: `reduction_type`, `reduction_value`, and amounts
  as `decimal(18,5)` (`price` and `price_scale_value` were `decimal(12,4)`,
  `reduction_value` was `decimal(8,4)`);
- `gross_amount`/`net_amount` on `weclapp_quotations`, `weclapp_sales_orders`
  and `weclapp_sales_invoices` as `decimal(18,5)` instead of `decimal(12,2)`,
  cast to five places (`100.00000`) — format them for display, e.g. with
  `Number::currency()`;
- `weclapp_quotations`: `quotation_version`, the quotation's revision. `version`
  used to hold that revision and now holds Weclapp's lock counter, as on every
  other mirror — code reading `Quotation::$version` as the revision must move
  to `quotation_version`;
- `weclapp_parties`: `is_customer`, `is_supplier`;
- `weclapp_article_categories`: `last_modified`;
- `weclapp_sales_invoices.description` as `text` instead of `varchar(255)`;
- `weclapp_quotations.report_id` is gone, as are the `weclapp_amounts` and
  `weclapp_reports` tables with their `Amount` and `Report` models and
  `Quotation::report()` — none of them was a Weclapp resource.

A mirror holds no data of its own, so the straightforward path is to rebuild
the tables. The published files keep the package's names, which an earlier
build may already have recorded as run, so clear those records too — otherwise
`migrate` skips the re-published files and creates nothing:

1. drop the `weclapp_*` tables;
2. delete their rows from the `migrations` table, e.g.
   `DB::table('migrations')->where('migration', 'like', '%create_weclapp_%')->delete();`;
3. delete any previously published `*_create_weclapp_*` migration files;
4. re-publish (see above) and run `php artisan migrate`;
5. run a full `php artisan weclapp:sync`.

Writing alter migrations by hand works too; the list above is complete.

### Sync entities

`weclapp:sync customers` and `weclapp:sync suppliers` are now one
`weclapp:sync parties`; filter on `is_customer` / `is_supplier`, or use
`Party::customers()` / `Party::suppliers()`.

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
