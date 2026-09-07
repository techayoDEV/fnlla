# FNLLA Changelog

All notable FNLLA framework changes should be recorded here before public
release tags are cut.

## 2.2.0

Publication dates and downloadable assets are recorded in GitHub Releases.

### Release Summary

FNLLA 2.2.0 brings application foundations and developer operations into one
integrated web framework, built on PHP. Start with your own project identity,
configure private developer access, and build the application in a clean export.

#### Highlights

- **An integrated starter:** Project Setup, a private Developer Panel, diagnostics,
  optional developer tooling and controlled file-based framework updates.
- **A focused PHP foundation:** routing, dependency injection, validation, PDO data
  access, migrations, authentication, sessions, cache, queues and mail.
- **Safer setup and recovery:** no default accounts, CSRF-protected onboarding,
  password recovery, session rotation and revocation after password changes.
- **A consistent UI:** outline identity, self-hosted typography, light/dark surfaces
  and regression-tested private/public layouts. Your application's brand remains yours.
- **AI connections by choice:** a built-in gateway to FIONN AI by TechAyo, plus
  optional OpenAI API and Anthropic API adapters. Accounts, API access and provider
  configuration are separate; no language model is bundled.

#### Start A Project

Use PHP 8.3 or later. Download the `fnlla-source.zip` attachment and verify its
SHA-256 against `fnlla-downloads.sha256`. Extract it, then run from the source directory:

```sh
php fnlla make:project ../my-app "My App"
cd ../my-app
composer install
php scripts/test.php
php scripts/lint.php
```

