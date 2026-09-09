# {{APP_NAME}}

This repository is a working application export generated from `techayoDEV/fnlla`.

It is intended to be the beginning of a new server-rendered website or web application built on:

- FNLLA
- the integrated FNLLA UI surface
- PHP 8.3
- MySQL

## What is already included

- the FNLLA application core
- the integrated FNLLA UI surface under `public/vendor/fnlla-runtime/`
- the integrated server-side runtime intelligence bundle under `resources/fnlla-ai-runtime/`
- the built-in FIONN AI gateway by TechAyo and optional OpenAI API / Anthropic API adapters; external connections disabled by default
- machine-readable release metadata in `MANIFEST.json`
- framework update baseline metadata in `.fnlla/framework-lock.json`
- root license: `LICENSE.md`; framework references:
  `docs/framework/SUPPORT.md`, `docs/framework/TRADEMARKS.md`
- an application base with public pages for home, about and services
- an optional password-protected maintenance access screen for client preview or staged review sessions
- sessions, cookies, CSRF, auth foundations and the rest of the core runtime under `src/`
- database directories ready for project-specific migrations and seeders
- local lint, test, version metadata and integrated UI surface validation scripts
- `project:acceptance` for framework-base smoke checks before product work begins
- a local-first framework maintenance page at `/maintenance/framework-update`

## How to start working

1. Claim the project identity:

```bash
php fnlla project:claim --product "{{APP_NAME}}" --owner "Owner" --developer "Developer" --maintainer "Developer"
```

2. Copy `.env.example` to `.env`. Use `.env.full.example` only as the complete
   operator reference when you need advanced keys.
3. Set `APP_URL`, your MySQL credentials and the client-preview password if the
   site should be reviewed privately before launch.
4. Run:

```bash
php fnlla project:acceptance --json
php fnlla fnlla-runtime:validate
php scripts/test.php
php scripts/lint.php
php scripts/validate-version-manifest.php
```

5. Start the local server:

```bash
php -S 127.0.0.1:8080 -t public public/router.php
```

6. Open `http://127.0.0.1:8080` in your browser and review the exported pages at `/`, `/about` and `/services`.
7. Use `http://127.0.0.1:8080/maintenance/framework-update` when you want a browser-based framework update check or safe apply flow.
8. When client preview should stay private, either:

   - open `/maintenance` locally and use the built-in "Save and enable maintenance" setup form on a fresh project export, or
   - set `MAINTENANCE_MODE_ENABLED=true` and `MAINTENANCE_ACCESS_PASSWORD=<your-password>` in `.env`

The maintenance page is controlled through `FRAMEWORK_UPDATE_UI_ENABLED`, `FRAMEWORK_UPDATE_UI_LOCAL_ONLY`, `FRAMEWORK_UPDATE_UI_APPLY_ENABLED`, `FRAMEWORK_UPDATE_GITHUB_ENABLED`, `MAINTENANCE_MODE_ENABLED`, `CLIENT_PREVIEW_ENABLED`, `CLIENT_PREVIEW_*`, `MAINTENANCE_SETUP_UI_ENABLED`, `MAINTENANCE_SETUP_UI_LOCAL_ONLY` and the related `MAINTENANCE_ACCESS_*` variables in `.env`.

For Apache environments, use `public/` as the document root.
The exported project already includes `public/.htaccess`.

The exported `.env.example` is the short starter for local development and
client-preview setup. `.env.full.example` is the full framework environment
reference for operators. Before production deployment, move real secrets into
the host secret store where possible, switch the environment back to
production-safe values and enable HTTPS.

## Commercial baseline

Before writing the first product feature, make one clean baseline commit in the
exported project containing:

- claimed project identity
- reviewed `.env.example` and any advanced values copied from `.env.full.example`
- passing `project:acceptance`
- passing runtime validation, tests and lint
- no generated files from `storage/`, `dist/` or local caches
- a short project README update describing the real owner, support route and
  deployment target

Then build business code in small product commits: schema, migration,
repository, route, controller, view, validation, auth/role test and deployment
note.

## What the export intentionally leaves behind

This exported application does not copy the full maintainer workspace from `techayoDEV/fnlla`.

It intentionally leaves behind:

- framework-only browser docs under `docs/`
- versioned project-export templates under `resources/project-templates/`
- the maintainer documentation checks and Markdown reference library
- repository governance and contribution files such as `.git/`, `.github/CODE_OF_CONDUCT.md` and `SECURITY.md`
- local runtime residue such as logs, cache entries, queue files, session files and integrated UI surface guard state
- maintainer publishing, ecosystem audit, release-metadata validation and export benchmarking scripts
- the unused FNLLA starter logo and individual icon SVG files

The export is an explicit file manifest maintained in
`resources/project-templates/v1/export-files.json` in the upstream repository.
New framework files must be deliberately added to that manifest. Storage and
uploads are generated empty, regardless of ignored files in the source checkout.

