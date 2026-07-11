# FNLLA

[![License](https://img.shields.io/badge/license-MIT-111827?style=flat-square)](./LICENSE.md)
[![UI Contract](https://img.shields.io/badge/ui-integrated%20runtime-0f766e?style=flat-square)](./public/vendor/fnlla-runtime/README.md)
[![Runtime](https://img.shields.io/badge/runtime-php%208.3%20%2B%20mysql-2f65eb?style=flat-square)](./VERSION)
[![Development](https://img.shields.io/badge/source-github%20only-c26d00?style=flat-square)](./scripts/sync-fnlla-runtime.ps1)

## What FNLLA is

FNLLA is a compact open-source PHP framework for server-rendered websites and application surfaces that ship with the official built-in FNLLA UI runtime.

It intentionally stays small enough that one maintainer or one delivery team can trace the whole request lifecycle without hidden framework magic, while still shipping the practical foundations needed for production work.

The supported application contract includes:

- `public/index.php` and `public/router.php` as the public HTTP entrypoints
- `bootstrap/` for application bootstrapping and environment wiring
- `src/` for framework source code
- `views/` for plain PHP templates
- `routes/` for HTTP and console route definitions
- `public/vendor/fnlla-runtime/` for the built-in authoritative UI runtime

FNLLA is produced, maintained and distributed by TechAyo LTD (techayo.co.uk).

Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.

## Name origin

The name `FNLLA` comes from Finella, and more specifically from Finella Gardens in Dundee, UK. That location is the origin point of the `FNLLA` framework line.

## Ownership and license

FNLLA is open-source software released under the MIT License by TechAyo LTD (techayo.co.uk).

Use of the source code is governed by `LICENSE.md`.

Support, maintenance and release-cadence expectations are documented in `SUPPORT.md`.

Trademark and branding boundaries for the FNLLA product names and marks are documented in `TRADEMARKS.md`.

The current repository identity is defined by state files that should stay aligned:

- `MANIFEST.json`
- `README.md`
- `VERSION`
- `LICENSE.md`
- `SUPPORT.md`
- `TRADEMARKS.md`

Repository participation and disclosure rules also rely on:

- `CODE_OF_CONDUCT.md`
- `SECURITY.md`

## What it includes

FNLLA currently ships with:

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
- a built-in cookie consent banner and settings modal powered by the built-in runtime
- a server-side page-title contract that feeds professional browser-tab titles into the shared layout
- built-in runtime enforcement and TechAyo-maintained runtime synchronization

## Support and release expectations

Anyone may use FNLLA under the MIT License, including for self-service and commercial work.

TechAyo LTD does not promise support, maintenance, SLA coverage or a fixed release cadence for third-party projects built on FNLLA.

Public updates are shipped when TechAyo LTD decides they are appropriate.

Third-party deployments remain responsible for their own hosting, integrations, cookie usage, security controls, monitoring, backups, patching and incident response.

Use `SUPPORT.md` for the exact support boundary and `TRADEMARKS.md` for branding rules.

## Repository structure

- `bootstrap/` contains application bootstrap stages and shared environment setup
- `config/` contains framework and delivery configuration
- `database/migrations/` contains schema changes
- `database/seeders/` contains seeders
- `database/factories/` contains factories
- `docs/` contains maintainer and delivery guides for building on top of the framework
- `lang/` contains translation lines
- `public/` contains the public entrypoints, static assets and the built-in runtime
- `routes/` contains HTTP and console route definitions
- `scripts/` contains maintainer and validation scripts
- `src/` contains the framework source code
- `storage/` contains runtime state such as logs, sessions, cache and queue files
- `tests/` contains the local repository test harness and framework test coverage
- `views/` contains the server-rendered PHP templates

## Built-in runtime boundary

FNLLA is not a UI-agnostic framework in the official stack.

The built-in runtime under `public/vendor/fnlla-runtime/` is part of FNLLA itself and the only supported UI layer for this repository and for downstream development based on this framework.

Important operational rules:

- do not replace the built-in runtime with another CSS framework
- do not introduce Tailwind, Bootstrap, Bulma, Foundation, UIkit, Materialize or Semantic UI into the official FNLLA stack
- do not load runtime assets from third-party CDNs
- keep the built-in runtime under `public/vendor/fnlla-runtime/`
- use the runtime workflow maintained inside `techayoDEV/fnlla` when syncing UI runtime updates

When writing views, treat that runtime as the shared view toolkit already built into FNLLA:

- keep full document structure in `views/layouts/app.php`
- keep page-level markup in `views/pages/`
- compose pages with the shipped `section`, `container`, `card`, `grid`, `stack`, `btn`, `alert` and form primitives first
- reach for project CSS in `public/assets/app.css` only when the built-in runtime does not already express the layout or token you need

## CSS variables and tokens

The built-in runtime is the source of truth for shared CSS variables in the official stack.

That means:

- shared colors, spacing, typography, sizing, radii, transitions and theme tokens come from `public/vendor/fnlla-runtime/assets/css/fnlla-runtime.css`
- downstream FNLLA styles should consume those `--fnlla-*` variables instead of rebuilding a second global token system
- FNLLA may define a small project-local layer of aliases such as `--fnlla-shell-*` in `public/assets/app.css` when the application shell needs its own composed values

Practical rule:

- use `--fnlla-color-*`, `--fnlla-space-*`, `--fnlla-font-*`, `--fnlla-radius-*` and related runtime tokens first
- add `--fnlla-shell-*` only for delivery-shell specifics that do not belong back in the shared UI runtime
- avoid scattering new hardcoded colors through `public/assets/app.css` when an existing runtime token already expresses the same design intent

## Strict development contract

FNLLA enforces the built-in runtime contract during development.

That enforcement currently includes:

- validating that the built-in UI runtime exists locally
- validating that the shared layout keeps the expected runtime shell structure
- validating that page templates keep the section and container conventions
- rejecting markers that suggest unsupported alternate CSS frameworks
- refreshing local built-in runtime guard state on a timed interval during development bootstraps
- auto-repairing a missing built-in runtime through the sync script and dedicated `fnlla-runtime:*` commands

If the built-in runtime contract is broken, the application and CLI fail fast until the repository is brought back into compliance.

## How to run it locally

```bash
cd <path-to-fnlla>
php -S 127.0.0.1:8080 -t public public/router.php
```

Then open `http://127.0.0.1:8080`.

For Apache-based local or production hosting, point the document root at `public/`.
The repository already includes the rewrite file at `public/.htaccess`.
There is intentionally no top-level `.htaccess` because `public/` is the only supported web root.

Copy `.env.example` to `.env` when you want explicit local configuration.

The template ships with local-development defaults that are safe for plain HTTP on `127.0.0.1`.
Before production deployment, switch the environment values back to production-safe settings and serve the app over HTTPS.
If the application sits behind a reverse proxy, set `TRUSTED_PROXIES` so forwarded client IP and HTTPS headers are only honored from explicitly trusted proxy addresses.

No Packagist download step is required for the framework itself.

## How to start a real new project

For an actual new website or web application, the recommended workflow is not to clone `techayoDEV/fnlla` and build the downstream project directly inside the framework repository.

Instead:

1. keep `techayoDEV/fnlla` as the maintained framework source
2. export a clean starter into a separate project directory
3. build the real website or application in that exported directory

Use:

```bash
php fnlla make:project ..\my-new-project "My New Project"
```

Then open the exported directory, initialize its own Git repository and build the real project there by modifying the shipped starter itself.

The intended model is:

- the starter is the base public application shell
- downstream teams replace and extend that shell directly
- maintenance, health and CLI remain linked framework capabilities around the app
- the framework repo stays the public source of truth for the shared built-in runtime, docs and update rules

Use [`docs/STARTING-A-NEW-PROJECT.md`](./docs/STARTING-A-NEW-PROJECT.md) for the exact workflow and rationale.

## Database boundary

FNLLA currently targets MySQL only.

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
php scripts/validate-fnlla-runtime.php
php scripts/validate-version-manifest.php
php scripts/build-docs.php --check
```

Windows launchers are also included:

```cmd
test-fnlla.cmd
lint-fnlla.cmd
update-fnlla-runtime.cmd
```

If Composer is present locally, `composer test` and `composer lint` still work as wrappers, but the framework no longer depends on Packagist for its day-to-day test or lint workflow.

## Building new websites and apps

Use [`docs/BUILDING-WITH-FNLLA.md`](./docs/BUILDING-WITH-FNLLA.md) as the primary guide for building new websites and web applications on top of FNLLA.

That guide covers:

- the recommended project build sequence
- how to add routes, controllers and views
- how to structure forms, validation and flash feedback
- how to use MySQL, migrations and the query builder
- how to protect pages with auth and authorization
- how to use the built-in runtime while composing and extending views

## Documentation set

The repository also ships a browsable docs set under [`docs/index.html`](./docs/index.html), styled on top of the built-in runtime.

Primary pages:

- `docs/index.html`
- `docs/distribution.html`
- `docs/getting-started.html`
- `docs/building.html`
- `docs/api.html`
- `docs/guides.html`

The long-form guide pages are generated from:

- `docs/STARTING-A-NEW-PROJECT.md`
- `docs/BUILDING-WITH-FNLLA.md`

When docs content or the shared docs shell changes, rebuild or verify the HTML with:

```bash
php scripts/build-docs.php
php scripts/build-docs.php --check
```

## CLI surface

Use `php fnlla list` to see the currently registered commands.

Important commands:

- `php fnlla fnlla-runtime:sync`
- `php fnlla fnlla-runtime:validate`
- `php fnlla framework:update --check --github`
- `php fnlla framework:update --check [--source <path-to-fnlla>]`
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

## Public source of truth

FNLLA is the public source of truth for the official FNLLA framework stack maintained by TechAyo LTD.

The built-in runtime remains part of the same TechAyo-controlled maintainer workflow, and the public entry point for both the framework and the integrated runtime is this repository.

Packagist, npm-style registry distribution and third-party mirrors are intentionally out of scope for the official maintainer workflow.

## Maintainer workflow

The repository root is the maintainer workspace.

Generated runtime state, local queue files, session files and logs should not be treated as hand-authored sources.

Authoritative maintainer scripts and checkpoints:

- `scripts/publish-fnlla-runtime.ps1` publishes the integrated built-in runtime from `public/vendor/fnlla-runtime/` into `dist/fnlla-runtime/`
- `scripts/sync-fnlla-runtime.ps1` syncs the built-in runtime from that published export workflow
- `scripts/sync-version-manifest.php` regenerates the repository MANIFEST.json from current version state
- `scripts/build-docs.php` rebuilds the shared HTML documentation set from the maintained docs sources
- `scripts/validate-fnlla-runtime.php` validates the enforced UI runtime contract
- `scripts/validate-version-manifest.php` validates framework and built-in runtime version metadata
- `scripts/validate-release-metadata.php` audits release-facing links, ownership markers and repository references before publication work
- `scripts/audit-fnlla-ecosystem.ps1` audits the local framework workspace, integrated runtime metadata and shared TechAyo defaults before release work
- exported projects keep `.fnlla/framework-lock.json` as the authoritative framework-base lock, while older compatibility artifacts stay internal to update flows
- exported projects keep `php fnlla framework:update` as the public downstream update command, while older compatibility aliases stay hidden
- exported projects also keep a local-first `/maintenance/framework-update` page with buttons for browser-based check and safe apply flows
- the GitHub-backed framework-update flow only prepares diffs or apply runs when the published release is newer than the current locked framework base, so it does not suggest downgrades over equal or ahead-of-release starter builds
- `scripts/test.php` runs the repository-local framework tests
- `scripts/lint.php` runs PHP syntax checks across the maintained source tree
- `bootstrap/common.php` enforces the shared UI runtime guard during bootstrap

Important boundary:

- `php fnlla framework:update` is a downstream project command and expects `.fnlla/framework-lock.json`
- use it from an exported application repository, not from the maintainer `techayoDEV/fnlla` repository root itself

Recommended maintainer sequence:

```bash
php scripts/test.php
php scripts/lint.php
php scripts/validate-fnlla-runtime.php
php scripts/validate-version-manifest.php
php scripts/validate-release-metadata.php
php scripts/build-docs.php --check
powershell -ExecutionPolicy Bypass -File .\scripts\publish-fnlla-runtime.ps1
php fnlla fnlla-runtime:sync
php fnlla version:status
```

## Runtime and repository boundary

Treat these as the public and supported downstream runtime surface:

- `public/index.php`
- `public/router.php`
- `public/assets/`
- `public/vendor/fnlla-runtime/`

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

## Product identity

FNLLA is the public framework identity in the FNLLA line.

The integrated runtime and the framework repository still share:

- the `FNLLA` naming origin
- TechAyo LTD ownership
- the same delivery direction and support boundary
- the same expectation that repository metadata should clearly identify the maintainer and ownership boundary

Treat the vendored runtime as part of the FNLLA stack, not as a separate first-stop public product.
