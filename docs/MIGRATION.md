# FNLLA Migration Guide

This guide is the authoritative place for downstream projects moving between
FNLLA versions. Version history belongs in CHANGELOG; this guide consolidates
compatibility and upgrade procedures rather than maintaining one file per release.

## Compatibility Notes

For 2.2.0, enable `ext-fileinfo` wherever upload MIME validation runs.
`UploadedFile::detectedMimeType()` now throws when detection is unavailable;
it no longer falls back to the client-provided Content-Type. Keep `mimeType()`
only for displaying untrusted metadata, never for an authorization or type check.

For 2.0.x installations adopting the 2.1 line, review environment templates,
project identity/ownership, the public API lock, database helpers, pagination and
release commands. Keep application routes, views, controllers and data intact.
The 2.1.0 to 2.1.1 transition added project acceptance, verified backup planning
and HTTP performance probes; it did not require a framework schema or route rewrite.

```console
php fnlla project:acceptance --json
php fnlla ops:backup-plan --verify
php fnlla perf:profile --iterations=5 --write-baseline
php fnlla perf:budget --iterations=5 --max-regression=20 --max-regression-ms=1000
```

Create a baseline on a controlled environment, not on production during an update.
For the pending 2.2.0 changes, follow [Runtime contracts](framework/RUNTIME-CONTRACTS.md):
non-local migrations require --force, application auth revalidates account identity,
cache writers preserve working files, forwarded protocol must be canonical, and
new exports support application generators. Review and explicitly run only the
migrations required by the application; never run migrations just to clear a checklist.
Full file updates and package-mode updates have different contracts; do not migrate
an existing installation by merely editing its profile or package marker.

Persistent sessions now enforce idle and absolute time limits on the server.
Review `SESSION_LIFETIME_MINUTES` and `SESSION_ABSOLUTE_LIFETIME_MINUTES` before
deployment and expect a fresh login for expired or malformed legacy sessions.
Start session-dependent workflows before emitting response output.
New integrated exports enable all modules. Project Setup and Panel Settings can
disable unwanted modules; updating an existing project's code preserves its settings.
`make:project` no longer prompts automatically. Use `--interactive` for the retained
advanced chooser or `--profile=plain` for an explicit core-only export.

