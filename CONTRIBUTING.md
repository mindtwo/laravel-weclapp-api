# Contributing to Laravel Weclapp API

Thank you for considering contributing to the Laravel Weclapp API package! This
guide will help you understand how to contribute effectively to this project.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [How Can I Contribute?](#how-can-i-contribute)
- [Development Setup](#development-setup)
- [Coding Standards](#coding-standards)
- [Adding New Endpoints](#adding-new-endpoints)
- [Which entities have a mirror, and why](#which-entities-have-a-mirror-and-why)
- [Adding a mirror entity or sync command](#adding-a-mirror-entity-or-sync-command)
- [Testing](#testing)
- [Submitting Changes](#submitting-changes)
- [Reporting Issues](#reporting-issues)

## Code of Conduct

This project follows a code of conduct to ensure a welcoming environment for all
contributors. By participating, you are expected to:

- Be respectful and inclusive
- Accept constructive criticism gracefully
- Focus on what is best for the community
- Show empathy towards other community members

You can see the Code of Conduct in detail [here](CODE_OF_CONDUCT.md).

## How Can I Contribute?

There are many ways to contribute to this project:

### 1. Report Bugs

If you find a bug, please create an issue with:

- A clear, descriptive title
- Steps to reproduce the problem
- Expected behavior vs. actual behavior
- Your environment (PHP version, Laravel version, package version)
- Any error messages or stack traces

### 2. Suggest Enhancements

Have an idea for a new feature or improvement? Create an issue describing:

- The problem you're trying to solve
- Your proposed solution
- Any alternative solutions you've considered
- How this benefits other users

### 3. Add New Weclapp API Endpoints

The package doesn't cover all Weclapp API endpoints yet. You can help by:

- Implementing missing endpoints (
  see [Adding New Endpoints](#adding-new-endpoints))
- Ensuring the implementation follows our patterns
- Adding comprehensive documentation

### 4. Improve Documentation

Documentation improvements are always welcome:

- Fix typos or unclear explanations
- Add more usage examples
- Improve code comments
- Update the README with better examples

### 5. Write Tests

Help improve test coverage by:

- Writing tests for existing features
- Adding edge case tests
- Improving test documentation

## Development Setup

### Prerequisites

- PHP 8.5 or higher
- Composer 2
- Node.js 24 (`lts/krypton`, see `.nvmrc`; requires `>=24.18.0`) and npm 12 (pinned to `12.0.2` via the `packageManager` field, requires `>=12.0.0`) — used for commit tooling and releases
- Git

### Initial Setup

1. **Fork the repository** on GitHub

2. **Clone your fork** locally:
   ```bash
   git clone https://github.com/YOUR-USERNAME/laravel-weclapp-api.git
   cd laravel-weclapp-api
   ```

3. **Install dependencies**:
   ```bash
   composer install
   npm install
   ```

4. **Create a feature branch**:
   ```bash
   git checkout -b feature/your-feature-name
   ```

### Running Tests

Run the test suite to ensure everything works:

```bash
composer test
```

Run tests with coverage:

```bash
composer test-coverage
```

### Code Analysis

Run static analysis to catch potential issues:

```bash
composer analyse
```

## Coding Standards

This project follows strict coding standards to maintain consistency and
quality.

### PHP Standards

- Follow **PSR-12** coding style
- Use **PHP 8.5+ features** where appropriate (typed properties, union types,
  etc.)
- Write **type-safe code** with proper type hints

### Code Formatting

We use **Laravel Pint** for automatic code formatting.

Before committing, format your code:

```bash
composer format
```

This will automatically fix style issues according to Laravel's coding
standards.

### Documentation Standards

Every public method must have a PHPDoc block including:

```php
/**
 * Brief description of what the method does.
 *
 * Longer description if needed, explaining behavior,
 * constraints, or important notes.
 *
 * @param int|string $paramName Description of parameter
 * @param array $data Array structure:
 *                    - key1 (type, required/optional): Description
 *                    - key2 (type, required/optional): Description
 *
 * @return \Illuminate\Http\Client\Response
 * 
 * @throws ExceptionType When this exception is thrown
 */
public function methodName(int|string $paramName, array $data): \Illuminate\Http\Client\Response
{
    // Implementation
}
```

### Naming Conventions

- **Classes**: PascalCase (e.g., `TaskDependency`)
- **Methods**: camelCase (e.g., `createTask`)
- **Variables**: camelCase (e.g., `$taskId`)
- **Constants**: UPPER_SNAKE_CASE (e.g., `API_VERSION`)

## Adding New Endpoints

Every typed endpoint extends the abstract `Http\Endpoints\Endpoint` base, which
already provides `query()` / `find()` / `count()` (reads) and `create()` /
`update()` / `delete()` (writes) against a resource path. In the common case a
new endpoint is a three-line subclass — you do **not** re-implement the CRUD
methods.

### 1. Create the endpoint class

Create a file in `src/Http/Endpoints/` that declares the Weclapp resource
segment:

```php
<?php

declare(strict_types=1);

namespace Mindtwo\LaravelWeclappApi\Http\Endpoints;

class SalesChannel extends Endpoint
{
    protected string $path = 'salesChannel';
}
```

That is usually all that is needed. Only override or add methods for behaviour
the base cannot express (e.g. a sub-resource action). Writes must keep returning
a `LazyResponseProxy` (return `new LazyResponseProxy($this->api, $path, $method, body: $data)`)
so callers keep the sync/queue/event behaviour.

Resources that are not fully writable declare what the API offers, so an
unsupported call fails in the guard instead of going out as a request Weclapp
rejects:

```php
class SalesOpenItem extends Endpoint
{
    protected string $path = 'salesOpenItem';

    protected array $writes = [];
}
```

### 2. Register it

- Add the class to the `ENDPOINTS` list in `WeclappApiServiceProvider` so it is
  bound as a singleton.
- Add an accessor to `WeclappClient` (e.g. `public function salesChannels(): SalesChannel { return app(SalesChannel::class); }`).
- Add a matching `@method` line to the `WeclappClient` facade docblock.

All three are enforced by `tests/Vendor/EndpointSpecConformanceTest.php`, which
checks every class against the vendored spec rather than a hand-maintained list.
Forget one and the suite tells you which.

### Reading the vendored spec

Everything below — endpoint paths, write verbs, mirror columns, factory values —
comes out of the spec. Read it correctly or it will quietly tell you the wrong
thing.

**Two files, and they are not equivalent.** `docs/specifications/` holds both
`weclapp-openapi_v2.yaml` and `weclapp-openapi_v2.json`. They agree exactly on
`paths` (698), `components.schemas` (442) and `components.responses` (154). The
JSON is a lossy conversion that drops `info.description` — **77k characters**
documenting filter operators (`-eq`, `-in`, dot-notation predicates), the
`properties=` partial-response parameter and PATCH semantics — plus all 207
`tags` and 42 path descriptions. **Read the YAML for behaviour, the JSON for
structure.** `SpecFilesAgreeTest` pins the parts that must stay in sync.

**Schemas use `allOf` composition.** Reading `components.schemas.<x>.properties`
directly returns an empty array for most resources; the fields live behind
`$ref`s that must be resolved recursively:

```php
$resolve = function (string $name, array $seen = []) use (&$resolve, $schemas): array {
    if (isset($seen[$name])) {
        return [];
    }

    $seen[$name] = true;
    $out = [];

    foreach ($schemas[$name]['allOf'] ?? [] as $sub) {
        $out += isset($sub['$ref'])
            ? $resolve(basename($sub['$ref']), $seen)
            : ($sub['properties'] ?? []);
    }

    return $out + ($schemas[$name]['properties'] ?? []);
};
```

`articlePrice` reports 0 properties unresolved and 16 resolved. Concluding "the
spec has no schemas" from the unresolved read is a mistake that has already been
made once.

**Enums are `$ref`s to standalone schemas**, not inline lists. `salesInvoice.status`
points at `salesInvoiceStatusType` (`CANCELLED`, `DOCUMENT_CREATED`,
`ENTRY_COMPLETED`, `NEW`, `OPEN_ITEM_CREATED`); `salesChannel` points at
`distributionChannel`, which has **600** values. Resolve them before writing any
literal — `FactorySpecEnumTest` asserts the mirror factories only seed values the
spec allows.

**`maxLength` is documented** — size string columns from it rather than defaulting
to `string()`.

**There are no example responses.** Neither file contains a single `example` or
`examples` key. The spec gives you shape, never values, so it cannot tell you
which fields this tenant actually populates. That still needs a live read — see
`bin/verify-live-paths.sh`.

### How endpoint classes are validated

`$path` and `$writes` are derived from `docs/specifications/weclapp-openapi_v2.json`,
the vendored copy of the official spec, and the conformance test asserts they
still match it. That is the whole contract of these classes — they hold no field
mappings, so the spec is sufficient to keep them honest.

Live validation is a separate, weaker guarantee, and it is limited by the API
token available to us:

- **83 of 153 resources are live-confirmed.** `GET /meta/resources` returns the
  resources the calling token can see (82 of ours), each with its real operation
  set; `project` was confirmed by a direct read.
- **70 are spec-only.** Whole modules (ticketing, warehouse/storage, production,
  variant articles, banking) answer `403` for our token because they are not
  licensed or not granted, so they cannot be exercised at all. Note this includes
  a few long-standing classes — `contract`, `purchaseInvoice`, `purchaseOrder`,
  `shipment`, `warehouse` — not just recent additions.

Do **not** narrow `$writes` to match `/meta/resources`. It disagrees with
`GET /system/permissions` in both directions (for `article`, meta omits delete
while permissions grant it and withhold create), so neither reflects the API
contract — both describe one tenant's licensing and one user's rights. Encoding
either would break the package for consumers with different rights.

If you ever have a token with wider access, re-run the checks in
`bin/verify-live-paths.sh` (read-only, `GET` requests only; it reads
`MINDTWO_WECLAPP_URL` and `MINDTWO_WECLAPP_API_KEY` from `.env`) and compare
`/meta/resources` against the class list; that is the only way to shrink the
spec-only set.

### 3. Method contract

The inherited methods return:

- `query(array $filters = []): Illuminate\Support\Collection` — all pages merged
- `find(string|int $id): ?object` — `null` on 404
- `count(array $filters = []): int`
- `create(array)`, `update(string|int $id, array)`, `delete(string|int $id)` — a
  `LazyResponseProxy`

The generic client (`WeclappClient::get()/find()/count()/post()`) already reaches
any Weclapp resource, so a typed class is only about ergonomics and discoverability.

## Which entities have a mirror, and why

The package exposes a typed endpoint for **every** resource the API documents,
but ships a mirror table for only nine: `Article`, `ArticleCategory`,
`ArticlePrice`, `Party`, `Project`, `Quotation`, `SalesInvoice`, `SalesOrder`,
`User`. That gap is deliberate, and the two layers answer different questions.

An endpoint class is `$path` plus `$writes`, both derivable from the spec and
kept honest by a single conformance test — 155 of them cost nothing to maintain.
A mirror is a table, a field map, a factory and reconciliation semantics, and it
encodes a judgement the spec cannot make: *which fields are worth storing.*
`salesInvoice` resolves to 73 scalar columns; the shipped mirror keeps 25.

**The criterion is a consumer.** An entity earns a mirror when something reads
it, or when it is committed groundwork for work already agreed. The current nine
are the entities cloudbase's original sync commands covered, plus the sales and
pricing set added for the per-tenant storage work. Nothing was added on the
grounds that it might be useful one day.

That rule exists because it was once broken: `weclapp_amounts` and
`weclapp_reports` were built without a reader, could never receive a row, and
were removed in `df9b58b`. Generating all 119 mirror-able resources would be the
same mistake at scale — 357 files, 2,095 columns and a `weclapp:sync` that would
hammer every licensed module.

Since the generator makes adding one a single command, there is no benefit to
pre-building them. `MakeMirrorCommandTest` runs every resource in the spec
through the blueprint and parses the generated output, so bulk generation is
proven to work whenever a consumer actually needs it.

## Adding a mirror entity or sync command

**Start with the generator.** It derives the whole scaffold from the spec, which
is both faster and more accurate than hand-writing it:

```bash
php artisan weclapp:make-mirror articlePrice          # migration + model + factory
php artisan weclapp:make-mirror salesInvoice --dry    # preview only
php artisan weclapp:make-mirror ticket --only=id,subject,ticketNumber
```

It resolves the `allOf` chain, maps types (`format: timestamp` → `datetime` and
into `dates`; `format: decimal` → `decimal`; `*Id` and `version` → integer
columns despite being serialised as strings), sizes strings from `maxLength`,
seeds factory values from the property's enum, and drops nested collections and
entity references with a printed reason. It then prints the `SyncRegistry` entry
and `MIGRATIONS` line to paste, since editing those files automatically is more
trouble than it is worth.

Generated output is a **starting point, not a finished mirror** — `salesInvoice`
resolves to 73 scalar columns and you almost certainly want a fraction of them.
Trim before publishing, and add relations by hand.

Doing it manually instead:

1. Add a `weclapp_*` migration + Eloquent model + factory (mirror the existing
   ones under `database/migrations`, `src/Models`, `database/factories`).
2. Add a `SyncDefinition` entry to `Sync\SyncRegistry` mapping the endpoint,
   model, the column → API-field `map`, epoch-ms `dates`, the match `key`, and
   any static `defaults`.
3. Register the migration in `WeclappApiServiceProvider::MIGRATIONS` so it gets a
   `weclapp-api-migrations-{entity}` publish tag. `MigrationPublishTagTest` fails
   if you forget.

No command code changes are needed — the registry drives `weclapp:sync` and
`weclapp:update`.

**Derive the columns from the spec, not from a sampled response.** Resolve the
resource's schema (see *Reading the vendored spec*) and take field names, types,
`maxLength` and enum values from there.

Sampling live responses to discover the shape does not work, because **Weclapp
omits null fields from JSON entirely** — an absent key is indistinguishable from a
field that does not exist. On `articlePrice`, a three-record sample shows 12
fields; all 967 records show 16. The four that only appear sometimes include
`customerId`, which is the entire basis of customer-specific pricing. A live read
is still worth doing, but for a different question: which fields this tenant
actually populates, and how densely.

Nested collections (a party's addresses, an invoice's items, `reductionAdditions`)
are deliberately out of scope for `SyncDefinition`, which maps flat scalars of one
record into one row. Skip them and leave them to the consumer.

## Testing

### Writing Tests

Create test files in the `tests/` directory:

```php
<?php

use Illuminate\Support\Facades\Http;
use Mindtwo\LaravelWeclappApi\Facades\WeclappClient;

it('creates a resource', function () {
    Http::fake(['*/salesChannel' => Http::response(['id' => '1'], 201)]);

    $response = WeclappClient::salesChannels()->create(['name' => 'Test Resource']);

    expect($response->status())->toBe(201);
});
```

### Test Structure

- Use **Pest PHP** testing framework
- Group related tests using `describe()` blocks
- Use descriptive test names
- Test both success and failure scenarios
- Mock API responses when possible

### Running Specific Tests

Run a specific test file:

```bash
./vendor/bin/pest tests/YourTest.php
```

Run tests matching a pattern:

```bash
./vendor/bin/pest --filter="resource"
```

## Submitting Changes

### Commit Message Guidelines

Releases are cut by semantic-release, which reads the **Conventional Commits**
subject to choose the version bump and copies that line into `CHANGELOG.md`. The
subject is therefore public API: keep it descriptive and free of internal
shorthand.

```
feat(weclapp-api): add an endpoint for the goal resource

Explain why the change is needed and what it affects. Bullet points are fine.

ticket(s):
    - [sd-1234](https://app.clickup.com/t/30379192/SD-1234)
```

**Format:**

- Subject: `type(scope): summary` — `feat`, `fix`, `docs`, `test`, `chore`,
  `refactor`, `build`, `ci`. Append `!` and add a `BREAKING-CHANGE:` trailer for
  anything that breaks consumers.
- Blank line, then a body explaining *why*, not restating the diff.
- Internal work carries a `ticket(s):` trailer derived from the branch name. It
  belongs in the **body only** — never the subject, which would publish ClickUp
  ids to Packagist. External contributors should reference GitHub issues instead
  (`Fixes #123`).

GrumPHP validates the subject on commit.

### Pull Request Process

1. **Ensure all tests pass**:
   ```bash
   composer test
   composer analyse
   ```

2. **Format your code**:
   ```bash
   composer format
   ```

3. **Update documentation** if needed:
    - Update README.md with new features
    - Add PHPDoc comments
    - Update CHANGELOG.md

4. **Create a Pull Request** with:
    - Clear title describing the change
    - Detailed description of what changed and why
    - Link to related issues
    - Screenshots if UI-related

5. **Address review feedback**:
    - Respond to comments
    - Make requested changes
    - Push updates to your branch

### PR Checklist

Before submitting, ensure:

- [ ] All tests pass (`composer test`)
- [ ] Code is formatted (`composer format`)
- [ ] Static analysis passes (`composer analyse`)
- [ ] New features have tests
- [ ] Documentation is updated
- [ ] CHANGELOG.md is updated (if applicable)
- [ ] Commit messages are clear
- [ ] No merge conflicts with main branch

## Reporting Issues

### Before Creating an Issue

1. **Search existing issues** to avoid duplicates
2. **Check the documentation** to ensure it's not a usage question
3. **Test with the latest version** to see if it's already fixed

### Creating a Good Issue

**For Bug Reports:**

```markdown
## Bug Description

Clear description of the bug

## Steps to Reproduce

1. First step
2. Second step
3. See error

## Expected Behavior

What should happen

## Actual Behavior

What actually happens

## Environment

- PHP Version: 8.5.0
- Laravel Version: 12.x|13.x
- Package Version: 1.1.0

## Error Messages
```

Paste error messages or stack traces here

```

## Additional Context
Any other relevant information
```

**For Feature Requests:**

```markdown
## Feature Description

Clear description of the feature

## Problem It Solves

What problem does this solve?

## Proposed Solution

How should this work?

## Alternative Solutions

Other ways to solve this

## Additional Context

Any other relevant information
```

## Security Vulnerabilities

**Do not** create public issues for security vulnerabilities.

Instead, please email security concerns to the maintainers directly. See
our [Security Policy](SECURITY.md) for details.

## Questions?

If you have questions about contributing:

1. Check the [README.md](README.md) for package usage
2. Look at existing code for patterns
3. Create an issue on GitHub (discussions are disabled)
4. Reach out to the maintainers

## Recognition

All contributors will be at least recognized in the
project's [Contributors](../../contributors) listing.

## License

By contributing to this project, you agree that your contributions will be
licensed under the [MIT License](LICENSE.md).

---

**Thank you for contributing to `mindtwo/laravel-weclapp-api`!**

Your efforts help make this package better for everyone. <3
