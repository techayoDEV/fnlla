# Project Scripts Reference

## Why this guide exists

`techayoDEV/fnlla` now separates two concerns more deliberately:

1. the maintained framework repository
2. the downstream project exported by `php fnlla make:project`

That distinction matters for `scripts/`.

Not every script in the framework repository belongs in every exported project.

The exported project keeps only the scripts that are useful inside a real downstream application repository.

## Short answer

For a normal exported project, the important script set is:

- `scripts/test.php`
- `scripts/lint.php`
- `scripts/validate-fnlla-runtime.php`
- `scripts/validate-version-manifest.php`
- `scripts/sync-version-manifest.php`
- `scripts/sync-fnlla-runtime.ps1`

The exported application also keeps one framework-update command on purpose:

- `php fnlla framework:update --check --github`
- `php fnlla framework:update --check [--source <path-to-fnlla>]`

And the application now keeps one browser-facing maintenance page on purpose:

- `/maintenance/framework-update`

The exported project also keeps only a lean downstream smoke-test subset under `tests/`.
Framework-internal export coverage and the `make:*` scaffolding commands stay in the upstream `techayoDEV/fnlla` repository.

The maintainer-only docs builder stays in the framework repository:

- `scripts/build-docs.php`

The private or maintainer-specific helper also stays out of the export:

- `scripts/apply-techayo-metadata.ps1`

## What each exported script does

### `scripts/test.php`

Purpose:

- runs the repository-local FNLLA test harness
- discovers `*Test.php` files under `tests/`
- boots the local shim under `tests/PHPUnit/Framework/TestCase.php`
- reports pass/fail output without requiring a Composer-installed PHPUnit package

Use it when:

- you changed routes, controllers, helpers, middleware or validation behavior
- you updated project-export behavior
- you want a quick regression pass before a commit or deployment candidate

Important boundary:

- this is still a project-local test harness, not a framework-wide CI service
- it depends on the `tests/` directory being present in the exported project

### `scripts/lint.php`

Purpose:

- runs `php -l` against the maintained PHP source tree inside the current project
- checks directories such as `bootstrap/`, `config/`, `database/`, `public/`, `routes/`, `scripts/`, `src/`, `tests/` and `views/`
- ignores `vendor/` and runtime files under `storage/`

Use it when:

- you edited PHP files and want a fast syntax pass
- you changed generated project files and want a cheap pre-test check

Important boundary:

- it only checks syntax
- it does not prove behavior, data flow or runtime correctness by itself

### `scripts/validate-fnlla-runtime.php`

Purpose:

- validates that the current project still respects the built-in UI runtime contract
- confirms the vendored runtime exists where FNLLA expects it
- catches unsupported UI drift in the official stack

Use it when:

- the vendored `public/vendor/fnlla-runtime/` runtime was updated
- the shared layout or page structure changed
- you want to confirm the project still stays inside the supported UI runtime boundary

Important boundary:

- it validates the official UI contract
- it is not a substitute for application-level QA of your own project pages

### `scripts/validate-version-manifest.php`

Purpose:

- validates the machine-readable version contract for the current project
- checks that `VERSION` and `MANIFEST.json` agree with the vendored runtime version state

Use it when:

- the project version changed intentionally
- the vendored runtime was synced
- you want proof that release metadata is not drifting

Important boundary:

- it validates release metadata consistency
- it does not publish or bump versions by itself

### `scripts/sync-version-manifest.php`

Purpose:

- regenerates `MANIFEST.json` from the current `VERSION` file plus the vendored runtime state

Use it when:

- you intentionally changed the project version
- you synced the vendored runtime and want the machine-readable manifest refreshed

Typical outcome:

- `MANIFEST.json` is rewritten to match the current framework and vendored runtime versions

Important boundary:

- this is a metadata synchronization step
- it should follow a real version decision rather than replace one

### `scripts/sync-fnlla-runtime.ps1`

Purpose:

- refreshes the built-in runtime under `public/vendor/fnlla-runtime/`
- can work from a provided local source path or by cloning the GitHub source of truth
- detects whether the provided source is a published runtime export or a source checkout
- when the source is a maintained checkout, publishes `dist/fnlla-runtime/` first and then mirrors that export
- can also work from a dedicated runtime export rooted elsewhere when that is the maintained source you have locally
- finishes by running `scripts/sync-version-manifest.php`

Use it when:

- the project needs a newer published runtime snapshot
- the local vendored runtime is missing or damaged
- you want to re-sync from the authoritative TechAyo-maintained runtime source

Important boundary:

- this script is about the downstream vendored runtime
- it does not maintain the upstream runtime source itself

### `scripts/publish-fnlla-runtime.ps1`

Purpose:

- publishes the integrated runtime maintained under `public/vendor/fnlla-runtime/`
- writes a clean runtime export to `dist/fnlla-runtime/`
- gives maintainers one explicit source -> publish -> sync path for runtime distribution work

