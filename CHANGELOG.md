# FNLLA Changelog

All notable FNLLA framework changes should be recorded here before public
release tags are cut.

## Unreleased

- Consolidate project command and recovery docs into the operations guide, move
  framework policy documents out of the repository root, place Windows helper
  launchers under `scripts/windows/`, and refresh Project Setup visuals.

- Retry transient Windows private-state rename failures while holding the writer
  lock; preserve the previous JSON state if atomic publication cannot complete.

- Reject upload MIME validation when Fileinfo or file detection is unavailable;
  never fall back to client-supplied MIME metadata. CI explicitly enables Fileinfo.

- Use managed Fileinfo objects for MIME detection without PHP 8.5 deprecations;
  cover spoofed client MIME headers with a regression test.
- Install locked development tools in hardening CI before repository tests.
- Consolidate release acceptance into the operations guide, correct update
  recovery instructions and distinguish clean distribution from live runtime data.

Target release: `2.2.0` (stable, not yet published). The architecture
changes and unfinished acceptance are tracked in `docs/MODERNIZATION-STATUS.md`.
This target replaces the proposed beta. Do not advertise this workspace as a
new stable release until the supported release scope passes its acceptance gates.

- Atomic config/route cache publication preserving working files on failure;
  route/profile metadata now travels together, with legacy cache read support.
- Shared, validated application generators in Core, Complete and package preview;
  new exports use App namespaces and refuse existing or unsafe destinations.
- Strict migration options, named CLI connections and explicit non-local `--force`.
- Removed/mismatched application accounts no longer pass authentication checks;
  corrupt identities fail closed and login installs identity only after rotation.
- Bounded request capture, strict Content-Length and nearest-untrusted-hop proxy
  resolution; forwarded protocol must be a single canonical value.
- Release preparation labels skipped validation as unknown risk, not low risk.
- Integrated starter creation no longer prompts automatically; the advanced
  chooser and explicit core-only export remain compatible.
- Full projects include all modules enabled by default. Project Setup and Panel
  Settings can disable unwanted modules without changing existing projects.
- Persistent sessions enforce server-side idle and absolute expiry, reject invalid
  timestamp state and fail when persistent storage cannot start.
- Redis sessions validate IDs and serialize reads/writes with expiring token-owned
  locks; stale owners cannot overwrite, delete or refresh successor state.
- Upgrade regression starts from the official 2.1.3 release. Framework policy
  documents and UI assets are tracked; metadata refresh joins the rollback journal.
  Only unchanged, hash-matched legacy framework tests are retired; application
  tests and locally modified test files remain untouched.
- Developer session refresh preserves the named account instead of selecting the
  first configured account; removed accounts cannot inherit another identity.
- Maintainer release checks require real PHPUnit; preparation no longer deletes
  queues, sessions, logs or caches from a working application.
- Full exports ship focused application smoke tests instead of framework-internal
  suites. Runtime updates preserve application tests, and README examples use the
  exported version rather than a hard-coded historical release number.
- Consolidated architecture, migration and recovery documentation with neutral
  examples; removed obsolete stage reports and application-specific instructions.
- Release and CI documentation hygiene checks detect broken Markdown links,
  workstation paths and common credential patterns without printing matched values.
- Source archive, SBOM and checksum filters exclude nested environment secrets,
  private keys, database dumps and generated artifacts.

- Named database managers with explicit PDO registration, transaction-safe
  purge, and migration schema/ledger binding verified against real MySQL.
- Opt-in, bounded developer request history with redaction, retention,
  permissions and CSRF protection; excluded from Core/plain.
- Private layout and base stylesheet are now framework-owned during upgrades;
  public application views, styles, routes, `.env` and data remain project-owned.
- Upgrade source scanning skips the active transaction journal/lock.
- Offline file-recovery drill with integrity verification and explicit RPO/RTO;
  synthetic checks are distinct from application-owned production recovery.

## 2.1.3 - 2026-09-04

### Added

