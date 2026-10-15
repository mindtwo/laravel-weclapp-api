---
name: php-conventions
description: "Project-specific PHP file conventions enforced silently on every PHP edit. Activate whenever creating, editing, or touching ANY .php file in this project — models, controllers, factories, tests, Livewire components, services, commands, middleware, form requests, jobs, notifications, mail classes, events, listeners, resources, seeders, migrations, enums, traits, interfaces. Covers: declare(strict_types=1); on line 2 (between opening <?php tag and namespace declaration), comment placement (always above the code line, NEVER end-of-line // comments — PHPDoc blocks are exempt), enum case naming convention (UPPERCASE_WITH_UNDERSCORES like FAVORITE_PERSON / ORDER_STATUS_PENDING / IN_APPROVAL — overrides any TitleCase suggestion in foundation rules), and core project tenets (use strict types where possible, latest code standards, latest Laravel features). Do not use for non-PHP files (Blade, JS, CSS, YAML, JSON)."
metadata:
  author: project
---

# PHP File Conventions

These rules apply silently and automatically to every `.php` file modified, created, or touched in this project.

## 1. Strict Types Declaration (mandatory on every PHP class file)

`declare(strict_types=1);` MUST appear on **line 2** of every PHP file containing a class, interface, trait, or enum.

### Exact structure
```
Line 1: <?php
Line 2: declare(strict_types=1);
Line 3: (blank line)
Line 4: namespace App\…;
```

### Scope
This applies to ALL PHP files: Models, Controllers, Factories, Tests, Livewire components, Services, Commands, Middleware, Form Requests, Jobs, Notifications, Mail classes, Events, Listeners, Resources, Seeders, Migrations, and any other PHP class file. **No exceptions.**

### Why
Strict types prevent unexpected type juggling, ensure integer parameters remain integers, prevent string-to-number coercion, and maintain type integrity throughout the application. They catch type mismatches at runtime instead of allowing silent coercion that can cause subtle bugs.

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

This overrides any `TitleCase` suggestion in foundation/boost-managed rules.

- Correct: `FAVORITE_PERSON`, `BEST_LAKE`, `MONTHLY`, `ORDER_STATUS_PENDING`, `NOT_FEASIBLE`, `IN_APPROVAL`.
- Wrong: `FavoritePerson`, `BestLake`, `Monthly`.

This follows standard PHP constant naming conventions and ensures consistency across the application. For full enum-design rules (translation files, label/options methods, string-based enums for duplicate values), see the `enum-development` skill.

## 4. Core Project Tenets

- Use strict types wherever possible.
- Use the latest code standards.
- Use the latest Laravel features available in the configured Laravel version (see CLAUDE.md foundation rules for the version).
