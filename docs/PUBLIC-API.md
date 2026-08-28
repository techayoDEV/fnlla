# FNLLA Public API Contract

This document names the framework surfaces downstream projects may rely on
between minor releases. Anything outside this list should be treated as internal
implementation detail unless a release note promotes it.

## Stable Runtime Surface

- Public entrypoints: `public/index.php`, `public/router.php`.
- Project routes: `routes/web.php`, plus route names generated through `route()`.
- Plain PHP views under `views/` rendered through controllers.
- Config files under `config/`, with environment overrides through `.env`.
- Console launcher: `php fnlla`.
- Core CLI contracts: `doctor`, `config:doctor`, `security:audit`, `app:map`, `upgrade:check`, `perf:budget`, `release:prepare`, `release:manifest`, `ai:ask`, `ai:triage`, `ai:explain-log`, `ai:brief` and `ai:providers`.

## Stable Helper Surface

- `config()`, `config_set()`, `env()`.
- `base_path()`, `public_path()`, `storage_path()`.
- `url()`, `asset()`, `route()`.
- `h()`, `csrf_token()`, `csrf_field()`, `verify_csrf_token()`.
- `csp_nonce()`.
- `auth()`, `cache()`, `queue()`, `storage()`, `mailer()`, `runtime_ai()`.
- `stream_request_body_to_file()` for endpoints that intentionally stream a raw
  request body to storage with a hard byte limit.

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
files and the internal shape of runtime guard state may change between releases.
