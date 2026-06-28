# FNLLA PHP

[![License](https://img.shields.io/badge/license-proprietary-111827?style=flat-square)](./LICENSE.md)
[![UI Contract](https://img.shields.io/badge/ui-fnlla--ui%20required-0f766e?style=flat-square)](./public/vendor/fnlla-ui/README.md)
[![Runtime](https://img.shields.io/badge/runtime-php%208.3%20%2B%20mysql-2f65eb?style=flat-square)](./VERSION)
[![Development](https://img.shields.io/badge/source-github%20only-c26d00?style=flat-square)](./scripts/sync-fnlla-ui.ps1)

## What FNLLA PHP is

FNLLA PHP is a compact proprietary PHP framework for server-rendered websites and application surfaces that are built on top of the FNLLA UI runtime.

It intentionally stays small enough that one maintainer or one delivery team can trace the whole request lifecycle without hidden framework magic, while still shipping the practical foundations needed for production work.

The supported application contract includes:

- `public/index.php` and `public/router.php` as the public HTTP entrypoints
- `bootstrap/` for application bootstrapping and environment wiring
- `src/` for framework source code
- `views/` for plain PHP templates
- `routes/` for HTTP and console route definitions
- `public/vendor/fnlla-ui/` for the vendored authoritative FNLLA UI runtime

FNLLA PHP is produced, maintained and distributed by TechAyo LTD (techayo.co.uk).

Copyright (c) 2026 TechAyo LTD (techayo.co.uk). All rights reserved.

## Name origin

The name `FNLLA` comes from Finella, and more specifically from Finella Gardens in Dundee, UK. That location is the origin point of both `FNLLA UI` and `FNLLA PHP`.

## Ownership and license

FNLLA PHP is proprietary software owned by TechAyo LTD (techayo.co.uk).

Its use is governed by `LICENSE.md`, which permits commercial use in productions executed by TechAyo LTD while prohibiting standalone redistribution, resale and unauthorized relicensing.

The current repository identity is defined by four state files that should stay aligned:

- `MANIFEST.json`
- `README.md`
- `VERSION`
- `LICENSE.md`

Repository participation and disclosure rules also rely on:

- `CODE_OF_CONDUCT.md`
- `SECURITY.md`

## What it includes

FNLLA PHP currently ships with:

- an HTTP application kernel and front controller flow
- routing with route groups, route names and URL generation
- request and response abstractions
- middleware aliases including `auth`, `authorize`, `csrf`, `cors` and `throttle`
- a lightweight DI container
- plain PHP controllers and views
- configuration loading and `.env` support
- structured logging and exception handling
- MySQL-only PDO database access and a query builder
- migrations, rollback, seeders and factories
- sessions, cookies, CSRF protection and session-backed authentication
- authorization gates
- file cache and file-backed queue primitives
- a simple scheduler and CLI command surface
- localization helpers
- FNLLA UI contract enforcement and GitHub-only UI runtime synchronization

## Repository structure

- `bootstrap/` contains application bootstrap stages and shared environment setup
- `config/` contains framework and delivery configuration
- `database/migrations/` contains schema changes
- `database/seeders/` contains seeders
- `database/factories/` contains factories
- `docs/` contains maintainer and delivery guides for building on top of the framework
- `lang/` contains translation lines
- `public/` contains the public entrypoints, static assets and the vendored FNLLA UI runtime
- `routes/` contains HTTP and console route definitions
- `scripts/` contains maintainer and validation scripts
- `src/` contains the framework source code
- `storage/` contains runtime state such as logs, sessions, cache and queue files
- `tests/` contains the local repository test harness and framework test coverage
- `views/` contains the server-rendered PHP templates

## FNLLA UI dependency boundary

FNLLA PHP is not a UI-agnostic framework in the official stack.

FNLLA UI is the only supported UI layer for this repository and for downstream development based on this framework.

Important operational rules:

- do not replace FNLLA UI with another CSS framework
- do not introduce Tailwind, Bootstrap, Bulma, Foundation, UIkit, Materialize or Semantic UI into the official FNLLA PHP stack
- do not load FNLLA UI assets from third-party CDNs
- keep the vendored runtime under `public/vendor/fnlla-ui/`
- treat the GitHub `fnlla/ui` repository as the only supported source of truth for FNLLA UI updates

## Strict development contract

FNLLA PHP enforces the FNLLA UI contract during development.

That enforcement currently includes:

- validating that the vendored FNLLA UI runtime exists locally
- validating that the shared layout keeps the FNLLA UI shell structure
- validating that page templates keep the section and container conventions
- rejecting markers that suggest unsupported alternate CSS frameworks
- auto-syncing the vendored runtime from GitHub on a timed interval when development bootstraps

If the UI contract is broken, the application and CLI fail fast until the repository is brought back into compliance.

## How to run it locally

```bash
cd <path-to-fnlla-php>
php -S 127.0.0.1:8080 -t public public/router.php
```

Then open `http://127.0.0.1:8080`.

For Apache-based local or production hosting, point the document root at `public/`.
The repository already includes the rewrite file at `public/.htaccess`.
There is intentionally no top-level `.htaccess` because `public/` is the only supported web root.

Copy `.env.example` to `.env` when you want explicit local configuration.

No Packagist download step is required for the framework itself.

## How to start a real new project

For an actual new website or web application, the recommended workflow is not to clone `fnlla/php` and build the downstream project directly inside the framework repository.

Instead:

1. keep `fnlla/php` as the maintained framework source
2. export a clean starter into a separate project directory
3. build the real website or application in that exported directory

Use:

```bash
php fnlla make:project ..\my-new-project "My New Project"
```

Then open the exported directory, initialize its own Git repository and build the real project there.

Use [`docs/STARTING-A-NEW-PROJECT.md`](./docs/STARTING-A-NEW-PROJECT.md) for the exact workflow and rationale.

## Database boundary

FNLLA PHP currently targets MySQL only.

Required PHP/runtime expectations:

- PHP 8.3
- `pdo_mysql` enabled
- a reachable MySQL server

Database work is exposed through:

- `src/Database/DatabaseManager.php`
- `src/Database/QueryBuilder.php`
- `src/Database/Migrations/`
- `database/migrations/`
- `database/seeders/`
- `database/factories/`

## Local quality checks

Use the repository-local commands:

```bash
php scripts/test.php
php scripts/lint.php
php scripts/validate-fnlla-ui.php
php scripts/validate-version-manifest.php
```

Windows launchers are also included:

```cmd
test-fnlla-php.cmd
lint-fnlla-php.cmd
update-fnlla-ui.cmd
```

If Composer is present locally, `composer test` and `composer lint` still work as wrappers, but the framework no longer depends on Packagist for its day-to-day test or lint workflow.

## Building new websites and apps

Use [`docs/BUILDING-WITH-FNLLA-PHP.md`](./docs/BUILDING-WITH-FNLLA-PHP.md) as the primary guide for building new websites and web applications on top of FNLLA PHP.

That guide covers:

- the recommended project build sequence
- how to add routes, controllers and views
- how to structure forms, validation and flash feedback
- how to use MySQL, migrations and the query builder
- how to protect pages with auth and authorization
- how to stay inside the FNLLA UI contract during delivery

## CLI surface

Use `php fnlla list` to see the currently registered commands.

Important commands:

- `php fnlla fnlla-ui:sync`
- `php fnlla fnlla-ui:validate`
- `php fnlla migrate`
- `php fnlla migrate:rollback`
- `php fnlla migrate:status`
- `php fnlla db:seed`
- `php fnlla cache:clear`
- `php fnlla queue:work`
- `php fnlla schedule:run`
- `php fnlla route:list`
- `php fnlla version:status`
- `php fnlla version:sync`

## GitHub-only source of truth

FNLLA PHP and FNLLA UI are intended to be maintained from GitHub repositories owned or controlled by TechAyo LTD.

For the official stack:

- `fnlla-php` repository is the source of truth for the PHP framework
- `fnlla/ui` repository is the source of truth for the UI runtime

Packagist, npm-style registry distribution and third-party mirrors are intentionally out of scope for the official maintainer workflow.

## Maintainer workflow

The repository root is the maintainer workspace.

Generated runtime state, local queue files, session files and logs should not be treated as hand-authored sources.

Authoritative maintainer scripts and checkpoints:

- `scripts/sync-fnlla-ui.ps1` syncs the vendored FNLLA UI runtime from GitHub
- `scripts/sync-version-manifest.php` regenerates the repository MANIFEST.json from current version state
- `scripts/validate-fnlla-ui.php` validates the enforced FNLLA UI contract
- `scripts/validate-version-manifest.php` validates framework and vendored runtime version metadata
- `scripts/test.php` runs the repository-local framework tests
- `scripts/lint.php` runs PHP syntax checks across the maintained source tree
- `bootstrap/common.php` enforces the shared FNLLA UI guard during bootstrap

Recommended maintainer sequence:

```bash
php scripts/test.php
php scripts/lint.php
php scripts/validate-fnlla-ui.php
php scripts/validate-version-manifest.php
php fnlla fnlla-ui:sync
php fnlla version:status
```

## Runtime and repository boundary

Treat these as the public and supported downstream runtime surface:

- `public/index.php`
- `public/router.php`
- `public/assets/`
- `public/vendor/fnlla-ui/`

Treat these as maintainer-owned framework internals:

- `bootstrap/`
- `config/`
- `database/`
- `routes/`
- `scripts/`
- `src/`
- `tests/`

Treat these as delivery-layer templates that still belong to the framework repository:

- `views/layouts/`
- `views/pages/`

## Related product identity

FNLLA UI and FNLLA PHP are related proprietary framework products maintained under the same product family.

They share:

- the `FNLLA` naming origin
- TechAyo LTD ownership
- the same delivery and licensing direction
- the same expectation that repository metadata should clearly identify the maintainer and ownership boundary

Use the repos as a coordinated pair, not as unrelated framework projects.