Back up before changes, test login/roles/CRUD/forms/uploads/queue/mail/health on a
staging copy, and retain private recovery evidence following
[release operations](RELEASE-AND-OPERATIONS.md#backup-and-recovery).

## Upgrade Categories

### Upgrading A 2.1.3 Project

After 2.2.0 is published, use the updater from its verified source distribution,
not the old project's 2.1.3 updater. From the project directory:

```console
php ../fnlla-2.2.0/fnlla framework:update --project=. --release-tag=v2.2.0 --dry-run
php ../fnlla-2.2.0/fnlla framework:update --project=. --release-tag=v2.2.0 --apply
```

The project target is explicit; local/fork release sources remain prohibited.
Back up first and stop traffic. The updater tracks framework policy documents,
VERSION and bundled UI files, using verified normalized 2.1.3 hashes for formerly
untracked files. Local changes cause conflicts rather than silent replacement.
Only unchanged, lock-hash-matched framework test fixtures are removed; modified
tests and application tests are preserved. Reconcile any retained historical tests
with the new documented behavior. Manifest refresh and metadata changes are inside
the rollback journal. Application routes, public styles/views, uploads and `.env`
remain application-owned, as does `config/app.php`. Claimed product identity is
preserved when rebuilding the manifest. Old unused individual icon files are not automatically
deleted because their ownership was not recorded by 2.1.3.

- Patch release: should be low-risk and focused on fixes, hardening or
  additional tooling. Example: `2.1.0 -> 2.1.1`.
- Minor release: may add public API, docs, operational commands and framework
  capability while preserving the same major contract. Example:
  `2.0.x -> 2.1.0`.
- Major release: may require manual review of public APIs, framework-managed
  files and product-owned integration points.

Even for patch releases, run acceptance and product tests in the downstream
repository before deploying.

## Application Layers And Ownership

A downstream FNLLA product has three layers:

- **Framework layer**: framework bootstrap, core `src/` classes, built-in
  runtime assets, project-facing console commands, default configuration and
  release metadata shipped by TechAyo.
- **Application layer**: product routes, controllers, views, migrations,
  seeders, repositories, mailables, jobs, tests and business copy created by the
  downstream team after `make:project`.
- **Environment layer**: `.env`, hosting secrets, logs, cache, queues, uploads,
  backups, deployment variables and real customer/runtime data.

`make:project` creates the application repository with FNLLA underneath it. The
commercial product then lives above the framework layer. A framework update must
therefore improve the lower layer without silently replacing product work in the
application or environment layers.

## No-Breakage Update Contract

Framework updates are intentionally conservative:

- updates come from the official `techayoDEV/fnlla` GitHub release channel;
- downloaded release sources must contain valid FNLLA manifest/version metadata;
- `.fnlla/framework-lock.json` records the currently installed framework base;
- `framework:update --check` and `--dry-run` compare local files before writing;
- application-owned files with local drift are reported as conflicts instead of
  being overwritten;
- `.env`, logs, queue state, uploads, backups, cache and generated runtime state
  are treated as environment/runtime data and are not update targets;
- `upgrade:plan` separates safe automated actions from manual-review actions;
- `upgrade:apply` is dry-run by default and applies only actions marked safe;
- post-update checks must include acceptance, tests, lint and strict security
  audit before deployment.

When in doubt, the updater should stop and produce evidence rather than guess.
That is the expected model for "anti-breakage" in FNLLA.

## Files Commonly Updated By The Framework

A normal framework update may refresh:

- framework core under `bootstrap/`, `src/`, `config/` and `fnlla`;
- project-facing docs and scripts that are explicitly part of the public
  project contract;
- built-in runtime files under `public/vendor/fnlla-runtime/`;
- default templates and metadata such as `VERSION`, `MANIFEST.json` and
  `.fnlla/framework-lock.json`;
- generated framework caches only when the command explicitly clears or rebuilds
  them.

Files that require special care:

- `routes/`, `views/`, `database/`, `tests/` and application controllers may
  contain product-owned changes; review dry-run output before accepting upstream
  changes in these paths.
- `.env.example` and `.env.full.example` may gain new documented keys, but the
  real `.env` remains environment-owned.
- `storage/`, `dist/`, logs, sessions, uploads and backups should not be used as
  source-of-truth framework files.

## Recommended Current Update Flow

Use this flow for maintained 2.x applications moving to the latest public
release, currently `2.1.3`:

```bash
php fnlla version:status
php fnlla framework:update --check
php fnlla framework:update --dry-run
php fnlla upgrade:check --target=2.1.3
php fnlla upgrade:plan --target=2.1.3
php fnlla ai:upgrade-brief --target=2.1.3
```

If the dry-run report has no conflicts and the upgrade plan contains only safe
actions you accept, apply the update:

```bash
php fnlla framework:update --apply
php fnlla upgrade:apply --target=2.1.3 --yes
```

Then validate the product:

```bash
php fnlla project:acceptance --json
php scripts/test.php
php scripts/lint.php
php fnlla security:audit --strict
php fnlla app:map
```

For production releases, add product E2E tests, backup/restore evidence and
performance probes before deployment.

## Historical 1.x To 2.0 Flow

For teams that want a browser-first workflow, use the built-in maintenance GUI:

1. Open `/maintenance/framework-update` from a developer-authorised local session.
2. Run **Check major readiness** in the Major upgrade safety section.
3. Review the checks, warnings, failures and upgrade plan actions.
4. Run **Apply safe actions** only when UI apply is enabled for that environment.
5. Complete any manual-review items listed by the plan.

The GUI uses the same `UpgradeAnalyzer` as the CLI. It can write the upgrade
plan and clear generated runtime residue without hand-editing files. It will not
perform manual migration review or overwrite project-owned work.

The equivalent historical CLI flow remains available from the project
repository:

```bash
php fnlla upgrade:check --target=2.0.0
php fnlla upgrade:plan --target=2.0.0
php fnlla upgrade:apply --target=2.0.0
php fnlla app:map
php fnlla ai:upgrade-brief --target=2.0.0
```

`upgrade:apply` defaults to dry-run. Pass `--yes` only after reviewing the plan.
The command and GUI apply only actions marked `safe_to_apply`; anything that can
change application behaviour remains a manual review item.

## Public Contract To Review

Before adopting a major version, review:

- CLI commands and their machine-readable JSON schemas
- `config/` keys used by deployment scripts
- route names consumed by views or tests
- middleware aliases and route groups
- helper behaviour for `asset()`, `route()`, `config()`, `cache()`, `auth()` and
  session/CSRF helpers
- generated files under `storage/framework/cache/`
- exported-project lock metadata under `.fnlla/framework-lock.json`

## Downstream Release Sequence

For a maintained commercial product:

```bash
php fnlla version:status
php fnlla framework:update --check
php fnlla framework:update --dry-run
php fnlla upgrade:check --target=2.1.3
php fnlla project:acceptance --json
php scripts/test.php
php scripts/lint.php
php fnlla security:audit --strict
```

Apply only after reviewing the dry-run report and confirming product-owned files
will not be overwritten unexpectedly.

## AI-Assisted Migration

FNLLA does not send application data to an AI provider. Generate local artefacts
and decide explicitly what to share:

```bash
php fnlla ai:review-pack --target=2.1.3
php fnlla ai:redact --input storage/framework/cache/ai-review-pack.json
```

Good review prompt:

> Review this FNLLA upgrade pack for release blockers, migration risks,
> backward-compatibility issues and missing tests.

## Release Owner Checklist

- Run `php fnlla release:prepare --major --target=2.1.3`.
- Confirm generated SBOM and checksums are attached to the public release.
- Confirm `CHANGELOG.md` has a dated entry for the tag.
- Confirm this migration guide matches the final public behaviour.
- Confirm runtime cache, sessions, logs and generated local review artefacts are not
  committed.
