# FNLLA Release And Operations

This document describes the operational commands that keep FNLLA release-ready,
observable and easier to audit in business deployments.

## Daily Readiness

Run:

```bash
php fnlla doctor
php fnlla security:audit
php fnlla app:map
php fnlla upgrade:check --target=2.0.0
php fnlla perf:profile --iterations=5
```

`doctor` checks the local PHP/runtime prerequisites: PHP version, extensions,
storage writability, logs, cache, sessions, manifests and the vendored FNLLA
runtime surface.

`security:audit` checks deployment posture: production debug mode, HTTPS app URL,
secure/session cookie settings, request and upload limits, credentialed CORS,
Content Security Policy and cache serializer policy.

Both commands support JSON output for CI:

```bash
php fnlla doctor --json
php fnlla security:audit --json
php fnlla security:audit --strict
```

`--strict` makes warnings fail the security audit. Use it for production release
pipelines once environment variables are fully defined.

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
php fnlla release:prepare --major --target=2.0.0
```

The command runs:

- full test suite
- syntax lint
- integrated FNLLA runtime validation
- version manifest validation
- release metadata validation
- static analysis baseline
- bootstrap/cache cleanup
- CycloneDX SBOM generation
- SHA-256 checksum generation

Artefacts are written under `dist/release/`:

- `fnlla-sbom.cdx.json`
- `SHA256SUMS`

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
php fnlla release:sbom --output /path/to/fnlla-sbom.cdx.json
php fnlla release:checksums --output /path/to/SHA256SUMS
```

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
