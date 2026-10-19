---
name: php-conventions
description: "Project-specific PHP file conventions enforced silently on every PHP edit. Activate whenever creating, editing, or touching ANY .php file in this project — endpoint classes, models, factories, migrations, commands, jobs, events, listeners, exceptions, sync classes, config and tests. Covers: declare(strict_types=1); as the first statement after the opening <?php tag and its blank line, comment placement (always above the code line, NEVER end-of-line // comments — PHPDoc blocks are exempt), enum case naming convention (UPPERCASE_WITH_UNDERSCORES like FAVORITE_PERSON / ORDER_STATUS_PENDING / IN_APPROVAL), and core project tenets (use strict types where possible, latest code standards, latest Laravel features). Do not use for non-PHP files (Blade, JS, CSS, YAML, JSON)."
metadata:
  author: project
---

# PHP File Conventions

These rules apply silently and automatically to every `.php` file modified, created, or touched in this project.

## 1. Strict Types Declaration (mandatory on every PHP file)

`declare(strict_types=1);` is the first statement of every PHP file — classes,
anonymous migration classes, config and Pest test files alike.

### Exact structure
```
Line 1: <?php
Line 2: (blank line)
Line 3: declare(strict_types=1);
Line 4: (blank line)
Line 5: namespace Mindtwo\LaravelWeclappApi\…;   (omitted in config, migration and Pest files)
```

The blank line after the opening tag is pint's `blank_line_after_opening_tag`
rule; `composer lint` puts the declaration on line 3 whatever you write, so
write it there.

### Scope
Every `.php` file under `src/`, `database/`, `config/` and `tests/`. **No
exceptions.** (`workbench/` is Testbench's scaffold and is left as generated.)

### Why
Strict types prevent unexpected type juggling, ensure integer parameters remain integers, prevent string-to-number coercion, and maintain type integrity throughout the package. They catch type mismatches at runtime instead of allowing silent coercion that can cause subtle bugs.

### Application
Apply silently and automatically without asking permission. Whenever any PHP file is touched, verify or insert this declaration.

## 2. Comment Placement

**Comments MUST be placed on their own line above the code they describe. NEVER append comments at the end of a code line.**

- Correct: a comment on its own line, then the code line beneath it.
- Wrong: `$user->refresh(); // refresh the user`.

PHPDoc blocks (`/** ... */`) are exempt — they serve a different purpose and follow standard PHPDoc conventions.

This ensures consistent formatting and improves readability.

## 3. Enum Case Naming

**Enum case names MUST use `UPPERCASE_WITH_UNDERSCORES` convention.**

- Correct: `FAVORITE_PERSON`, `BEST_LAKE`, `MONTHLY`, `ORDER_STATUS_PENDING`, `NOT_FEASIBLE`, `IN_APPROVAL`.
- Wrong: `FavoritePerson`, `BestLake`, `Monthly`.

This follows standard PHP constant naming conventions and keeps enum cases consistent with the Weclapp values they mirror (`STANDARD_INVOICE`, `REDUCTION_PERCENT`).

## 4. Core Project Tenets

- Use strict types wherever possible.
- Use the latest code standards.
- Use the latest Laravel features available across the supported versions (`laravel/framework` in `composer.json`: 12 and 13).