Open the project locally to complete Project Setup. Serve only `public/` in
production and follow the
[installation guide](https://github.com/techayoDEV/fnlla/blob/v2.2.0/docs/STARTING-A-NEW-PROJECT.md).
For a deliberate core-only installation, add `--profile=plain` to `make:project`.

#### Upgrading From 2.1.3

Back up the application and stop traffic. Use the new release's updater, with the
existing application as its explicit target; do not use the old 2.1.3 updater:

```sh
php ../fnlla-2.2.0/fnlla framework:update --project=. --release-tag=v2.2.0 --dry-run
php ../fnlla-2.2.0/fnlla framework:update --project=. --release-tag=v2.2.0 --apply
```

Review the [migration guide](https://github.com/techayoDEV/fnlla/blob/v2.2.0/docs/MIGRATION.md):
non-local migrations/seeding require explicit confirmation, MIME validation requires
Fileinfo, and existing demo accounts must be reviewed by the application owner.
Customized files must be reconciled; file rollback does not reverse database changes
or external business operations.

#### Scope And Support

The supported runtime is ordinary isolated PHP requests. Long-lived HTTP worker
isolation is not supported. Composer package mode remains a bundled-path preview,
not a public registry offering. Email recovery requires a working mail transport
and queue worker; CLI recovery is a separate server-owner fallback.

FNLLA is MIT-licensed. Security maintenance targets the latest 2.2.x patch on a
best-effort basis, without LTS or SLA guarantees. Application-specific security,
load testing and restore procedures remain deployment responsibilities. See the
[operations guide](https://github.com/techayoDEV/fnlla/blob/v2.2.0/docs/RELEASE-AND-OPERATIONS.md).

Release attachments include a source ZIP, SBOM, checksums and an exact-commit CI
receipt. Checksums are integrity checks, not a publisher signature or security
certification. Report vulnerabilities through
[private GitHub reporting](https://github.com/techayoDEV/fnlla/security/advisories/new).

Created and maintained by **TechAyo**. Lead Developer / Product Manager: **Marcin Kordyaczny**.

### Release Acceptance

- Make queue lease/retry tests independent of one-second scheduling assumptions;
  simulate expiry in private fixtures while retaining stale-worker rejection.
- Require confirmed public, stable GitHub release metadata for framework updates;
  remove the bare-tag fallback during API failures and validate source/tag versions.
- Accept an absent/empty CGI content length from Nginx/PHP-FPM while retaining
  strict HTTP length validation and bounded body reads.
- Replace automatic tag publication with a manually requested draft, exact-commit
  checks of all three CI workflows and promotion of the accepted source ZIP.
  Refuse existing drafts/releases and never overwrite release assets.
- Add source-archive installation checks and a disposable HTTPS/Nginx/PHP-FPM
  acceptance runner covering setup, cached login, CSRF, recovery, session revocation
  and serving-process restart with timestamp-disabled OPcache.
- Make private vulnerability reporting the primary security contact route and
  document the latest-patch, best-effort maintenance policy for 2.2.x.

### Framework And Developer Experience

- Recognize unchanged FNLLA brand assets from the published 2.1.3 baseline during
  upgrades; retain conflicts for customized marks and test both cases against
  the original release.
- Correct upgrade readiness checks to accept documented provider integrations
  and match the business reference to the current source edition.
- Validate source archives independently of the Git checkout and initialize empty
  test-storage fixtures when private directories are absent. Provision macOS PHP
  explicitly before release-gate configuration.

- Fix private preview and update stylesheet ownership, dark cookie/Kanban
  surfaces and form boundaries. Add browser regression checks against fresh
  exports in both themes and four viewport widths.
- Generate framework/runtime palette values from the brand tokens; use shared
  supporting type and canonical compact wordmarks. Remove retired solid marks
  from exports and correct office-template minimum logo sizes.
- Put application identity first on the starter home page, remove the fictitious
  terminal command and distinguish FNLLA's built-in tools from optional FIONN AI
  access. Derive upgrade targets from version metadata and show live main-branch
  CI status separately from the unreleased source candidate.

- Align runtime and full-starter UI with the outline brand, neutral light/dark
  surfaces, semantic status colours and visible keyboard focus. Keep Plain's
  system-font footprint. Simplify the 01 signature and keep it outside working forms.
- Add editable vector artboards, layered PSD handoffs and four branded Word
  templates. Keep all design-tool files outside starter and source distributions.
- Complete missing assertion support in the dependency-free test harness and
  prevent an absent expected exception from accepting its own assertion failure.

- Refresh the README with an identity-led cover, a full-starter workflow and an
  AI service-boundary diagram, reused in the matching Markdown guides. Keep
  the essential claims in text and alt descriptions. Use light-grey social
  information bands and an original, static 01 motif built from FNLLA's bytes.
- Refine the 2.2.0 brand palette without Citron, brighten accessible success/error
  text and introduce a grouped binary campaign pattern. Use lowercase Mono
  domains/email and Mono SemiBold for the FIONN AI display name.
- Ship real 400/600 faces for both brand families, align project-shell weights,
  and include the new Mono face in full starter exports. Keep plain lightweight.
- Add canonical framework lead credits to About FNLLA and public API metadata,
  separate from application ownership. Use Anthropic API as the display label
  for the direct Claude API without changing driver IDs or request contracts.

- Standardise the brand kit on six outline colour treatments, preserve every
  master contour and retire filled silhouettes. Add restrained binary campaign
  fields, scannable social-cover QRs and stronger FIONN AI positioning with
  explicit developer-account, API access and service-memory boundaries.
- Retain TechAyo contact details and dual framework/creator
  print QRs. Clarify that local reference lookup is not a local AI model and that
  `ai:ask` can send the question to an explicitly enabled external provider.
- Align public naming around FIONN AI by TechAyo, its built-in gateway and
  optional OpenAI API / Anthropic API integrations. Keep driver and env
  identifiers compatible; use Claude Platform's documented Bearer authentication
  and test refusal, stop-reason and multi-block text response handling.

- Add opt-in OpenAI API (Responses) and Anthropic API (Messages) text-generation adapters,
  bounded HTTPS transport, explicit model/key settings and protected panel controls.
  Keep diagnostics local, reject unknown drivers and never report unknown AI cost as free.
- Tighten FIONN AI host allowlists, reject unsafe tokens, block redirects, bound
  responses and avoid exposing upstream error details.
- Adopt the AI-ready web framework positioning with a PHP foundation; distinguish
  local assistance, the separate FIONN AI brain and cloud provider integrations.
- Enrich the 14-page brand guide with origin and creator credits, introduce
  landscape A3/A4 campaigns and platform-specific social artwork, and refresh
  the README cover. Self-host Space Grotesk and JetBrains Mono in the full starter.

- Remove the default demo administrator and shared factory password; require
  explicit non-local seeding confirmation and validate seeder input before execution.
- Align PHPStan's distributed and active configuration, adding database source
  analysis; preserve Windows CLI exit codes and argument expansion settings.
- Protect future module storage from accidental Git inclusion in both starter
  profiles; keep actual data and historical migration identities unchanged.
- Refresh the business reference and performance policy for edition 2.2.0,
  correct route-to-gate mismatches, and use neutral ownership placeholders.
- Align branding to edition 2.2.0 and add reproducible A3/A4 vector print artwork
  with a verified QR link to fnlla.com.

- Retire generated HTML documentation, its local routes and build pipeline;
  retain Markdown guides and enforce documentation hygiene in CI and release checks.
  Consolidate brand assets and replace the outdated brand guide with a maintainable
  visual standard aligned with FNLLA's actual product identity.
- Replace the runtime's outdated Open Graph image with the maintained artwork
  using the official fnlla.com domain.

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

Remaining architecture work and the supported release scope are tracked in
`docs/MODERNIZATION-STATUS.md`. Package mode is a bundled-path preview; public
registry installation, isolated long-lived HTTP workers and comparative performance
claims are not part of the supported 2.2.0 scope. Review `docs/MIGRATION.md` for
breaking operational changes before updating an existing application.

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
  limited to the shipped local runtime, review commands and opt-in FIONN AI bridge.

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
  layers, client preview and the FIONN AI bridge boundary.

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
- Runtime AI now has an opt-in FIONN AI HTTP bridge contract with endpoint policy
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
