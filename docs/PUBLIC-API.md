# FNLLA Public API Contract

This document names the framework surfaces downstream projects may rely on
between minor releases. FNLLA keeps these surfaces compatible throughout the
same major line unless a documented security issue requires a tighter behavior.
Anything outside this list should be treated as internal implementation detail
unless a release note promotes it.

## Stable Runtime Surface

- Public entrypoints: `public/index.php`, `public/router.php`.
- Project routes: `routes/web.php`, plus route names generated through `route()`.
- Consent telemetry endpoint: `POST /fnlla/consent`, used by the built-in cookie
  consent banner to record aggregate consent decisions without raw visitor
  identifiers.
- Plain PHP views under `views/` rendered through controllers.
- Config files under `config/`, with environment overrides through `.env`.
- Console launcher: `php fnlla`.
- Core CLI contracts: `doctor`, `config:doctor`, `security:audit`, `app:map`,
  `ops:backup-plan`, `project:acceptance`, `developer:install-storage`,
  `upgrade:check`, `perf:budget`, `release:prepare`, `release:manifest`, `ai:ask`, `ai:triage`,
  `ai:explain-log`, `ai:brief` and `ai:providers`.
- Developer operations routes: `/developer`, `/developer/panel`,
  `/developer/panel/project-identity`, `/developer/panel/project-settings`,
  `/developer/panel/access`, `/developer/panel/profile`,
  `/developer/panel/settings`, `/developer/panel/workspace`, `/developer/panel/operations`,
  `/developer/panel/analytics`, `/developer/panel/notifications`,
  `/developer/panel/release-readiness`, `/developer/panel/integrations`,
  `/developer/panel/policy`,
  `/developer/panel/integrations/settings`,
  `/developer/panel/settings/project-leadership`,
  `/developer/panel/settings/project-leadership/confirmation` and
  `/developer/panel/framework-updates`.
  `/developer/panel/security` and `/developer/panel/health` remain compatibility
  routes that lead to the integrated Access & Security and Readiness & Health
  surfaces.

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
- `project_leadership()` for the optional neutral responsibility record used by
  public About pages, documentation and Developer Panel system information.
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
- `fnlla.developer_activity.v1`
- `fnlla.developer_activity_export.v1`
- `fnlla.developer_operations.v1`
- `fnlla.developer_analytics.v1`
- `fnlla.developer_analytics_settings.v1`
- `fnlla.cookie_consent_event.v1`
- `fnlla.form_inbox_summary.v1`
- `fnlla.developer_integrations_settings.v1`
- `fnlla.developer_notifications.v1`
- `fnlla.developer_policy_boundary.v1`
- `fnlla.project_leadership.v1`
- `fnlla.developer_security.v1`
- `fnlla.developer_storage_install.v1`
- `fnlla.developer_workspace.v1`
- `fnlla.remote_control_plugin.v1`
- `fnlla.techayo_remote_control.v1`
- `fnlla.techayo_remote_control_state.v1`
- `fnlla.public_api_lock.v1`

Automation should tolerate additional keys and should key decisions off
documented status fields such as `ok`, `status`, `failures` and `warnings`.

## Developer Panel Boundary

The Developer Panel is a stable technical control surface, not a product admin
panel. It may manage framework-owned operations such as developer access,
client preview, service control, framework updates, privacy-light operations
summaries, audit export and the technical Kanban workspace.

The optional project leadership block names a real person responsible for
product direction, roadmap, delivery or technical leadership. It is neutral
system information, not a branding device. Client projects can keep it private
with `PROJECT_LEADERSHIP_VISIBILITY=admin` or disable it entirely.

Optional integrations are public configuration contracts only when explicitly
enabled by project developers. FNLLA ships disabled adapters for privacy-light
analytics, error reporting, consent-aware heatmaps, API hooks, Fionn and the
TechAyo remote-control bridge; private provider logic and secrets stay outside
the framework repository.

It must not contain customer data, product CRM/CMS logic, billing, bookings,
private client workflows or the private Fionn brain. Those belong to the
downstream application repository or to an external service accessed through an
explicit adapter contract.

The detailed boundary is documented in `docs/DEVELOPER-PANEL.md`.

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
