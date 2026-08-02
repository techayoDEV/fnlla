# FNLLA Technical Debt and Future Proofing

This document tracks known future-facing improvement areas for the maintained
FNLLA repository. It is not a release blocker list. It is a practical backlog
for keeping the framework small while reducing long-term operational risk.

## Implemented Hardening

- `ProcessRunner` now runs process commands with argv boundaries, timeout
  enforcement and output limits.
- File cache writes JSON payloads by default and still reads safe legacy PHP
  serialized cache files during migration.
- File cache increments are protected by per-key file locks on the local host.
- Request capture rejects oversized bodies with an explicit 413 response path.
- Uploaded files expose explicit size and MIME validation before storage.
- Response headers reject invalid names and CRLF/null-byte values.
- Client-provided request IDs are normalized before being returned or logged.
- Logs redact configured sensitive keys and rotate when the active file exceeds
  the configured size.
- Queue storage is behind `QueueStoreInterface`; the default implementation is
  still the local file adapter.
- `framework:update --json` emits machine-readable reports for CI.
- Release downloads validate FNLLA release identity through `MANIFEST.json` and
  `VERSION` in addition to source-root shape.
- The local test runner supports `--filter`, and `scripts/static-analysis.php`
  provides a dependency-light static analysis baseline with optional PHPStan or
  Psalm delegation.
- Production-style bootstrap caches are available through `config:cache`,
  `route:cache`, `optimize` and `optimize:clear`.
- `optimize:warm` now builds bootstrap caches, an asset manifest and an optional
  OPcache preload file for production deployments.
- `perf:profile` and `perf:budget` provide local performance baselines and p95
  regression checks.
- `ai:context` generates a redacted, local-only review context pack for release
  workflows without raw secrets or source-file contents.
- `app:map` exposes route/controller/view topology for audits, onboarding,
  migration planning and tool-assisted review.
- `upgrade:check`, `upgrade:plan` and `upgrade:apply` provide a local-first
  major-release readiness workflow.
- `ai:review-pack`, `ai:upgrade-brief` and `ai:redact` extend local review
  without external calls.
- `release:prepare --major` adds docs sync, security posture, upgrade readiness
  and app-map evidence to the release gate.
- Route cache export rejects closure/object route handlers so cached production
  routes are deterministic and source-reviewable.
- HTTP session state is lazy: API/static-style requests no longer start session
  state until flash, CSRF, auth or session helpers are actually used.
- The health endpoint caches expensive readiness checks briefly while preserving
  request-specific IDs, method, path, IP and timestamp per request.
- The public development router rejects dotfiles, null bytes, encoded traversal
  attempts, Windows alternate-data-stream syntax and static-file symlink escapes.
- Credentialed wildcard CORS is rejected; deployments using cookies or auth
  headers must list explicit allowed origins.
- Trusted host enforcement can reject requests whose Host header is outside the
  configured deployment boundary.
- The mail surface validates recipients, subjects and native headers before a
  form notification can leave the application boundary.
- `scripts/benchmark.php --production` applies a production-like local
  environment and builds bootstrap caches before measuring CLI, HTTP and export
  performance.
- `doctor`, `security:audit`, `release:prepare`, `release:sbom` and
  `release:checksums` provide operator-facing readiness, security posture and
  release supply-chain workflows.
- Request observability now includes structured access logs, local JSON metrics
  and an optional response-time header.

## Priority 1: Runtime Safety

- Add more upload examples to starter docs and exported project tests.
- Add request body streaming support for endpoints that intentionally accept
  large files without loading them into memory.
- Add optional trusted-host enforcement for deployments that terminate TLS or
  route multiple domains through the same PHP entrypoint.

## Priority 2: Production Scalability

- Add a Redis cache store adapter when FNLLA is ready to support optional
  extension-backed infrastructure. The cache contract is ready, but no Redis
  dependency is bundled.
- Add a distributed queue store adapter with visibility timeouts and retry
  metadata. `QueueStoreInterface` is ready; only the local file adapter ships by
  default.
- Add migration transaction capability detection so migrations can opt into safe
  transactional execution where the storage engine supports it.
- Add Redis or Memcached cache drivers for horizontally scaled deployments. The
  current JSON file cache is secure and simple, but not a multi-node cache.
- Add an external session store adapter for multi-worker or multi-node hosting.
  The lazy session boundary is ready, but storage is still local by default.
- Add generated build fingerprints when FNLLA grows a first-class asset bundling
  pipeline. The current production manifest removes hot-path `filemtime()`
  checks for files already present under `public/`.

## Priority 3: Update And Distribution Workflow

- Add signed release metadata when the release process has a signing key and
  verification policy.
- Separate maintainer-only scripts from exported project scripts more visibly in
  docs and command output.
- Publish generated SBOM and checksum artefacts alongside public GitHub
  releases after the maintainer release process decides where artefacts should
  be attached.
- Add signed release metadata when the signing key and verification policy are
  finalized.

## Priority 4: Observability

- Add a stable event naming convention for framework events emitted by auth,
  maintenance unlocks, update checks and queue failures.
- Add OpenTelemetry-compatible hooks once FNLLA has a supported adapter policy.

## Priority 5: Developer Experience

- Expand the local test harness with data providers and richer assertions while
  keeping the no-Packagist development path.
- Add generated API documentation for public framework classes and helpers.
- Add examples for custom middleware, queued jobs, external mail transports and
  storage disks.
- Promote `perf:budget` into CI once a stable hosted runner baseline has been
  established for the public release process.

## Current Hardening Notes

- Process execution in framework update, release download, runtime sync and
  project export paths now goes through `Fnlla\Php\Support\ProcessRunner`.
- Request IDs accepted from clients are restricted to a short safe character set.
- File cache reads disallow PHP object hydration from serialized cache payloads.
- Response headers reject invalid names and line-break/null-byte values.
