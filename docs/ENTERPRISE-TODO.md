# FNLLA Enterprise To-Do

This is the focused backlog after the architecture and hardening review. Each task should stay small, reviewable and easy to verify.

## Done Rule

A task is done only when code, tests, docs and release checks agree. Do not ship half-finished safeguards.

## Top 15 Tasks

1. Add GitHub Actions for `composer test`, `composer lint`, `php scripts/build-docs.php --check` and `php fnlla release:prepare`.
2. Add branch protection documentation for `main`, including required checks and release-owner approval.
3. Attach SBOM, SHA-256 checksums and release manifest to every GitHub release.
4. Add signed release manifests after a signing key policy exists.
5. Add an update dry-run report that lists exact changed files before `framework:update --apply`.
6. Add an update audit log for check, apply, conflict, rejected source and completed update events.
7. Add deeper tests for blocked non-official GitHub repositories, custom API base URLs, custom clone URLs and local source paths.
8. Keep `local` as the default runtime AI driver and require explicit review before any non-local driver can answer.
9. Keep `fionn` as a reserved provider only; it may report status through `ai:providers`, but it must not call Fionn until schemas, auth, redaction, evals and operator controls are approved.
10. Add a prompt registry for reusable review, triage, release and migration prompts.
11. Add eval fixtures for `ai:ask`, `ai:triage`, `ai:explain-log`, `ai:brief` and future provider adapters.
12. Add token, cost and latency accounting fields before any remote or paid AI provider is allowed.
13. Move embedded export templates out of `MakeProjectCommand` into versioned template files with snapshot tests.
14. Add PHPStan or Psalm as an optional strict quality gate for maintainers.
15. Add a short maintainer runbook for release, rollback, update recovery, backup, restore and common production failures.

## Current Fionn Boundary

FNLLA has a reserved Fionn AI bridge, not a Fionn integration.

- Allowed now: provider status reports, configuration placeholders and tests proving the bridge is reserved.
- Blocked now: network calls, imports from the Fionn repository, training mutations, learning queue writes and secret forwarding.
- Next technical step: design stable request and response payloads outside production code, then add adapter tests before any runtime call path exists.
