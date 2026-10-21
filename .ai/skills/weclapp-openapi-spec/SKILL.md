---
name: weclapp-openapi-spec
description: >-
  Resolve Weclapp API facts from the vendored OpenAPI spec instead of guessing or
  sampling. Activate BEFORE writing any endpoint $path or $writes, any migration
  column type or length, any factory literal, any enum value, or any
  SyncRegistry field map in mindtwo/laravel-weclapp-api. Also activate when
  reading docs/specifications/weclapp-openapi_v2.{json,yaml}, when a schema looks
  empty, when deciding whether a field exists, or when someone proposes probing
  the live API to discover a data shape. Covers allOf/$ref resolution, enums as
  $refs, maxLength, the YAML-vs-JSON asymmetry, the absence of example bodies,
  and Weclapp's null-omission trap.
---

# Weclapp OpenAPI spec handling

The spec is the source of truth for **shape**: field names, types, lengths, enum
values, paths and write verbs. Resolve facts from it. Do not guess, and do not
infer shape from a sampled API response.

## Use the generator when scaffolding a mirror

`php artisan weclapp:make-mirror {resource}` already applies everything below --
allOf resolution, type and length mapping, enum-valid factory values, nested
collections skipped. Reach for it before hand-writing a migration/model/factory.
`--dry` previews, `--only=` trims. Its output is a starting point: trim columns
you will not read.

`Spec\SpecReader` and `Spec\MirrorBlueprint` expose the same resolution if you
need it directly -- do not re-implement the allOf walk.

## Before you write a literal, resolve it

Applies to endpoint `$path` / `$writes`, migration column types and lengths,
model `$fillable` and casts, factory values, and `SyncRegistry` maps.

Two failures have already shipped from skipping this:

- `sales_invoice_type => 'STANDARD'` — the enum value is `STANDARD_INVOICE`.
- `status => 'OPEN'` — not a `salesInvoice` status at all; valid values are
  `CANCELLED`, `DOCUMENT_CREATED`, `ENTRY_COMPLETED`, `NEW`, `OPEN_ITEM_CREATED`.

Both produced a green test suite and fixtures no real response could contain.
`distributionChannel` has 600 values; guessing is not viable.

## Schemas hide behind `allOf` — resolve recursively

`components.schemas.articlePrice.properties` is **empty**. The fields are behind
`$ref`s. Resolved, it has 16 properties.

```php
use Mindtwo\LaravelWeclappApi\Spec\SpecReader;

// The resource's own schema (via the GET-by-id response), allOf resolved.
$properties = SpecReader::properties(SpecReader::schemaFor('articlePrice'));
```

**Never conclude "the spec has no schemas" from an unresolved read.** That
mistake was made once and led to a wrong recommendation. All 152 resources
addressable as `/{resource}/id/{id}` resolve to a schema — 119 under their own
name, 29 lookup lists through the shared `customValue`, and the four
`shipmentReturn*` resources through `shipmentReturnDescription`. The schema a
resource uses is the `$ref` of its GET-by-id 200 response; `SpecReader::schemaFor()`
reads it, so never assume schema name equals resource name.

## Enums are `$ref`s, not inline

A property like `status` carries `{"$ref": "#/components/schemas/salesInvoiceStatusType"}`.
Follow the ref to get `enum`. Useful ones: `priceScaleType`,
`distributionChannel`, `salesInvoiceStatusType`, `paymentStatus`,
`salesInvoiceType`.

## Size columns from `maxLength`

It is documented per property. Use it rather than defaulting every string to
`string()`. Unbounded text fields (`recordFreeText`) want `text()`.

## Two spec files, not interchangeable

| | YAML | JSON |
|---|---|---|
| paths / schemas / responses | 698 / 442 / 154 | identical |
| `info.description` | 77k chars | **absent** |
| `tags` | 207 | **0** |

The JSON is a lossy conversion. **Read the YAML for behaviour** — filter
operators (`-eq`, `-in`, dot-notation predicates), the `properties=`
partial-response parameter, partial PUT via `ignoreMissingProperties` (there is
no PATCH) — **and the JSON for structure**
(it parses fast and the tests use it). `SpecFilesAgreeTest` guards the overlap.

## There are no example responses

Neither file has a single `example` or `examples` key. The spec gives shape,
never values. It cannot tell you what this tenant contains.

## When a live read IS the right tool

Live reads answer a different question: **which fields are populated, and how
densely.** They do not establish the schema, because **Weclapp omits null fields
from JSON entirely** — an absent key looks exactly like a nonexistent field. A
three-record `articlePrice` sample shows 12 fields; all 967 show 16, and the
missing four include `customerId`.

So: shape from the spec, distribution from a full read. Send
`serializeNulls=true` (documented on every collection read, not on `/id/{id}`)
so empty fields come back as `null` instead of missing; without it, pull the
whole collection or do not draw conclusions from a sample.

Practical notes for probing: base URL `https://<tenant>.weclapp.com/webapp/api/v2/` (the value of `MINDTWO_WECLAPP_URL`),
header `AuthenticationToken` (raw, no `Bearer`), responses are **gzipped** so
`curl` needs `--compressed`, single records are at `/{resource}/id/{id}`.
`bin/verify-live-paths.sh` holds the read-only probe. Never print or commit the
token.

## Spec and reality do drift, both ways

- `article.unitName` was in the field map, absent from real responses — removed.
- `project` responds and is populated but appears **nowhere** in the spec, so it
  is exempt from the spec-derived assertions (`UNDOCUMENTED` in
  `EndpointSpecConformanceTest`).

Treat access claims as dated observations, never fixed properties. The
sales/pricing domain was recorded as permanently `403` and later read fine; a
plan stayed closed on that stale evidence.

## Do not narrow `$writes` to match live permissions

`/meta/resources` and `/system/permissions` disagree in both directions and each
describes one tenant's licensing and one user's rights. `$writes` encodes the API
contract from the spec. Narrowing it breaks consumers with different rights.
Note `/meta/resources` keys are **path-prefixed** (`/article`, not `article`).
