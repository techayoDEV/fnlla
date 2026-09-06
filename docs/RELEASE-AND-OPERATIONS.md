# FNLLA Release And Operations

## Target 2.2.0 Acceptance

2.2.0 is the selected stable target, not a published version. Consult
`MODERNIZATION-STATUS.md` and the canonical modernization ledger before advancing
VERSION or creating a tag. The current working tree is not a substitute for an
immutable, tested release artifact.

Review `framework/RUNTIME-CONTRACTS.md` before upgrading: non-local migrations now
require explicit `--force`, proxy protocol must be canonical, application auth
revalidates accounts, and cache publication requires secure CLI/PHP permissions.

`release:prepare --skip-tests` only builds artifacts. Its `ok` means the command
succeeded; `validation: skipped` and `risk: unknown` explicitly mean validation was
not performed. Even `validation: passed` covers local commands only. Confirm the
remote PHP/OS/MySQL/Redis matrix, installation from actual release artifacts,
upgrade/rollback evidence and security review separately before publication.

Maintainer validation requires installed PHPUnit and runs the framework suite with
`--fail-on-skipped`; the offline smoke harness is not a release certification.
Run `composer install` first. Project exports keep their local project runner.
Release preparation never clears application caches, pending jobs, sessions or
logs. Distribution exclusions produce a clean artifact without destroying local
state. Remove obsolete files from `dist` only after confirming they are generated
output, not backups or evidence that must be retained privately.

The quality workflow audits the installed lock file with `composer audit --locked`
and uploads JUnit reports for each PHP/OS and integration job, retained for 14 days.
Reports live in the runner temporary directory, not the source artifact. Check
that every job belongs to the exact candidate commit; retain release evidence
outside temporary CI retention before approving a stable release.

This document describes the operational commands that keep FNLLA release-ready,
observable and easier to audit in business deployments.

## Documentation Before Every Release

Before approving any GitHub release, review the changed behavior and update its
Markdown instructions, examples, environment reference, CLI/API contracts,
migration notes and security/operational limitations. Include first-run setup
and account recovery when access behavior changes. Update release notes for the
approved version, and keep unresolved architecture work in the JSON ledger.
Do not replace evidence with checked boxes or delete active acceptance criteria.

```sh
php scripts/build-docs.php
php scripts/build-docs.php --check
php scripts/check-docs.php
php scripts/check-modernization.php
php fnlla release:prepare
```

All maintainer releases require synchronized documentation, documentation hygiene
and a valid modernization ledger, including non-major releases and `--skip-tests`
invocations. The quality workflow checks documentation too. Hygiene checks detect
broken relative Markdown links, workstation paths and known credential patterns;
they report locations without echoing matched secrets. These checks do not prove
prose completeness, absence of every possible secret or completion of unfinished work.
`--require-complete` is a separate architecture acceptance gate, not a requirement
to pretend every roadmap item is finished before an incremental release.
Exported application projects do not run maintainer-only documentation scripts.
Publication, tags and remote pushes still require explicit release approval.

## Clean Source Archives

`scripts/build-source-archive.ps1` requires PowerShell, Git and PHP on PATH. It
checks documentation hygiene before packaging the current working tree and refuses
to overwrite an existing archive. A working-tree archive is for inspection, not
proof that a tagged release passed CI.

```powershell
./scripts/build-source-archive.ps1 -OutputPath ./dist/fnlla-source-review.zip
```

The shared policy in `resources/source-distribution.json` excludes runtime state,
uploads, dependencies, editor state, previous build output, editable brand masters,
credential files, private keys, database dumps and nested archives. SBOM/checksum
generation applies the same sensitive-file exclusions; `.gitattributes` also
protects Git archives. Environment examples remain included. These rules exclude
files from distribution, not from the application's filesystem or backup policy.
Use PHP migrations rather than shipping SQL dumps as source fixtures.

Inspect the archive before publication, including its Markdown and generated HTML.
Public instructions must describe FNLLA with neutral examples. Keep application
recovery evidence, account details, local paths and deployment reports in private
storage. Preserve license attribution and documented public API identifiers.

## Release Acceptance Checklist

This is the canonical checklist for both incremental and major releases.

1. Review public helpers, CLI commands and JSON contracts against the API lock.
   Document intentional changes in MIGRATION and CHANGELOG.
