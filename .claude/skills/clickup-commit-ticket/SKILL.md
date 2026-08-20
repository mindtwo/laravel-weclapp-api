---
name: clickup-commit-ticket
description: Append the ClickUp ticket reference (derived from the current git branch name) to every commit message in this repo. Activate whenever creating a git commit, writing a commit message, or running a commit through Claude. If the branch name carries no ticket id, ask the committing user for it before committing, and offer to rename the branch to the team scheme (keeping its feature/ or bugfix/ prefix).
---

# ClickUp Commit Ticket Reference

## When to use this skill

Activate this skill **every time a commit is created in this project** — whenever the user asks to commit, when writing a commit message, or when a commit is run through Claude. It governs how commit messages and branch names are formed here.

## What every commit message must contain

In addition to the normal Conventional-Commit subject + body, every commit message MUST end with a ClickUp ticket trailer block, exactly in this shape (4-space indented bullet):

```
ticket(s):
    - [sd-%id%](https://app.clickup.com/t/30379192/SD-%id%)
```

- `%id%` is the ClickUp task id taken from the current branch name.
- The link text uses the lowercase prefix `sd-%id%`; the URL path uses the uppercase `SD-%id%`. The team/workspace segment `30379192` is fixed — never change it.
- The label is `ticket(s):` (plural-aware): if a branch legitimately references more than one ticket, add one indented `- [...]` line per ticket. Normally there is exactly one.
- This block goes in the commit **body**, never the subject — the subject stays a valid Conventional-Commit line (`type(scope): summary`, ≤120 chars, so the GrumPHP commit-msg hook passes).

## Deriving the ticket id from the branch

Read the id from the current branch name. The team scheme embeds it right after the prefix:

```
feature/sd-1234-new-branch-index   → id = 1234
bugfix/sd-987-fix-map-zoom         → id = 987
```

Match `sd-<id>` case-insensitively anywhere in the branch name; the id is the token immediately after `sd-`.

## If the branch carries no ticket id

Do NOT silently commit without the ticket trailer.

1. **Ask the committing user for the ClickUp ticket id first.** Pause the commit until they provide it (or explicitly tell you there is none — only then may you proceed without the trailer, and say so plainly).
2. Once you have the id, build the trailer block as above and include it in the commit.
3. **Offer to rename the branch to the team scheme** so future commits derive the id automatically:
   - Keep the existing kind prefix (`feature/` or `bugfix/`) if present.
   - Insert `sd-<id>-` after the prefix, then the existing human description as a kebab-case suffix.
   - Example: a branch `feature/new-branch-index` with ticket `1234` becomes `feature/sd-1234-new-branch-index`.
   - If the branch has no prefix at all, propose one (`feature/` for features, `bugfix/` for fixes) and confirm it.
   - **Always ask for confirmation before renaming** (`git branch -m <old> <new>`). Never rename without an explicit yes.

## Squashing commits

When collapsing several commits into one (e.g. tidying a branch before a PR or
merge), the squashed commit follows an **extended schema** — the same one used for
the SD-114009 ACL refactor. It differs from a normal single commit in three ways:

1. ~~**Subject carries the ticket id.**~~ **Does not apply in this repository.**
   `mindtwo/laravel-weclapp-api` releases through semantic-release, which parses
   the Conventional-Commit subject to pick the version bump and copies it verbatim
   into `CHANGELOG.md`. Putting `SD-%id%` in the subject would publish internal
   ClickUp ids to Packagist. Keep the id in the trailer for squashed commits too;
   the subject stays a plain `type(scope): summary`.
2. **Body is a `Squashed commits:` list.** Under a `Squashed commits:` header, add
   one 4-space-indented bullet per squashed commit, each preserving that commit's
   original subject line, in order. Do **not** replace this list with hand-written
   prose — the list *is* the body.
3. **Trailer lists every referenced ticket.** Keep the usual `ticket(s):` block,
   but include *every distinct* ticket referenced across the squashed commits —
   primary (branch) ticket first, deduplicated, one 4-space-indented
   `- [sd-%id%](https://app.clickup.com/t/30379192/SD-%id%)` line each. If all the
   squashed commits share one ticket, the trailer has a single line.

Full shape:

```
feat(acl): user, roles & teams refactoring

Squashed commits:
    - feat(users): track last login + soft deletes
    - refactor(auth): replace boolean flags with Spatie roles & permissions
    - chore(generated): regenerate ide-helper and wayfinder artifacts

ticket(s):
    - [sd-114009](https://app.clickup.com/t/30379192/SD-114009)
    - [sd-119606](https://app.clickup.com/t/30379192/SD-119606)
```

Preserve any `BREAKING-CHANGE:` / `Signed-off-by:` trailers from the squashed
commits, placing them after the ticket block. Only squash the commits that belong
to the work being collapsed — leave unrelated pre-existing commits on the branch
untouched.

## Hard rules

- **Never** add a `Co-Authored-By: Claude …` trailer (or any AI co-author line) to commit messages in this project — it is forbidden by organization policy. The ticket block is the only required trailer.
- The ticket trailer is mandatory on every commit unless the user has explicitly confirmed the work has no ticket.
- Keep the Conventional-Commit subject intact and the ticket block in the body so the commit-msg hook passes.
- **This repository publishes to Packagist via semantic-release.** The subject line
  becomes the public changelog entry, so it must stay free of ClickUp ids and
  internal shorthand. The `ticket(s):` trailer is unaffected — it lives in the body,
  which semantic-release does not surface.
