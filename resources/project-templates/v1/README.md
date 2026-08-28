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
- the opt-in Fionn runtime AI bridge contract, disabled by default
- machine-readable release metadata in `MANIFEST.json`
- framework update baseline metadata in `.fnlla/framework-lock.json`
- root legal and policy files: `LICENSE.md`, `SUPPORT.md`, `TRADEMARKS.md`
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
php fnlla project:claim --product "{{APP_NAME}}" --owner "Owner LTD" --developer "Developer LTD" --maintainer "Developer LTD"
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
- the maintainer docs builder `scripts/build-docs.php`
- repository governance and contribution files such as `.git/`, `.github/`, `CODE_OF_CONDUCT.md` and `SECURITY.md`
- local runtime residue such as logs, cache entries, queue files, session files and integrated UI surface guard state

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

Use `LICENSE.md`, `SUPPORT.md` and `TRADEMARKS.md` to understand the upstream FNLLA code license, support boundary and branding rules that came with this application base.

## Useful commands

The application base keeps only the project-facing scripts, smoke tests and commands:

- `php scripts/test.php` runs the project-local smoke test harness kept under `tests/`
- `php scripts/lint.php` runs PHP syntax lint across the maintained project tree
- `php scripts/validate-fnlla-runtime.php` checks that the exported project still respects FNLLA's integrated UI surface contract
- `php scripts/validate-version-manifest.php` checks that `VERSION`, `MANIFEST.json` and the integrated UI surface metadata stay aligned on one FNLLA version
- `php fnlla project:claim --product "Product Name" --owner "Owner LTD" --developer "Developer LTD"` writes project identity into `MANIFEST.json`, `.env.example`, `README.md` and `config/app.php`
- `php fnlla project:acceptance --json` checks runtime files, writable storage, `/`, `/api/health` and `/maintenance` before product-specific work or release
- `php fnlla doctor` checks local PHP/runtime readiness before development, CI or release
- `php fnlla security:audit` checks deploy-time security configuration posture
- `php fnlla optimize` builds route and configuration caches for production-style deployments
- `php fnlla optimize:warm` builds bootstrap caches, the asset manifest and optional OPcache preload file
- `php fnlla app:map` generates a route/controller/view map for audits, onboarding and AI-assisted review
- `php fnlla upgrade:check --target=2.1.1` checks current-release upgrade readiness
- `php fnlla upgrade:plan --target=2.1.1` writes a machine-readable upgrade plan
- `php fnlla perf:profile --iterations=5` records local CLI timings, repository footprint and peak memory
- `php fnlla perf:baseline:update --iterations=7` captures a local performance baseline
- `php fnlla perf:budget --iterations=5 --max-regression=20 --max-regression-ms=1000` compares current p95 timings against a saved local baseline
- `php fnlla ai:context` writes a local redacted context pack for AI-assisted review without raw secrets
- `php fnlla ai:review-pack --target=2.1.1` combines context, app map and upgrade readiness into one local AI review artefact
- `php fnlla ai:providers --json` reports local runtime AI provider readiness without contacting external providers
- `php fnlla optimize:clear` removes generated bootstrap caches before local development or release packaging
- `php fnlla release:prepare` runs the release gate and generates SBOM/checksum artefacts under `dist/release/`
- `php fnlla framework:update --check` checks the latest published FNLLA release from the official `techayoDEV/fnlla` GitHub channel and caches the release source locally before comparing drift
- `php fnlla framework:update --dry-run` writes an exact file-change report before any apply run
- `php fnlla framework:update --apply` applies the safe portion of a newer official GitHub-backed update after the report has no conflicts
- `/maintenance/framework-update` provides the same official GitHub-backed check, dry-run and apply workflow through a local-first maintenance page
- `php fnlla version:sync` regenerates `MANIFEST.json` and re-syncs integrated UI surface metadata after an intentional FNLLA version change
- `php fnlla fnlla-runtime:sync` refreshes the integrated FNLLA UI surface from the official `techayoDEV/fnlla` GitHub repository through the publish -> sync workflow

The export intentionally leaves `make:*`, `make:project` and broader framework-internal test coverage in the upstream `techayoDEV/fnlla` repository.

The full framework documentation remains in the upstream `techayoDEV/fnlla` repository.
Start with `docs/README.md`, `docs/STARTING-A-NEW-PROJECT.md`,
`docs/BUILDING-WITH-FNLLA.md`, `docs/PUBLIC-API.md` and
`docs/PRODUCTION-CHECKLIST.md` there when you need deeper framework guidance.

Fionn integration is a controlled API bridge, not a copied model or knowledge
bundle. Keep `AI_RUNTIME_DRIVER=local` unless the product explicitly enables a
separate Fionn service through `AI_FIONN_ENDPOINT`, `AI_FIONN_ALLOWED_HOSTS` and
the production security checklist.

The GitHub-backed framework-update flow only prepares diffs or apply runs when the published FNLLA release is actually newer than the framework base already locked into this application, so the browser and CLI workflow do not suggest downgrades over equal or ahead-of-release project builds.

```bash
php fnlla list
php fnlla fnlla-runtime:sync
php fnlla fnlla-runtime:validate
php fnlla project:claim --product "Product Name" --owner "Owner LTD" --developer "Developer LTD"
php fnlla project:acceptance --json
php fnlla doctor
php fnlla security:audit
php fnlla framework:update --check
php fnlla framework:update --dry-run
php fnlla optimize
php fnlla optimize:warm
php fnlla app:map
php fnlla upgrade:check --target=2.1.1
php fnlla perf:profile --iterations=5
php fnlla perf:baseline:update --iterations=7
php fnlla ai:context
php fnlla ai:review-pack --target=2.1.1
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

On Windows, the application export also includes:

```cmd
test-project.cmd
lint-project.cmd
update-fnlla-runtime.cmd
```