- `tech-debt:update` for a self-checking technical-debt report, release-gate
  freshness checks and a generated Markdown snapshot in
  `docs/TECH-DEBT-AND-FUTURE-PROOFING.md`.

### Changed

- README and documentation now position FNLLA as an AI-ready web product
  framework with a Developer Operations Panel, while keeping the AI claims
  limited to the shipped local runtime, review commands and opt-in Fionn bridge.

## 2.1.2 - 2026-08-31

### Added

- Developer panel analytics and heatmap surfaces backed by FNLLA observability
  data, keeping GA4 and Microsoft Clarity as optional external adapters.
- Project leadership/system information configuration for discreet, confirmed
  responsibility disclosure across public, administrator-only and disabled
  visibility modes.
- Developer panel documentation, About/System information, profile, 2FA,
  integration settings and storage installer surfaces.
- Developer workspace Kanban with modal task editing, participants, sub-tasks,
  status movement and compact Jira-style controls.

### Changed

- Developer panel navigation, policy boundary, operations, integrations,
  analytics and profile views now use a more consistent shared layout.
- Project exports now carry the developer panel, observability and leadership
  contracts without maintainer-only test or template residue.
- Runtime UI controls, sidebar typography, navbar actions and Kanban card
  spacing have been tightened for denser operational use.

### Fixed

- Release-readiness tests now assert the active framework version dynamically
  instead of hardcoding the previous patch version.

## 2.1.1 - 2026-08-28

### Added

- `project:acceptance` for machine-readable project-base smoke checks after
  `make:project` export and before commercial product work begins.
- In-process HTTP performance probes for `/` and `/api/health`.
- `ops:backup-plan --verify` readiness checks and an explicit restore command.
- `.env.full.example` as the complete environment reference beside the shorter
  `.env.example` starter.
- `docs/ENVIRONMENT.md` and generated `docs/environment.html` for environment
  layers, client preview and the Fionn bridge boundary.

### Changed

- Release gate now runs project acceptance and verified backup-plan generation.
- Project export smoke coverage now proves the generated project can run the
  acceptance command.
- README is now a concise project front door with version badges, governance
  links and a framework cover image.
- Documentation now has a dedicated map in `docs/README.md` and clearer links
  from README into build, public API, production, environment, operations,
  business-reference, performance and AI-context guides.
- Markdown documentation has been expanded around downstream project start,
  commercial release evidence, security review and framework/product
  boundaries.
- Runtime AI now has an opt-in Fionn HTTP bridge contract with endpoint policy
  checks, redacted context forwarding, provider selection through
  `runtime_ai()` and strict security-audit coverage.
- Exported application surface tests now validate the configured application
  name instead of assuming the framework repository name after `.env` is copied.

### Fixed

- Developer panel setup now preserves bcrypt hashes when replacing existing
  `.env` keys, so fresh exported projects no longer redirect to a hidden `404`
  after the first developer password is created.
- Fresh exported projects now render first-run developer onboarding directly at
  `/`, keeping `/maintenance` reserved for the maintenance/client-preview
  surface once that mode is explicitly configured.

## 2.1.0 - 2026-08-28

### Added

- Business reference blueprint for login, roles, CRUD, dashboard, forms,
  mail/log delivery, migrations, seeders, queue, health and client preview.
- `db()` helper, query-builder pagination and offset support for business list
  screens.
- `ops:backup-plan` for redacted backup and restore runbook generation.
- Production checklist, 2.0.x-to-2.1.0 upgrade notes and performance baseline
  policy.

### Changed

- Public API contract now names stable minor-release surfaces and internal
  boundaries more explicitly.
- Performance profiling now includes `make:project` export timing.
- Release gate now includes strict security audit, backup-plan generation,
  performance budget evidence and 2.0.3 export readiness.
- `release:prepare` now runs `security:audit --strict`.

### Security

- Production readiness now treats strict security audit as a release blocker.
- Backup plans redact database credentials and exclude cache, session, queue and
  log residue from recovery state.

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
