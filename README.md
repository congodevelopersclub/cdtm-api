# CDTM-API Contributing Guide

Thanks for contributing! This document explains how to set up the project, the workflow we follow, and the quality gates every change must pass before it's merged and released.

## Table of Contents

- [Prerequisites](#prerequisites)
- [Getting Started](#getting-started)
- [Branching Model](#branching-model)
- [Commit Message Convention](#commit-message-convention)
- [Code Style — PHP CS Fixer](#code-style--php-cs-fixer)
- [Static Analysis — Larastan & PHPStan Strict Rules](#static-analysis--larastan--phpstan-strict-rules)
- [API Documentation — L5-Swagger](#api-documentation--l5-swagger)
- [Testing](#testing)
- [Pull Request Process](#pull-request-process)
- [Releases — Semantic Release](#releases--semantic-release)
- [CI Pipeline Overview](#ci-pipeline-overview)

---

## Prerequisites

- PHP 8.3 or higher (see `composer.json` for the exact required version)
- Composer
- Node.js + npm (only needed for `semantic-release`)
- A local database (MySQL/PostgreSQL/SQLite — see `.env.example`)

## Getting Started

```bash
git clone git@https://github.com/congodevelopersclub/cdtm-api.git
cd cdtm-api

composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed

```

Run the app as usual with `php artisan serve` or your preferred local environment (Sail, Valet, Docker, etc.).

---

## Branching Model

We use a **feature branching** model. There is a single long-lived branch, `main`, which is always deployable. All work happens on short-lived branches created from `main` and merged back via pull request.

| Branch type | Naming pattern                | Created from | Merges into |
| ----------- | ----------------------------- | ------------ | ----------- |
| Feature     | `feature/<short-description>` | `dev`        | `dev`       |
| Bug fix     | `fix/<short-description>`     | `dev`        | `dev`       |
| Hotfix      | `hotfix/<short-description>`  | `dev`        | `dev`       |
| Chore/docs  | `chore/<short-description>`   | `dev`        | `dev`       |

**Rules:**

1. Never commit directly to `main`. It is protected and only updated via merged/squashed pull requests.
2. Keep branches short-lived and focused on a single concern — smaller PRs are reviewed and merged faster.
3. Rebase (or merge `dev` into your branch) regularly to avoid large, painful conflicts.
4. Delete your branch after it's merged.

```bash
git checkout dev
git pull origin dev
git checkout -b feature/add-profile-filter
```

---

## Commit Message Convention

We use [Conventional Commits](https://www.conventionalcommits.org/), because `semantic-release` reads your commit history to decide the next version number and to generate the changelog. **This is not optional — releases depend on it.**

Format:

```
<type>(<optional scope>): <short summary>

<optional body>

<optional footer(s)>
```

Common types and their release effect:

| Type                         | Purpose                                   | Version bump    |
| ---------------------------- | ----------------------------------------- | --------------- |
| `feat`                       | A new feature                             | Minor (`1.x.0`) |
| `fix`                        | A bug fix                                 | Patch (`1.0.x`) |
| `perf`                       | A performance improvement                 | Patch           |
| `refactor`                   | Code change that isn't a fix or a feature | No release      |
| `docs`                       | Documentation only                        | No release      |
| `style`                      | Formatting only, no code meaning change   | No release      |
| `test`                       | Adding or fixing tests                    | No release      |
| `chore`                      | Build process, tooling, deps              | No release      |
| `BREAKING CHANGE:` in footer | Any breaking API/behavior change          | Major (`x.0.0`) |

Examples:

```
feat(invoices): add CSV export endpoint

fix(auth): correct token expiry calculation

feat(api)!: rename `customer_id` to `client_id` in responses

BREAKING CHANGE: `customer_id` is no longer returned; use `client_id` instead.
```

---

## Code Style — PHP CS Fixer

We use [`friendsofphp/php-cs-fixer`](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer) to enforce a consistent code style automatically in the CI

Config lives in `.php-cs-fixer.php` at the project root.

```bash
# Check for violations without changing files
vendor/bin/php-cs-fixer fix --dry-run --diff

# Auto-fix
vendor/bin/php-cs-fixer fix
```

Guidelines:

- Run the fixer **before** committing — don't rely on CI to catch style issues, it's better to fix your code in local befaire pushing.
- Do not hand-edit style purely to disagree with the fixer's output; if you think a rule should change, propose it via PR to `.php-cs-fixer.php` and discuss it separately.
- CI runs `php-cs-fixer fix --dry-run` and fails the build if any file would be changed.

Optional: add a pre-commit hook (e.g. via [Husky](https://typicode.github.io/husky/) or a Git hook script) to run this automatically.

---

## Static Analysis — Larastan & PHPStan Strict Rules

We use [`larastan/larastan`](https://github.com/larastan/larastan) (PHPStan with Laravel-specific type inference) together with [`phpstan/phpstan-strict-rules`](https://github.com/phpstan/phpstan-strict-rules) for stricter type safety on top of the base rule set.

Config lives in `phpstan.neon` (or `phpstan.neon.dist`).

```bash
vendor/bin/phpstan analyse
```

Guidelines:

- All new code must pass analysis at the project's configured level — do not lower the level to make errors disappear.
- Prefer fixing the underlying type issue over adding an inline `@phpstan-ignore-line`. If an ignore is unavoidable, add a short comment explaining why.
- If you introduce a new false positive that genuinely can't be resolved in code, add it to the `ignoreErrors` section of `phpstan.neon` with a comment, rather than sprinkling inline ignores.
- Run analysis locally before pushing — it's part of CI and will block merging otherwise.

---

## API Documentation — L5-Swagger

We use [`darkaonline/l5-swagger`](https://github.com/DarkaOnLine/L5-Swagger) to generate OpenAPI documentation from PHP annotations/attributes in the codebase.

- Every new or changed API endpoint **must** have accurate Swagger annotations (route, parameters, request body, responses, and error cases).
- Annotations typically live directly above the controller method they document.
- Keep a base `#[OA\Info(...)]` (or `@OA\Info`) definition up to date in a central location (e.g. a dedicated controller or the base API controller).

Regenerate docs locally after adding/changing annotations:

```bash
php artisan l5-swagger:generate
```

View the generated docs at the configured route (default: `/api/documentation`) while running the app locally.

**PR checklist for API changes:** annotations added/updated, docs regenerate without errors, and example request/response payloads reflect reality.

---

## Testing

We use [`phpunit/phpunit`](https://phpunit.de/) as the test runner (via `php artisan test` or directly).

```bash
php artisan test
# or
vendor/bin/phpunit
```

### Coverage requirement

CI enforces a **minimum test coverage of 80%**. Builds fail if coverage drops below this threshold, so make sure new code is adequately tested before opening a PR.

Generate a coverage report locally (requires Xdebug or PCOV enabled):

```bash
php artisan test --coverage --min=80
# or
vendor/bin/phpunit --coverage-text --coverage-clover=coverage.xml
```

Guidelines:

- Add or update tests for any behavior change (feature and/or unit tests as appropriate).
- All tests must pass, **and** overall coverage must be ≥ 80%, locally and in CI before a PR is merged.
- Don't chase coverage with meaningless tests — cover real behavior, edge cases, and error paths.

---

## Pull Request Process

1. Create a branch from `dev` following the [branching model](#branching-model).
2. Make your changes in small, logical commits using [Conventional Commits](#commit-message-convention).
3. Before opening the PR, run locally:
    ```bash
    vendor/bin/php-cs-fixer fix --dry-run --diff
    vendor/bin/phpstan analyse
    php artisan test --coverage --min=80
    ```
4. Push your branch and open a PR against `dev`. Fill in the PR template, describing what changed and why.
5. Ensure CI passes (style, static analysis, tests, and Swagger generation, as configured).
6. Address review feedback with additional commits (no need to force-push during review unless requested).
7. Once approved and green, the PR is merged into `dev` using **squash merge**, so the PR title (which should itself follow Conventional Commits format) becomes the single commit message that `semantic-release` reads.
8. Delete the branch after merge.

---

## Releases — Semantic Release

Releases are fully automated via [`semantic-release`](https://semantic-release.gitbook.io/) triggered on every push/merge to `main`.

What happens automatically on merge to `main`:

1. `semantic-release` analyzes commit messages since the last release (per the [Conventional Commits](#commit-message-convention) types above).
2. It determines the next semantic version (`major.minor.patch`).
3. It generates/updates `CHANGELOG.md`.
4. It tags the release in Git and publishes a GitHub release with release notes.
5. (If configured) it triggers deployment of the tagged version.

**Because of this:**

- Do not manually edit `CHANGELOG.md` — it is generated.
- Do not manually create Git tags for versions — `semantic-release` owns this.
- If your change should **not** trigger a release (e.g. internal tooling, docs), use a non-releasing commit type (`chore`, `docs`, `style`, `refactor`, `test` without a `fix`/`feat` in the body).
- A `fix:`/`feat:` commit that never makes it to `main` will never be released — releases only happen from `main`.

---

## CI Pipeline Overview

On every PR and on merges to `main`, CI runs:

1. **Install** — `composer install`, `npm install` (if applicable).
2. **Lint** — `php-cs-fixer fix --dry-run --diff`.
3. **Static analysis** — `phpstan analyse` (Larastan + strict rules).
4. **Tests & coverage** — full PHPUnit test suite, gated on **≥ 80% coverage**.
5. **API docs** — `l5-swagger:generate` to confirm annotations are valid.
6. **Release** (main only) — `semantic-release` runs after all the above pass.

A PR cannot be merged unless steps 2–5 pass. Step 6 only runs after merge, on `dev`.

---

Questions or suggestions about this process? Open an issue or start a discussion Congo developers club's slack