The `.fnlla/ui-distribution` file selects `sprite` for a compact UI package.
Use local `vendor/fnlla-runtime/assets/icons/sprite.svg#search` references; the
sprite includes all icon names, including aliases. Runtime synchronization keeps
this distribution compact. Projects requiring individual SVG URLs can change the
file to `full` before the next official runtime sync. Existing projects without
this file keep the full distribution. Keep the icon LICENSE and NOTICE files.

That keeps the downstream project focused on application delivery rather than framework maintenance.

## First files to replace or review

- `routes/web.php`
- `src/Controllers/PageController.php`
- `views/pages/`
- `public/assets/app.css`
- `database/migrations/`
- `config/app.php`

## Important note

The exported project still contains a working application surface so the application runs immediately.

That surface is a starting point, not the final product. Replace the placeholder pages, routes and content with the real website or application flow for this project.

Use `LICENSE.md`, `docs/framework/SUPPORT.md` and `docs/framework/TRADEMARKS.md` to understand the upstream FNLLA code license, support boundary and branding rules that came with this application base.

## Useful commands

- `composer install` builds the project autoloader without downloading heavy dev tools by default; commit the generated application `composer.lock`.
- `php scripts/test.php` runs the bundled project smoke harness; `composer analyse` runs PHPStan/Psalm when added and otherwise uses the bundled baseline.
- `composer install --no-dev --optimize-autoloader` is still the production install form.
- Authenticated panel layout is `views/layouts/developer.php`; public layout/CSS changes do not override it.

The application base keeps only the project-facing scripts, smoke tests and commands:

`tests/ProjectTest.php` covers local setup, protected access, CSRF and health;
`tests/BootstrapAutoloadTest.php` covers the shipped namespaces. Extend these tests
with application behavior. Framework internals and demonstration-specific tests
stay upstream. Tests belong to this application and are not replaced by runtime
updates. Add full PHPUnit deliberately when the project needs that runner.

- `php scripts/test.php` runs the project-local smoke test harness kept under `tests/`
- `php scripts/lint.php` runs PHP syntax lint across the maintained project tree
- `php scripts/validate-fnlla-runtime.php` checks that the exported project still respects FNLLA's integrated UI surface contract
- `php scripts/validate-version-manifest.php` checks that `VERSION`, `MANIFEST.json` and the integrated UI surface metadata stay aligned on one FNLLA version
- `php fnlla project:claim --product "Product Name" --owner "Owner" --developer "Developer"` writes project identity into `MANIFEST.json`, `.env.example`, `README.md` and `config/app.php`
- `php fnlla project:acceptance --json` checks runtime files, writable storage, `/`, `/api/health` and `/maintenance` before product-specific work or release
- `php fnlla doctor` checks local PHP/runtime readiness before development, CI or release
- `php fnlla security:audit` checks deploy-time security configuration posture
- `php fnlla optimize` builds route and configuration caches for production-style deployments
- `php fnlla optimize:warm` builds bootstrap caches, the asset manifest and optional OPcache preload file
- `php fnlla app:map` generates a route/controller/view map for audits, onboarding and AI-assisted review
- `php fnlla upgrade:check --target={{FNLLA_VERSION}}` checks current-release upgrade readiness
- `php fnlla upgrade:plan --target={{FNLLA_VERSION}}` writes a machine-readable upgrade plan
- `php fnlla perf:profile --iterations=5` records local CLI timings, repository footprint and peak memory
- `php fnlla perf:baseline:update --iterations=7` captures a local performance baseline
- `php fnlla perf:budget --iterations=5 --max-regression=20 --max-regression-ms=1000` compares current p95 timings against a saved local baseline
- `php fnlla ai:context` writes a local redacted context pack for AI-assisted review without raw secrets
- `php fnlla ai:review-pack --target={{FNLLA_VERSION}}` combines context, app map and upgrade readiness into one local AI review artefact
- `php fnlla ai:providers --json` reports provider configuration readiness without contacting external services; it does not verify a live connection or imply a bundled model
- `php fnlla optimize:clear` removes generated bootstrap caches before local development or release packaging
- `php fnlla release:prepare` runs the release gate and generates SBOM/checksum artefacts under `dist/release/`
- `php fnlla config:doctor --json` checks project environment configuration
- `php fnlla developer:install-storage --dry-run` prints the optional panel database schema
- `php fnlla ops:backup-plan --verify` verifies the project backup plan
- `php fnlla tech-debt:update --json` reports project debt without requiring upstream documentation
- `php fnlla framework:update --check` checks the latest published FNLLA release from the official `techayoDEV/fnlla` GitHub channel and caches the release source locally before comparing drift
- `php fnlla framework:update --dry-run` writes an exact file-change report before any apply run
- `php fnlla framework:update --apply` applies the safe portion of a newer official GitHub-backed update after the report has no conflicts
- `/maintenance/framework-update` provides the same official GitHub-backed check, dry-run and apply workflow through a local-first maintenance page
- `php fnlla version:sync` regenerates `MANIFEST.json` and re-syncs integrated UI surface metadata after an intentional FNLLA version change
- `php fnlla fnlla-runtime:sync` refreshes the integrated FNLLA UI surface from the official `techayoDEV/fnlla` GitHub repository through the publish -> sync workflow

