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

## Stable Helper Surface

- `config()`, `config_set()`, `env()`.
- `base_path()`, `public_path()`, `storage_path()`.
- `url()`, `asset()`, `route()`.
- `h()`, `csrf_token()`, `csrf_field()`, `verify_csrf_token()`.
- `csp_nonce()`.
- `auth()`, `db()`, `cache()`, `queue()`, `storage()`, `mailer()`, `runtime_ai()`.
- `stream_request_body_to_file()` for endpoints that intentionally stream a raw
  request body to storage with a hard byte limit.

## Stable Data Surface

- `DatabaseManager::table(string $table)` for creating a query builder.
- `DatabaseManager::transaction(callable $callback)` for atomic application
  writes.
- `QueryBuilder::select()`, `where()`, `orderBy()`, `limit()`, `offset()`,
  `get()`, `first()`, `insert()`, `insertGetId()`, `update()`, `delete()`,
  `count()`, `exists()` and `paginate()`.
- `QueryBuilder::paginate()` returns an array with `data` and `meta`. The meta
  keys are `current_page`, `per_page`, `total`, `last_page`, `from` and `to`.

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

Public API changes require `docs/PUBLIC-API.md`,
`docs/PUBLIC-API.lock.json`, `CHANGELOG.md` and relevant upgrade notes to be
updated together.