2. Run `composer install`, `composer audit --locked`,
   `composer test:unit -- --testsuite framework`, `composer analyse` and
   `composer lint`. Deprecations are failures, not warnings to suppress.
   The offline smoke runner does not replace PHPUnit acceptance.
3. Run the documentation checks and `php fnlla release:prepare`. For a major
   compatibility review, also use `--major --target=2.2.0` and inspect its app map,
   upgrade plan and redacted AI review pack. Adjust the target for future releases.
4. Verify full and plain exports, first-run setup, application-owned files,
   dry-run/apply behavior and rollback from a supported previous release.
5. Review CORS, sessions, trusted hosts/proxies, debug and mail policy. Run
   `php fnlla security:audit --strict` with the intended deployment configuration.
6. Compare representative baselines with `perf:baseline:update` and `perf:compare`,
   including `/` and `/api/health`. Publish measured results, not unsupported
   performance claims or comparisons between inequivalent applications.
7. Push the approved candidate and require Core Quality, Hardening and Release
   Gate to pass for that exact commit. Inspect every matrix job, not an older
   green run or a workflow still in progress.
8. Inspect source archives, SBOMs and checksums; verify consumer installation and
   upgrade from the actual immutable artifacts. Preserve evidence privately.
9. Obtain separate tag/publication approval. Align release notes, version/runtime
   metadata and migration instructions; attach only reviewed release assets.

Core Quality covers PHP 8.3, 8.4 and 8.5 on Windows and Linux, plus MySQL/Redis
integration on Linux. Release Gate adds macOS and export/update regression.
Hardening validates repository scripts and runtime export. Every job depending
on Composer tools must install the lock file before invoking them.

## Daily Readiness

Run:

```bash
php fnlla doctor
php fnlla config:doctor
php fnlla security:audit
php fnlla ops:backup-plan
php fnlla app:map
php fnlla upgrade:check --target=2.2.0
php fnlla perf:profile --iterations=5
```

`doctor` checks the local PHP/runtime prerequisites: PHP version, extensions,
storage writability, logs, cache, sessions, manifests and the vendored FNLLA
runtime surface.

`security:audit` checks deployment posture: production debug mode, HTTPS app URL,
secure/session cookie settings, request and upload limits, credentialed CORS,
Content Security Policy and cache serializer policy.

`config:doctor` is the shorter environment sanity check. It catches the mistakes
that usually waste deployment time: bad `APP_URL`, invalid `ASSET_URL`, Redis
enabled without `ext-redis`, production debug and missing trusted hosts.

These commands support JSON output for CI:

```bash
php fnlla doctor --json
php fnlla config:doctor --json
php fnlla security:audit --json
php fnlla security:audit --strict
```

`--strict` makes warnings fail the security audit. Use it for production release
pipelines once environment variables are fully defined.

`ops:backup-plan` emits a redacted backup and restore plan for the current
environment. Use `--verify --output=framework/backup-plan.json` when a
deployment pipeline needs to archive the runbook as local evidence and fail on
missing backup include paths.

`project:acceptance` runs framework-base smoke checks for the current project:
version/runtime files, writable storage and in-process HTTP probes for `/`,
`/api/health` and `/maintenance`. It is the checkpoint to run after
`make:project`, after restore to staging and before product-specific E2E tests.

`perf:profile` records local command timings, in-process HTTP probe timings,
repository footprint and peak memory. Run `php fnlla perf:profile --write-baseline`
before a performance-sensitive change and `php fnlla perf:budget --max-regression=20 --max-regression-ms=1000`
after the change to catch p95 regressions before release while avoiding noisy
microbenchmark false positives.

`app:map` and `upgrade:check` are especially useful before a major release. They
make route/controller/view topology and migration readiness machine-readable.

## Commercial Product Handover Evidence

For a downstream commercial product, keep release evidence outside Git unless it
is intentionally part of a public release. A normal handover pack should include
summaries of:

- `php fnlla project:acceptance --json`;
- `php scripts/test.php`;
- `php scripts/lint.php`;
- `php fnlla security:audit --strict`;
- `php fnlla ops:backup-plan --verify`;
- `php fnlla perf:budget`;
- `/api/health` after deployment.

Do not commit generated evidence files unless the project has a deliberate
compliance reason to version them.

## Release Preparation