The export intentionally leaves `make:*`, `make:project` and broader framework-internal test coverage in the upstream `techayoDEV/fnlla` repository.

Project release preparation validates the application tests, lint, runtime,
version metadata, acceptance probes and configuration. It does not require the
FNLLA maintainer documentation or delete application queues, sessions or logs.
Release checksums exclude runtime storage, uploads and local environment files.

The full framework documentation remains in the upstream `techayoDEV/fnlla` repository.
Start with `docs/README.md`, `docs/STARTING-A-NEW-PROJECT.md`,
`docs/BUILDING-WITH-FNLLA.md`, `docs/PUBLIC-API.md` and
`docs/RELEASE-AND-OPERATIONS.md` there when you need deeper framework guidance.

The export keeps standard tool entrypoints at root: Composer metadata,
`phpunit.xml`, `phpstan.neon`, `.env.example`, `VERSION`, `MANIFEST.json`,
`LICENSE.md`, `README.md`, `fnlla` and `fnlla.cmd`. Optional Windows wrappers
live under `scripts/windows/`, and framework policy references live under
`docs/framework/`.

OpenAI API and Anthropic API can be configured in Developer Panel > Integrations > AI providers or
through `AI_OPENAI_*` / `AI_ANTHROPIC_*` in `.env.full.example`. Set your API key,
an available model ID and the selected `AI_RUNTIME_DRIVER`. PHP cURL is required
only for these cloud adapters. Cloud requests send the explicit question, not
application context, source files or sessions. Status checks do not call providers;
costs and provider data policies apply. Never publish provider keys or responses.

Space Grotesk and JetBrains Mono are self-hosted in the full starter. Override
`--fnlla-font-base`, `--fnlla-font-heading` and `--fnlla-font-mono` for your product;
keep font license notices with redistributed assets.
Both families ship real 400/600 weights. Space Grotesk handles body, headings,
navigation and prose; JetBrains Mono handles literal URLs, email, code, versions
and metadata. Use `.fnlla-literal` for displayed technical values, not all links.
The standalone FIONN AI name uses `.fnlla-fionn-name`. Plain keeps system fonts.

FNLLA framework authorship: **Lead Developer / Product Manager - Marcin Kordyaczny**.
This credits the framework, not the ownership or leadership of your application.
The private About FNLLA screen exposes the same framework credit.

FIONN AI, Persistent Personal Intelligence created by TechAyo, has a built-in,
controlled API gateway, not a bundled local model. An appropriate FIONN developer
account and API access are required. Configure `AI_FIONN_ENDPOINT`,
`AI_FIONN_ALLOWED_HOSTS`, `AI_FIONN_API_TOKEN` and explicit activation according
to the production security checklist. Memory and permissions belong to the
connected service; FNLLA disables learning and does not automatically index the
project or write service memory. Never share personal memory between app users.
The default `local` driver is a deterministic reference lookup, not AI inference.

The GitHub-backed framework-update flow only prepares diffs or apply runs when the published FNLLA release is actually newer than the framework base already locked into this application, so the browser and CLI workflow do not suggest downgrades over equal or ahead-of-release project builds.

```bash
php fnlla list
php fnlla fnlla-runtime:sync
php fnlla fnlla-runtime:validate
php fnlla project:claim --product "Product Name" --owner "Owner" --developer "Developer"
php fnlla project:acceptance --json
php fnlla doctor
php fnlla security:audit
php fnlla framework:update --check
php fnlla framework:update --dry-run
php fnlla optimize
php fnlla optimize:warm
php fnlla app:map
php fnlla upgrade:check --target={{FNLLA_VERSION}}
php fnlla perf:profile --iterations=5
php fnlla perf:baseline:update --iterations=7
php fnlla ai:context
php fnlla ai:review-pack --target={{FNLLA_VERSION}}
php fnlla ai:providers --json
php fnlla optimize:clear
php fnlla release:prepare
php fnlla route:list
php fnlla migrate
php fnlla migrate:rollback
php fnlla migrate:status
php fnlla version:status
php fnlla version:sync
php scripts/test.php
php scripts/lint.php
php scripts/validate-version-manifest.php
```

Developer password recovery is documented in the upstream
`docs/DEVELOPER-PANEL.md#developer-account-recovery` section. Email reset
requests require a configured mail transport and `php fnlla queue:work 50`;
authorized server owners can use `php fnlla developer:recovery-link EMAIL`.

On Windows, the application export also includes:

```cmd
scripts\windows\test-project.cmd
scripts\windows\lint-project.cmd
scripts\windows\update-fnlla-runtime.cmd
```
