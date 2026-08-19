# Sync scope: this package vs. cloudbase

Status: open question, no code change pending. Written while auditing the
package on 2026-08-19.

cloudbase requires this package (`mindtwo/laravel-weclapp-api: "@dev"` via a
local path repository) and still runs eight of its own `weclapp:*` commands.
This note records how the two sync layers actually relate, because the overlap
is narrower than the command names suggest.

## The two layers are not duplicates

The package's sync layer mirrors a Weclapp endpoint into a `weclapp_*` table of
its own, one row per remote record, driven declaratively by `SyncRegistry`:

```
WeclappClient -> SyncDefinition field map -> EntitySynchronizer -> weclapp_* mirror table
```

cloudbase's commands share only the first step. All eight import
`Mindtwo\LaravelWeclappApi\WeclappClient`, so the transport, auth, pagination
and rate limiting are already the package's. What they do next is different:
they project the response into **cloudbase's own domain models**, correlating on
`weclapp_*_id` columns that live on those models.

| cloudbase command | writes to (cloudbase domain models) |
| --- | --- |
| `weclapp:customers` | `Customer`, `Address`, `Contact`, `User` |
| `weclapp:updateCustomers` | `Customer`, `Address`, `Contact`, `User`, `Slot` |
| `weclapp:articles` | `ArticleTemplate`, `ArticleSideTemplate`, `Category` |
| `weclapp:updateArticles` | `Article`, `Category`, `Price` |
| `weclapp:categories` | `Category` |
| `weclapp:updateCategories` | `Category` |
| `weclapp:users` | `User` |
| `weclapp:updateUsers` | `User`, `Slot` |

No cloudbase command reads or writes a `weclapp_*` mirror table. The nominal
equivalences below are therefore *entity* overlaps, not drop-in replacements:

| cloudbase command | nominally equivalent package command |
| --- | --- |
| `weclapp:customers` / `weclapp:updateCustomers` | `weclapp:sync customers` / `weclapp:update customers` |
| `weclapp:articles` / `weclapp:updateArticles` | `weclapp:sync articles` / `weclapp:update articles` |
| `weclapp:categories` / `weclapp:updateCategories` | `weclapp:sync article-categories` / `weclapp:update article-categories` |
| `weclapp:users` / `weclapp:updateUsers` | `weclapp:sync users` / `weclapp:update users` |

## What cloudbase's commands do that `SyncRegistry` cannot

`SyncDefinition` maps flat scalar fields of one record into one row of one
table. Everything below is outside that model, which is why the commands are not
simply redundant:

- **Nested collections.** `weclapp:customers` walks a party's addresses and
  contacts into separate `Address` and `Contact` rows. `SyncRegistry`'s docblock
  names nested collections (addresses, contacts, bank accounts) as deliberately
  left to the consumer.
- **Fan-out to several models from one response.** One Weclapp article becomes an
  `ArticleTemplate` or an `ArticleSideTemplate` depending on its shape, plus
  `Category` and `Price` rows. A `SyncDefinition` targets exactly one model.
- **Cross-entity resolution.** Articles resolve their `Category` by
  `weclapp_category_id` before saving, so ordering between commands matters.
- **Domain side effects.** The user and customer commands touch `User` and `Slot`,
  i.e. application state (accounts, capacity) rather than a passive mirror.

## Which side should own what

- **Transport, auth, pagination, rate limiting, typed endpoints** — the package.
  Already true; cloudbase consumes `WeclappClient` directly.
- **Projection into cloudbase's domain schema** — cloudbase. The mapping is
  app-specific (its own tables, its own `Slot`/`Price` semantics) and does not
  generalise to other consumers.
- **`weclapp_*` mirror tables and `SyncRegistry`** — the package, for consumers
  that want a faithful local copy of Weclapp rather than a domain projection.
  cloudbase is not such a consumer.

## cloudbase has dropped part of the mirror schema

`2026_07_12_180000_drop_dead_weclapp_sales_subsystem` drops `weclapp_suppliers`,
`weclapp_projects`, `weclapp_quotations` (plus the now-removed
`weclapp_amounts`, `weclapp_reports`, and the app's `orders`), guarded to skip
any table that is non-empty.

This does not narrow the package. Its sync surface stays at all eight entities —
`customers`, `suppliers`, `article-categories`, `articles`, `users`,
`quotations`, `sales-orders`, `projects` — because the package is
general-purpose and one consumer dropping its copies does not bind the others.

## Open question

Should cloudbase migrate onto the package's sync layer?

Doing so is not a command swap. It would mean either

1. adopting the `weclapp_*` mirror tables as the source of truth and rewriting
   cloudbase's domain projections to read from them instead of from the API —
   two hops instead of one, but a clear boundary; or
2. extending the package so a `SyncDefinition` can express nested collections
   and multi-model fan-out — which risks turning a declarative field map into a
   general ETL framework, and would need a second consumer to justify it.

Neither is obviously right, and nothing is broken today: cloudbase already reuses
the part of the package that carries the real complexity (the HTTP client and the
typed endpoints). Recommendation is to leave the projections in cloudbase and
revisit only if a second consumer needs the same nesting.