Run the full local release gate:

```bash
php fnlla release:prepare
php fnlla release:prepare --major --target=2.2.0
```

The command runs:

- full test suite
- syntax lint
- integrated FNLLA runtime validation
- version manifest validation
- release metadata validation
- static analysis baseline
- strict security audit
- bootstrap/cache cleanup
- CycloneDX SBOM generation
- SHA-256 checksum generation

Artefacts are written under `dist/release/`:

- `fnlla-sbom.cdx.json`
- `SHA256SUMS`
- `fnlla-release-manifest.json`

Tag pushes attach those three files to the GitHub Release after the full release
gate and export regression jobs pass.

Release manifest signing is enabled only when the release owner has approved a
signing key policy and configured both values:

- `RELEASE_SIGNING_KEY`
- `RELEASE_SIGNING_KEY_ID`

The manifest then records the HMAC algorithm, key id, signing timestamp and
payload hash. Leave the key empty for local unsigned manifests.

With `--major`, FNLLA also writes:

- `major/fnlla-app-map.json`
- `major/fnlla-upgrade-plan.json`
- `major/fnlla-ai-review-pack.json`

`dist/` is ignored by Git so source releases stay clean unless a maintainer
explicitly attaches generated artefacts to a GitHub release.

## Source Tree Cleanliness

The committed source and distribution artifacts must not contain:

- `dist/`;
- cache files under `storage/framework/cache/`;
- queue payloads under `storage/framework/queue/`;
- session files under `storage/framework/sessions/`;
- logs under `storage/logs/`;
- generated backup plans under `storage/framework/`;
- local `.env` files;
- database dumps, uploaded client files or staging artefacts.

Only placeholder `.gitignore` files belong in versioned persistent storage
directories. Local working copies may contain real runtime state; do not delete
that state to satisfy a packaging checklist. Inspect `git status` and archive
entries instead, using the distribution exclusions described above.

Individual artefact commands are also available:

```bash
php fnlla release:sbom
php fnlla release:checksums
php fnlla release:manifest
php fnlla release:sbom --output /path/to/fnlla-sbom.cdx.json
php fnlla release:checksums --output /path/to/SHA256SUMS
```

`release:prepare` also reports a small risk label: `low`, `medium` or `high`.
It is intentionally blunt: failed validation is high risk, larger major-release
plans are medium risk, and clean validated releases are low risk.

## GitHub Actions Gate

The repository ships a GitHub Actions release gate at `.github/workflows/fnlla-release-gate.yml`.

It runs on pushes to `main`, pull requests to `main`, version tags and manual dispatch. The matrix covers `ubuntu-latest`, `macos-latest` and `windows-latest`, with PowerShell Core as the shared shell for repository scripts.

The gate checks docs, runtime contract, version manifest, release metadata, fast
tests, `doctor`, strict `security:audit`, verified backup-plan generation,
`project:acceptance`, performance budget, lint, release artefacts, runtime
publish and ecosystem audit. A second matrix job runs the slower export/update
regression suite across the same operating systems and keeps the 2.0.3 export
path visible as historical upgrade evidence while the current target moves
forward.

## Branch Protection

Protect `main` in GitHub before publishing production releases.

Required checks:

- `Release gate (ubuntu-latest)`
- `Release gate (macos-latest)`
- `Release gate (windows-latest)`
- `Export regression (ubuntu-latest)`
- `Export regression (macos-latest)`
- `Export regression (windows-latest)`

Require pull requests before merging into `main`, require the branch to be up to
date before merge, block force pushes and deletions, and require at least one
release-owner approval for changes touching release scripts, GitHub workflows,
manifest/version metadata, update code, security controls or runtime bundles.

## Framework Update Audit

Downstream framework updates use only the official `techayoDEV/fnlla` GitHub release channel.
The public framework website is `https://fnlla.com`; it is a product reference
and documentation entrypoint, not an alternate update source. Update checks
still validate the downloaded release manifest against the official GitHub
repository before trusting cached source.

Useful commands:

```bash
php fnlla framework:update --check
php fnlla framework:update --dry-run
php fnlla framework:update --apply
php fnlla framework:update --dry-run --json
```

`--dry-run` writes a machine-readable file-change report before apply. By default it is stored at `storage/framework/updates/fnlla/dry-run-report.json`.

