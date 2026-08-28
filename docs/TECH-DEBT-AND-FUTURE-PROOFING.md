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
- CSP nonce support is available through `csp_nonce()` and the `{nonce}` header
  placeholder.
- `config:doctor`, `ai:explain-log`, `ai:brief`, queue retry metadata, health
  levels, release risk labels and a small public API lock are available as
  simple operational guardrails.

## Current Backlog

There are no known release-blocking technical-debt items in this snapshot.
Future work should be opened as explicit issues with owner, scope and acceptance
criteria instead of staying as vague backlog text inside the repository.

## Current Hardening Notes

- Process execution in framework update, release download, runtime sync and
  project export paths now goes through `Fnlla\Php\Support\ProcessRunner`.
- Request IDs accepted from clients are restricted to a short safe character set.
- File cache reads disallow PHP object hydration from serialized cache payloads.
- Response headers reject invalid names and line-break/null-byte values.
- Framework and runtime updates reject local sources, fork repositories and
  repository overrides; downstream updates come only from the official
  `techayoDEV/fnlla` GitHub channel.
