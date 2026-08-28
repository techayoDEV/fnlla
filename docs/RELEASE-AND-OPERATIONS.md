# FNLLA Release And Operations

This document describes the operational commands that keep FNLLA release-ready,
observable and easier to audit in business deployments.

## Daily Readiness

Run:

```bash
php fnlla doctor
php fnlla config:doctor
php fnlla security:audit
php fnlla ops:backup-plan
php fnlla app:map
php fnlla upgrade:check --target=2.1.0
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
environment. Use `--output=framework/backup-plan.json` when a deployment
pipeline needs to archive the runbook as local evidence.

`perf:profile` records local command timings, repository footprint and peak
memory. Run `php fnlla perf:profile --write-baseline` before a performance-sensitive
change and `php fnlla perf:budget --max-regression=20 --max-regression-ms=1000`
after the change to catch p95 regressions before release while avoiding noisy
microbenchmark false positives.

`app:map` and `upgrade:check` are especially useful before a major release. They
make route/controller/view topology and migration readiness machine-readable.

## Release Preparation

Run the full local release gate:

```bash
php fnlla release:prepare
php fnlla release:prepare --major --target=2.1.0
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

The gate checks docs, runtime contract, version manifest, release metadata, fast tests, `doctor`, strict `security:audit`, backup-plan generation, performance budget, lint, release artefacts, runtime publish and ecosystem audit. A second matrix job runs the slower export/update regression suite across the same operating systems and keeps the 2.0.3 export path visible before 2.1.0 publication.

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

## Clean Source Release

Before committing source changes, clear runtime residue:

```bash
php fnlla optimize:clear
php fnlla cache:clear
```

The source tree should keep only `.gitignore` placeholders under `storage/`.
Runtime files such as sessions, cache entries, queue jobs, metrics and logs
should not be committed.

## Maintainer Runbook

Release:

1. Confirm `CHANGELOG.md`, `VERSION`, `MANIFEST.json` and runtime metadata are aligned.
2. Run `php scripts/build-docs.php --check`.
3. Run `php fnlla ops:backup-plan --output=framework/backup-plan.json`.
4. Run `php fnlla security:audit --strict`.
5. Run `php fnlla release:prepare`.
6. Review `dist/release/fnlla-sbom.cdx.json`, `dist/release/SHA256SUMS` and `dist/release/fnlla-release-manifest.json`.
7. Push the commit and signed tag only after local validation is green.

Rollback:

1. Keep the previous tag and release artefacts available.
2. Re-deploy the previous validated source package.
3. Clear generated bootstrap caches with `php fnlla optimize:clear`.
4. Re-run `php fnlla doctor` and `php fnlla security:audit`.

Update recovery:

1. Read `storage/framework/updates/fnlla/dry-run-report.json`.
2. Read `storage/logs/framework-update.log`.
3. Restore project-owned files from Git when an apply was interrupted.
4. Re-run `php fnlla framework:update --dry-run` before any second apply.

Backup and restore:

1. Back up `.env`, `storage/`, uploaded project files and the application database before deployment.
2. Do not back up generated cache, queue, session or log residue as release state.
3. Generate `php fnlla ops:backup-plan` and attach the redacted plan to internal deployment evidence.
4. Restore database and uploaded files before warming caches.
5. Run `php fnlla migrate:status` after restore.

Common production failures:

- 500 after deploy: run `php fnlla doctor`, check PHP version/extensions and inspect `storage/logs/app.log`.
- Broken assets: confirm `ASSET_URL` and `public/vendor/fnlla-runtime/` match the deployed host.
- Login/session loops: confirm HTTPS, `SESSION_SECURE`, cookie domain and trusted proxy settings.
- Framework update blocked: use the dry-run report and resolve conflicts manually before apply.
