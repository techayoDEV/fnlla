# FNLLA Changelog

All notable FNLLA framework changes should be recorded here before public
release tags are cut.

## 2.0.0 - Planned Major Release

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
