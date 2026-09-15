# FNLLA Architecture And Development

## Product Boundary

FNLLA is one PHP framework for server-rendered applications plus a commercial
operations layer. FNLLA is the integrated product experience: setup, Developer
Panel, Client Portal,
diagnostics, updates, analytics and runtime assets. Both share the same positive
Core inventory. FNLLA Core is the public core-only framework surface.

FNLLA retains the file-based distribution by default; `--packages`
enables the Core/full-FNLLA package preview. Independent modules and a versioned
resource publication/removal contract remain incomplete. Disabling a module is
not uninstalling it. Editing a profile marker is not a migration between presets.

Application business logic, data, credentials and proprietary service internals
belong outside the framework repository.

## Runtime Responsibilities

| Boundary | Responsibility and constraint |
| --- | --- |
| Container | Provider bindings, cycle detection, explicit defaults and singleton rebinding. |
| HTTP | Routing, middleware and optional lifecycle observers; collector errors cannot replace a valid response. |
| Identity | Separate application, developer and customer domains; navigation visibility is not authorization. |
| Database | MySQL-oriented query builder, named PDO connections and nested savepoints, not a general-purpose ORM. |
| Shared state | Locked read/modify/write, revisions and corrupt-state rejection; row locks for database panel mutations. |
| Queues | Expiring reservations, retries and stale-token rejection; applications need idempotent jobs. |
| Cache | Atomic single-file publication preserving a valid previous cache on failure. |
| UI | Private access/panel layouts and styles isolated from public application presentation. |
| Diagnostics | Default-off, authorized, bounded and redacted; no raw cookies, credentials, form payloads or SQL parameters. |
| Integrations | Explicit opt-in adapters; normal bootstrap must not depend on network or AI service availability. |
| Updates | Ownership-aware comparison, transaction journal, rollback of managed files and early HTTP coordination. |
| Deployment | Optional immutable code releases and pointer rollback; business recovery is separate. |

Normal isolated PHP requests are supported. PSR HTTP adapters do not establish
global/static/session isolation in long-lived or concurrent HTTP workers.

Do not manually commit inside transaction callbacks. MySQL implicit-commit DDL
and deadlocks can invalidate the wider transaction. Callbacks are not automatically
retried because they may have external effects. Migrate panel tables before
invoking panel writes inside application-owned transactions.

## Developer Workflow

```console
composer install
composer test:unit -- --testsuite framework
composer analyse
composer lint
php scripts/test.php --suite fast
php fnlla make:project ../example-app "Example Application" --profile=fnlla
```

PHPUnit and PHPStan are development dependencies. The offline runner is a smoke
testing fallback, not a full PHPUnit implementation; nested integration/adapter
suites require PHPUnit. Exports use the project suite and commit their own
Composer lock. Production uses composer install --no-dev --optimize-autoloader.

MySQL integration requires an isolated fnlla_test_* database. Supply
FNLLA_TEST_MYSQL_DSN, FNLLA_TEST_MYSQL_USER and FNLLA_TEST_MYSQL_PASSWORD through a
private environment. Redis tests require ext-redis and FNLLA_TEST_REDIS_HOST /
FNLLA_TEST_REDIS_PORT; use unique prefixes, never production data or FLUSHDB.
Integration acceptance uses --fail-on-skipped.
Released-upgrade integration also requires `FNLLA_TEST_PREVIOUS_SOURCE` pointing
to the official v2.1.3 checkout; the test verifies its immutable commit. CI obtains
that checkout explicitly. It tests the candidate engine, not a published 2.2.0
registry artifact. On Windows, `FNLLA_TEST_REDIS_EXTENSION` may identify a private
test-only phpredis DLL to load in child PHP processes without changing php.ini.

See [Runtime contracts](framework/RUNTIME-CONTRACTS.md) for generators, migrations,
identity, proxy configuration and cache permissions.

## Panel Direction

Keep configuration, access/security, diagnostics, updates and release readiness
central. Use one capability-aware navigation registry and retain compatibility
routes when consolidating duplicate screens. Notifications should link to causes.

Technical debt needs owner, priority, status, reason and bounded audit history;
scanning preserves human decisions. Workspace, analytics, heatmaps and customer
review are optional concerns. Prioritize keyboard access, focus, responsive
layout and asset/CSS isolation before adding screens.

## Extension And Distribution Direction

Optional PSR-3/11/7/15, SMTP and deployment adapters have package documentation and
contract tests. Use standard interfaces and proven transports. A local Composer
path package is not proof of public GitHub VCS consumer installation.

Core and full FNLLA need a common versioned ownership contract for assets, config,
routes and package removal. Test actual published upgrades while preserving
application files. File rollback cannot reverse emails, payments, uploads or
schema changes. See [release operations](RELEASE-AND-OPERATIONS.md#backup-and-recovery).

Measure checkout, source archive, clean export and browser transfer separately.
Clean-export limits are 450,000 bytes / 170 files for Core and 4,250,000 bytes /
440 files for FNLLA. Dependencies/data are excluded. Tests are the maintained
authority, not copied local size snapshots.

scripts/build-source-archive.ps1 packages eligible working-tree sources without
publishing them. Exclude dependencies, Git internals, branding masters, credentials,
backups and runtime artifacts; never delete real application data to clean a ZIP.
Comparable performance claims require pinned equivalent applications, cold/warm
runs, latency percentiles, memory and repeatable scripts.

## Acceptance Ownership

[Current status](MODERNIZATION-STATUS.md) summarizes the sole
[JSON ledger](../resources/modernization-tasks.json). Every unfinished criterion
from the consolidated reviews remains there. Documentation cleanup and new version
labels do not complete tasks. Prioritize integrity, identity/session contracts,
real service/HTTP integration, package ownership and reproducible releases.
Private deployment evidence belongs in restricted application records.