Use it when:

- you want a reproducible runtime export from the current `techayoDEV/fnlla` state
- you are preparing runtime sync or release work
- you want to verify the published export shape before downstream use

Important boundary:

- it publishes from the integrated maintainer repository
- it does not change starter pages, routes or application-owned project files

### `php fnlla framework:update`

Purpose:

- compares the current downstream project against a fresh application export generated from a maintained `techayoDEV/fnlla` repository
- checks only framework-managed files recorded in `.fnlla/framework-lock.json`
- protects application-owned files such as routes, views, the project README and project-specific migrations from blind overwrite

Use it when:

- the upstream FNLLA framework was improved and you want to see what can safely flow into an existing project
- you need a framework-update report before doing manual merge work
- you want to apply only the non-conflicting framework-managed changes

Typical examples:

```bash
php fnlla framework:update --check --github
php fnlla framework:update --apply --github
php fnlla framework:update --check --source ..\fnlla
php fnlla framework:update --apply --source ..\fnlla
```

Important boundary:

- by default, the GitHub-backed workflow checks the latest published FNLLA release and caches that release source locally under `storage/framework/updates/`
- the GitHub-backed workflow only prepares a diff or apply path when the published release is actually newer than the current locked framework base
- when `--source` is used, the command expects a maintained `techayoDEV/fnlla` source repository path
- the GitHub-backed workflow depends on network access plus a working local `git` binary so the published release can be cached locally
- it updates only files that the framework lock marks as framework-managed
- older compatibility paths remain intentionally hidden so the public downstream command surface stays centered on `php fnlla framework:update`

### `/maintenance/framework-update`

Purpose:

- gives the downstream project a small browser-facing maintenance surface for framework updates
- wraps the same check/apply workflow as `php fnlla framework:update`
- stays local-first and can keep safe apply disabled outside trusted development usage

Use it when:

- you want a visible maintenance surface inside the project itself
- the local team prefers reviewing framework drift from the browser before applying it
- you want a professional hand-off path for teams that are less CLI-heavy

Important boundary:

- it is meant for local or explicitly enabled maintenance usage, not for general public exposure
- it can fetch the latest published FNLLA release from GitHub or rely on a maintained local `techayoDEV/fnlla` source path when a maintainer checkout is preferred

Operational note:

- `FRAMEWORK_UPDATE_UI_ENABLED` turns the page on or off
- `FRAMEWORK_UPDATE_UI_LOCAL_ONLY` keeps it limited to localhost by default, and proxy-forwarded localhost headers are only trusted when `TRUSTED_PROXIES` explicitly names the proxy
- `FRAMEWORK_UPDATE_UI_APPLY_ENABLED` controls whether the browser UI may run safe apply or only drift checks
- `FRAMEWORK_UPDATE_GITHUB_ENABLED` controls whether the maintenance page may contact GitHub directly for published release checks
- `FRAMEWORK_UPDATE_SOURCE_PATH` lets the project prefill the maintained `techayoDEV/fnlla` source path

## What stays maintainer-only

### `scripts/build-docs.php`

Purpose:

- rebuilds the framework documentation HTML under `docs/`
- turns the maintained Markdown guide files into the published docs pages
- keeps the framework documentation shell in sync with the current repository state

Why it is not exported:

- downstream application projects do not need to ship the whole framework docs browser
- this script belongs to framework maintenance, not day-to-day application delivery

### `scripts/apply-techayo-metadata.ps1`

Purpose:

- maintainer or organization-specific helper

Why it is not exported:

- downstream FNLLA application repositories should not inherit TechAyo-specific maintainer automation unless a project explicitly wants that behavior

## What `make:project` now leaves behind on purpose

The exported project intentionally does not copy:

- `docs/`
- `scripts/build-docs.php`
- `.git/`
- `.github/`
- `CODE_OF_CONDUCT.md`
- `SECURITY.md`
- runtime residue from `storage/` such as logs, cache files, queue files, session files and UI runtime guard state
- the `make:*` scaffolding commands, the `make:project` export command and their maintainer-only regression coverage

That keeps the exported project closer to what a delivery repository should actually own.

## Recommended downstream command sequence

After export, a healthy first pass is:

```bash
php fnlla fnlla-runtime:validate
php fnlla framework:update --check --github
php scripts/test.php
php scripts/lint.php
php scripts/validate-version-manifest.php
php fnlla version:status
```

Use this order when:

- you want to prove the exported project is healthy before heavier project work
- the vendored runtime was just synced
- you want a compact pre-commit or pre-release project check

Use the local `--source` override only when you intentionally want to compare the project against an unpublished maintainer checkout instead of the latest GitHub release.

## Final rule

Treat `scripts/` in the framework repository as two overlapping groups:

- project-facing validation and sync tools that belong in exported projects
- maintainer-only documentation and organization helpers that should stay in `techayoDEV/fnlla`

That split keeps downstream projects leaner and makes the project export easier to understand.
