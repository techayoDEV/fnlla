# FNLLA Migration Guide

This guide is the authoritative place for downstream projects moving between
FNLLA major versions.

## Recommended 1.x To 2.0 Flow

For teams that want a browser-first workflow, use the built-in maintenance GUI:

1. Open `/maintenance/framework-update` from a developer-authorised local session.
2. Run **Check major readiness** in the Major upgrade safety section.
3. Review the checks, warnings, failures and upgrade plan actions.
4. Run **Apply safe actions** only when UI apply is enabled for that environment.
5. Complete any manual-review items listed by the plan.

The GUI uses the same `UpgradeAnalyzer` as the CLI. It can write the upgrade
plan and clear generated runtime residue without hand-editing files. It will not
perform manual migration review or overwrite project-owned work.

The equivalent CLI flow remains available from the project repository:

```bash
php fnlla upgrade:check --target=2.0.0
php fnlla upgrade:plan --target=2.0.0
php fnlla upgrade:apply --target=2.0.0
php fnlla app:map
php fnlla ai:upgrade-brief --target=2.0.0
```

`upgrade:apply` defaults to dry-run. Pass `--yes` only after reviewing the plan.
The command and GUI apply only actions marked `safe_to_apply`; anything that can
change application behaviour remains a manual review item.

## Public Contract To Review

Before adopting a major version, review:

- CLI commands and their machine-readable JSON schemas
- `config/` keys used by deployment scripts
- route names consumed by views or tests
- middleware aliases and route groups
- helper behaviour for `asset()`, `route()`, `config()`, `cache()`, `auth()` and
  session/CSRF helpers
- generated files under `storage/framework/cache/`
- exported-project lock metadata under `.fnlla/framework-lock.json`

## AI-Assisted Migration

FNLLA does not send application data to an AI provider. Generate local artefacts
and decide explicitly what to share:

```bash
php fnlla ai:review-pack --target=2.0.0
php fnlla ai:redact --input storage/framework/cache/ai-review-pack.json
```

Good review prompt:

> Review this FNLLA upgrade pack for release blockers, migration risks,
> backward-compatibility issues and missing tests.

## Release Owner Checklist

- Run `php fnlla release:prepare --major --target=2.0.0`.
- Confirm generated SBOM and checksums are attached to the public release.
- Confirm `CHANGELOG.md` has a dated entry for the tag.
- Confirm this migration guide matches the final public behaviour.
- Confirm runtime cache, sessions, logs and generated local review artefacts are not
  committed.