Framework update audit events are JSON lines stored at `storage/logs/framework-update.log` by default. The log records check, dry-run, apply, conflict, rejected-source and failed update events without storing raw secrets.

## Observability

FNLLA records lightweight request observability without external dependencies:

- every response receives `X-Request-Id`
- response timing can be exposed with `X-Response-Time`
- structured access logs are written through the redacting JSON logger
- local request metrics are stored in `storage/framework/metrics.json`

Environment controls:

```env
OBSERVABILITY_ACCESS_LOG_ENABLED=true
OBSERVABILITY_RESPONSE_TIME_HEADER_ENABLED=true
OBSERVABILITY_RESPONSE_TIME_HEADER=X-Response-Time
OBSERVABILITY_METRICS_ENABLED=true
OBSERVABILITY_METRICS_PATH=framework/metrics.json
```

Access logs include request ID, method, path, route name, status, duration, IP
and user agent. Sensitive fields still pass through the logger redaction policy.

Security events are written separately to `storage/logs/security.log` when
`SECURITY_EVENT_LOG_ENABLED=true`. The first small event set covers CSRF
failures, throttling blocks and trusted-host rejections.

## Health Levels

The health endpoint supports three simple depths:

```bash
curl /api/health?level=live
curl /api/health?level=ready
curl /api/health?level=deep
```

`live` is the smallest ping. `ready` is the normal default. `deep` includes
dependency, storage, cache, queue and migration detail for operator review.

## Public API Lock

FNLLA keeps a tiny public API lock at `docs/PUBLIC-API.lock.json`. Refresh it
after intentional API changes:

```bash
php fnlla api:lock
```

The lock is deliberately small. It protects documented helpers and core CLI
commands without turning every internal class into public contract.

The local metrics file is intentionally small and dependency-free. It is useful
for single-node and staging deployments. Larger multi-node deployments should
replace it later with a Prometheus/OpenTelemetry adapter while keeping the same
request-observer boundary.

## Maintainer Runbook

Release:

1. Confirm `CHANGELOG.md`, `VERSION`, `MANIFEST.json` and runtime metadata are aligned.
2. Run `php scripts/build-docs.php --check`.
3. Run `php fnlla ops:backup-plan --output=framework/backup-plan.json`.
4. Run `php fnlla security:audit --strict`.
5. Run `php fnlla release:prepare`.
6. Review `dist/release/fnlla-sbom.cdx.json`, `dist/release/SHA256SUMS` and `dist/release/fnlla-release-manifest.json`.
7. Push the approved commit and wait for the full remote matrix, then obtain
   separate tag/publication approval. Local validation alone is insufficient.

Rollback:

1. Keep the previous tag and release artefacts available.
2. Re-deploy the previous validated source package.
3. Clear generated bootstrap caches with `php fnlla optimize:clear`.
4. Re-run `php fnlla doctor` and `php fnlla security:audit`.

Update recovery:

1. Read `storage/framework/updates/fnlla/dry-run-report.json`.
2. Read `storage/logs/framework-update.log`.
3. Stop application writers and run `php scripts/rollback-framework-update.php`
   in the affected project to recover an interrupted managed-file transaction.
   Do not delete its journal or overwrite project-owned files from Git blindly.
4. Verify recovery, then run `php fnlla framework:update --dry-run` before any
   second apply. See [Recovery](RECOVERY.md) for the database/external-effect boundary.

Backup and restore:

1. Back up `.env`, `storage/`, uploaded project files and the application database before deployment.
2. Keep runtime data out of code release assets. Separately define backup and
   reconciliation policy for pending jobs, audit logs and application data;
   never discard pending work merely because it is stored under `storage/`.
3. Generate `php fnlla ops:backup-plan` and attach the redacted plan to internal deployment evidence.
4. Restore database and uploaded files before warming caches.
5. Run `php fnlla migrate:status` after restore.

Common production failures:

- 500 after deploy: run `php fnlla doctor`, check PHP version/extensions and inspect `storage/logs/app.log`.
- Broken assets: confirm `ASSET_URL` and `public/vendor/fnlla-runtime/` match the deployed host.
- Login/session loops: confirm HTTPS, `SESSION_SECURE`, cookie domain and trusted proxy settings.
- Framework update blocked: use the dry-run report and resolve conflicts manually before apply.
