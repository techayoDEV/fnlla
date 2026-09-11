# FNLLA Public API Contract

This document names the framework surfaces downstream projects may rely on
between minor releases. FNLLA keeps these surfaces compatible throughout the
same major line unless a documented security issue requires a tighter behavior.
Anything outside this list should be treated as internal implementation detail
unless a release note promotes it.

## Stable Runtime Surface

Edition 2.2.0 adds `openai` (OpenAI API) and `anthropic` (Anthropic API)
to the existing `local` and `fionn` (FIONN AI, created by TechAyo)
runtime AI drivers. `RuntimeAiProviderInterface` is unchanged. The answer schema
uses nullable token counts when a cloud provider omits usage and nullable
`estimated_cost_gbp` when cost is unknown, including FIONN AI. Consumers must not
cast unknown cost to a displayed zero. Cloud confidence is explicitly unmeasured;
see [AI request and response boundaries](AI-CONTEXT.md#request-and-response-boundaries).
Unknown driver names now fail closed. `ai:triage` remains local regardless of
the selected runtime provider; `ai:ask` uses the selected provider.

- Public entrypoints: `public/index.php`, `public/router.php`.
- Project routes: `routes/web.php`, plus route names generated through `route()`.
- Consent telemetry endpoint: `POST /fnlla/consent`, used by the built-in cookie
  consent banner to record aggregate consent decisions without raw visitor
  identifiers.
- Plain PHP views under `views/` rendered through controllers.
- Config files under `config/`, with environment overrides through `.env`.
- Framework identity config under `config/framework.php`, especially
  `config("framework.official_url")`, `config("framework.support_email")` and
  `config("framework.repository_web_url")`.
- Console launcher: `php fnlla`.
- Core CLI contracts: `doctor`, `config:doctor`, `security:audit`, `app:map`,
  `ops:backup-plan`, `project:acceptance`, `developer:install-storage`,
  `upgrade:check`, `perf:budget`, `release:prepare`, `release:manifest`,
  `tech-debt:update`, `ai:ask`, `ai:triage`, `ai:explain-log`, `ai:brief`
  and `ai:providers`.
- Developer operations routes: `/developer`, `/developer/panel`,
  `/developer/panel/project-identity`, `/developer/panel/setup-checklist`,
  `/developer/panel/project-settings`,
  `/developer/panel/access`, `/developer/panel/profile`,
  `/developer/panel/settings`, `/developer/panel/workspace`,
  `/developer/panel/my-todo`, `/developer/panel/operations`,
  `/developer/panel/analytics`, `/developer/panel/notifications`,
  `/developer/panel/release-readiness`, `/developer/panel/integrations`,
  `/developer/panel/policy`,
  `/developer/panel/debug/live`,
  `/developer/panel/debug/runtime-issues/promote`,
  `/developer/panel/integrations/settings`,
  `/developer/panel/settings/runtime-environment`,
  `/developer/panel/settings/project-leadership`,
  `/developer/panel/settings/project-leadership/confirmation` and
  `/developer/panel/framework-updates`.
  `/developer/panel/setup-checklist`, `/developer/panel/project-settings`,
  `/developer/panel/security` and `/developer/panel/health` remain compatibility
  routes that lead to the integrated Project Setup, Access & security and
  Readiness & Health surfaces.
- Customer portal routes: `/client`, `/client/invite`, `/client/panel`,
  `/client/panel/kanban`, `/client/panel/analytics` and
  `/client/panel/heatmap`, with the entry path configurable through
  `CUSTOMER_ACCESS_PATH`.

## Compatibility Policy

`framework:update --project=PATH` selects an explicit downstream project when
running a newer updater outside it. Check/dry-run/apply still use the official
release channel; this option does not permit local or fork release sources.
Follow [Migration](MIGRATION.md) for projects whose old updater predates the
2.2.0 ownership and metadata migration.

The shared generator, migration, application identity v1 and cache behavior is
specified in [CLI And Runtime Contracts](framework/RUNTIME-CONTRACTS.md). These
changes are prepared for 2.2.0, not a declaration of publication. Application
identity verification now rejects removed/mismatched accounts. Non-local migration
and `db:seed` writes require explicit `--force`; update deployment automation before upgrading.

Both presets expose `make:controller`, `make:middleware`, `make:command`,
`make:factory`, `make:seeder`, `make:migration`, `migrate`, `migrate:rollback`,
`migrate:status`, `config:cache`, `route:cache` and command help.

`release:prepare --skip-tests` prepares artifacts, not a validated release. Its
JSON reports `validation: skipped` and `risk: unknown`. `ok` describes command
success, not publication readiness. A validated local run reports `validation:
passed`; failed validation reports `failed`. Remote and publication acceptance
must still be checked separately.

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
- `customer_access()` for the private read-only customer portal account and
  session contract.
- `stream_request_body_to_file()` for endpoints that intentionally stream a raw
  request body to storage with a hard byte limit.

`runtime_ai()` returns `RuntimeAiProviderInterface` for the configured runtime
AI driver. The default driver is `local`. The maintained external boundary is
`fionn`, which is available only through the audited opt-in bridge policy in
`config/ai.php`.

## Stable Data Surface

- `DatabaseManager::table(string $table)` for creating a query builder.
- `DatabaseManager::forConnection(string $name): DatabaseManager` selects a
  cached named manager without changing the default connection.
- `DatabaseManager::connection(?string $name = null): PDO` resolves the selected
  connection lazily; an unknown name fails instead of falling back.
- `registerConnection(string $name, PDO $pdo)` installs an explicitly supplied
  connection; `purge(?string $name = null)` releases the manager's cached PDO
  reference and rejects active transactions. External PDO references are not closed.
- `DatabaseManager::transaction(callable $callback)` for atomic application
  writes.
- `QueryBuilder::select()`, `where()`, `orderBy()`, `limit()`, `offset()`,
  `get()`, `first()`, `insert()`, `insertGetId()`, `update()`, `delete()`,
  `count()`, `exists()` and `paginate()`.
- `QueryBuilder::paginate()` returns an array with `data` and `meta`. The meta
  keys are `current_page`, `per_page`, `total`, `last_page`, `from` and `to`.

## Named Database Connections

Add MySQL connection definitions under `database.connections` in
`config/database.php`; `DB_CONNECTION` selects the default key (default `mysql`).
Keep credentials in environment variables, not source. Configure before resolving
the manager; after changing configuration, explicitly purge an existing connection.
Names accept letters followed by letters, digits, hyphens or underscores (64
characters maximum). There is no automatic read replica routing, distributed
transaction or ORM identity map.

```php
$audit = db()->forConnection("audit");
$rows = $audit->table("events")->where("processed", 0)->get();
$migrator = new \Fnlla\Php\Database\Migrations\Migrator(
    $audit,
    base_path("database/audit-migrations")
);
$migrator->migrate();
$migrator->status();
$migrator->rollback();
```

The migrator binds both `up()` / `down()` and its ledger to its selected manager,
regardless of the manager supplied by a migration file's constructor. Put database
work in `up()` / `down()`, not migration constructors. Existing CLI migration
commands continue to target the configured default connection. MySQL DDL may
implicitly commit; a failed migration is not recorded as complete but may leave
schema changes that require an application-specific repair. Run migrations under
one deployment owner; concurrent migration execution is not serialized here.

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
- `fnlla.technical_debt_report.v1`
- `fnlla.technical_debt_update.v1`
- `fnlla.framework_identity.v1`
- `fnlla.developer_activity.v1`
- `fnlla.developer_activity_export.v1`
- `fnlla.developer_operations.v1`
- `fnlla.debug_report.v1`
- `fnlla.debug_live.v1`
- `fnlla.runtime_issue_tracker.v1`
- `fnlla.developer_analytics.v1`
- `fnlla.developer_analytics_settings.v1`
- `fnlla.cookie_consent_event.v1`
- `fnlla.form_inbox_summary.v1`
- `fnlla.developer_integrations_settings.v1`
- `fnlla.developer_notifications.v2`
- `fnlla.developer_review_queue.v2`
- `fnlla.developer_control_state.v2`
- `fnlla.developer_policy_boundary.v1`
- `fnlla.developer_telemetry_policy.v1`
- `fnlla.framework_apply_policy.v1`
- `fnlla.project_leadership.v1`
- `fnlla.developer_security.v1`
- `fnlla.developer_storage_install.v1`
- `fnlla.developer_workspace.v1`
- `fnlla.developer_private_todo.v1`
- `fnlla.customer_access.v1`
- `fnlla.customer_workspace.v1`
- `fnlla.remote_control_plugin.v1`
- `fnlla.techayo_remote_control.v1`
- `fnlla.techayo_remote_control_state.v2`
- `fnlla.public_api_lock.v1`

Automation should tolerate additional keys and should key decisions off
documented status fields such as `ok`, `status`, `failures` and `warnings`.

## Developer Panel Boundary

The Developer Panel is a stable technical control surface, not a product admin
panel. It may manage framework-owned operations such as developer access,
customer portal invitations, client preview, service control, framework
updates, privacy-light or regulated operations summaries, audit export and the
technical Kanban workspace.

The Customer Portal is a separate read-only review surface. It can show
customer-visible Kanban cards, aggregate analytics, aggregate heatmap summaries
and a preview link. It must not expose Developer Panel actions, framework
updates, audit export, service control, private developer notes or customer
business records.

The shared workspace Kanban exposes the same delivery state to developers and
customer-visible review cards. Its supported card columns are `backlog`,
`in_progress`, `review` and `done`; legacy `todo` card states are normalised into
`backlog` when existing project data is read.

The private developer to-do surface is separate from the shared Kanban. Its
stable data key is `developer.private_todo`, its schema is
`fnlla.developer_private_todo.v1`, and its contents are scoped to the signed-in
developer rather than the project team or customer.

The optional project leadership block names a real person responsible for
product direction, roadmap, delivery or technical leadership. It is neutral
system information, not a branding device. Client projects can keep it private
with `PROJECT_LEADERSHIP_VISIBILITY=admin` or disable it entirely.

Optional integrations are public configuration contracts only when explicitly
enabled by project developers. FNLLA Analytics, FNLLA Heatmap and FNLLA Error
Monitor remain the first-party observability source of truth; API hooks, AI
providers and remote control are neutral contracts with optional adapters.
Private provider logic, vendor SDKs and secrets stay outside the framework
repository.

It must not contain customer data, product CRM/CMS logic, billing, bookings,
private client workflows or the private FIONN AI brain. Those belong to the
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

## Framework Identity Contract

`config("framework.*")` describes FNLLA itself, not the downstream product.
The stable keys are:

- `framework.name` and `framework.slug`;
- `framework.official_domain` and `framework.official_url`;
- `framework.support_email`;
- `framework.maintainer_name`, `framework.maintainer_legal` and
  `framework.maintainer_url`;
- `framework.repository`, `framework.repository_url` and
  `framework.repository_web_url`.

The official website is `https://fnlla.com`. `APP_URL` stays project-owned and
must point at the local, staging or production URL for the current installation.

## Internal By Default

Classes under `src/Support/`, release scripts, documentation tooling, generated cache
files, tests, blueprint fixtures and the internal shape of runtime guard state
may change between releases.

The fact that a class exists in the repository does not make it public. Prefer
helpers, controllers, routes, middleware aliases and documented CLI commands
over depending directly on internal support classes.

Public API changes require `docs/PUBLIC-API.md`,
`docs/PUBLIC-API.lock.json`, `CHANGELOG.md` and relevant upgrade notes to be
updated together.
