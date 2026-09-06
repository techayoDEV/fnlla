# FNLLA Maintainer Workflow

Before every GitHub release, review and update the documentation for changed
behavior. Cover first-run setup, configuration defaults, public API/CLI changes,
upgrade steps, security/operational requirements and known limitations. Update
release notes for the actual approved version. Generated HTML must match Markdown.

Run `php scripts/build-docs.php`, `php scripts/build-docs.php --check`,
`php scripts/check-docs.php`, `php scripts/check-modernization.php` and the
relevant tests before release preparation. `php fnlla release:prepare` enforces
documentation synchronization, hygiene and ledger validation for maintainer
releases, including `--skip-tests`.
Automated checks do not replace a human review of documentation completeness.
Do not publish, tag or push without explicit authorization.

Use `resources/modernization-tasks.json` as the sole live architecture task ledger
and `docs/MODERNIZATION-STATUS.md` as its narrative entrypoint. Consolidate design
decisions in `docs/ARCHITECTURE-ROADMAP.md`; preserve every unfinished acceptance
criterion when removing obsolete reports. Public documentation must be neutral:
no application incident records, private paths, credentials or deployment evidence.
Retain legal attribution and actual public API/package identifiers.

Fresh Complete exports must have no preconfigured developer credentials and show
local Project Setup before sign-in. Core / plain has no Developer Panel setup.
Keep setup security restrictions; never erase real accounts to reveal onboarding.
Label QA previews with preconfigured accounts clearly and provide a separate
unconfigured preview when demonstrating first-run behavior.
