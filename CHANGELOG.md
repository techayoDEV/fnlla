# FNLLA Changelog

All notable FNLLA framework changes should be recorded here before public
release tags are cut.

## 2.0.3 - 2026-08-28

### Added

- Versioned project-export templates under `resources/project-templates/`.
- Local AI prompt registry and eval fixtures for release and upgrade workflows.
- Optional strict static-analysis configuration for maintainers.

### Changed

- Client preview copy now clearly describes password-protected client review mode.
- Local static analysis now catches named functions with declared return types
  that neither return nor intentionally throw.
- The `/api/profile` endpoint now returns its declared capability payload.
- Release operations now document GitHub release assets, keyed manifest signing,
  branch protection and maintainer recovery runbooks.
- Framework update checks now reject custom update sources and keep the GitHub
  release channel explicit.

### Removed

- Retired the temporary enterprise todo document and generated docs route.

## 2.0.2 - 2026-08-28

- Stable public FNLLA framework release.

## 2.0.1 - 2026-08-26

- Stable FNLLA framework maintenance release.

## 2.0.0 - 2026-08-02

### Added

- `app:map` generates a route/controller/view/application map for audits,
  onboarding, AI review and upgrade planning.
- `upgrade:check`, `upgrade:plan` and `upgrade:apply` provide a local-first
  upgrade readiness workflow for major releases and downstream projects.
- `ai:review-pack`, `ai:upgrade-brief` and `ai:redact` extend FNLLA's local AI
  workflow without sending data to an external service.
- `perf:baseline:update` and `perf:compare` make performance baselines explicit
  in release and CI workflows.
- `release:prepare --major` runs additional major-release readiness checks and
  emits app-map, upgrade-plan and AI-review artifacts under `dist/release/`.

### Changed

- Major-release preparation now treats migration documentation, docs sync,
  upgrade readiness and application mapping as first-class release concerns.
- Performance comparison supports both percentage and absolute millisecond
  thresholds to reduce false positives on tiny local timings.

### Security

- AI-facing artifacts remain local-only by default and defensively redact
  sensitive-looking keys.
- Major release preparation includes security posture evidence in the gate.

## 1.1.0 - Current Stable Line

- Production cache warmup.
- JSON file cache serializer.
- Release gate, SBOM and checksum generation.
- Security audit, doctor command and local observability.
- Framework update and downstream project export hardening.
