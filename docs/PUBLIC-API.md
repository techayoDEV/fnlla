# FNLLA Public API Contract

This document names the framework surfaces downstream projects may rely on
between minor releases. FNLLA keeps these surfaces compatible throughout the
same major line unless a documented security issue requires a tighter behavior.
Anything outside this list should be treated as internal implementation detail
unless a release note promotes it.

## Stable Runtime Surface

- Public entrypoints: `public/index.php`, `public/router.php`.
- Project routes: `routes/web.php`, plus route names generated through `route()`.
- Plain PHP views under `views/` rendered through controllers.
- Config files under `config/`, with environment overrides through `.env`.
- Console launcher: `php fnlla`.
- Core CLI contracts: `doctor`, `config:doctor`, `security:audit`, `app:map`,
  `ops:backup-plan`, `project:acceptance`, `upgrade:check`, `perf:budget`,
  `release:prepare`, `release:manifest`, `ai:ask`, `ai:triage`,
  `ai:explain-log`, `ai:brief` and `ai:providers`.

## Compatibility Policy

Within the same major version, FNLLA treats this document as the compatibility
contract for downstream projects. Patch and minor releases may add public
surface area, improve validation and tighten unsafe behaviour, but they should
not remove documented helpers, route entrypoints or command names without an
explicit security note.

Documented JSON schemas are intended for CI and release automation. A schema may
gain new fields, but existing stable keys should remain readable unless the
release notes say otherwise.

## Stable Helper Surface

- `config()`, `config_set()`, `env()`.
- `base_path()`, `public_path()`, `storage_path()`.
- `url()`, `asset()`, `route()`.
- `h()`, `csrf_token()`, `csrf_field()`, `verify_csrf_token()`.
- `csp_nonce()`.
- `auth()`, `db()`, `cache()`, `queue()`, `storage()`, `mailer()`, `runtime_ai()`.
- `stream_request_body_to_file()` for endpoints that intentionally stream a raw
  request body to storage with a hard byte limit.

`runtime_ai()` returns `RuntimeAiProviderInterface` for the configured runtime
AI driver. The default driver is `local`. The maintained external boundary is
`fionn`, which is available only through the audited opt-in bridge policy in
`config/ai.php`.

## Stable Data Surface

- `DatabaseManager::table(string $table)` for creating a query builder.
- `DatabaseManager::transaction(callable $callback)` for atomic application
  writes.
- `QueryBuilder::select()`, `where()`, `orderBy()`, `limit()`, `offset()`,
  `get()`, `first()`, `insert()`, `insertGetId()`, `update()`, `delete()`,
  `count()`, `exists()` and `paginate()`.
- `QueryBuilder::paginate()` returns an array with `data` and `meta`. The meta
  keys are `current_page`, `per_page`, `total`, `last_page`, `from` and `to`.

## Stable Operational Schemas

The following machine-readable schemas are considered project-facing:

- `fnlla.doctor.v1`
- `fnlla.security_audit.v1`
- `fnlla.project_acceptance.v1`
- `fnlla.backup_plan.v1`
- `fnlla.backup_plan_verification.v1`
- `fnlla.performance_profile.v1`
- `fnlla.performance_budget.v1`
- `fnlla.app_map.v1`
- `fnlla.upgrade_report.v1`
- `fnlla.runtime_ai.answer.v1`
- `fnlla.runtime_ai.providers.v1`
- `fnlla.runtime_ai.provider_status.v1`
- `fnlla.runtime_ai.provider.fionn.v1`
- `fnlla.public_api_lock.v1`

Automation should tolerate additional keys and should key decisions off
documented status fields such as `ok`, `status`, `failures` and `warnings`.

## Stable Extension Points

- Controller handlers registered in routes as `[ClassName::class, "method"]`.
- Middleware aliases registered on the router.
- Service providers listed in `config/app.php`.
- Cache stores implementing `CacheStoreInterface`.
- Queue stores implementing `QueueStoreInterface`.
- Migrations extending `Migration`; set `$withinTransaction = false` when a
  migration performs engine-specific DDL that should not be wrapped.

The machine-readable lock for this surface is `docs/PUBLIC-API.lock.json`.
Refresh it with `php fnlla api:lock` after intentional public API changes.

## Internal By Default

Classes under `src/Support/`, release scripts, generated docs, generated cache
files, tests, blueprint fixtures and the internal shape of runtime guard state
may change between releases.

The fact that a class exists in the repository does not make it public. Prefer
helpers, controllers, routes, middleware aliases and documented CLI commands
over depending directly on internal support classes.

Public API changes require `docs/PUBLIC-API.md`,
`docs/PUBLIC-API.lock.json`, `CHANGELOG.md` and relevant upgrade notes to be
updated together.
