# Upgrade Notes: 2.0.x to 2.1.0

FNLLA 2.1.0 is a minor release. It keeps the 2.0 public runtime contract and
adds business-application, operations and data-layer surfaces.

## Compatibility

Downstream 2.0.x applications should upgrade without required code rewrites when
they use the documented public API. Treat files outside `docs/PUBLIC-API.md` as
internal unless this changelog or a later public API note promotes them.

## Added Public Surface

- `db()` helper for resolving the database manager.
- `DatabaseManager::transaction(callable $callback)`.
- `QueryBuilder::offset(int $offset)`.
- `QueryBuilder::paginate(int $perPage = 15, int $page = 1)`.
- `php fnlla ops:backup-plan`.
- Business reference blueprint under `resources/business-reference/2.1/`.
- Production checklist under `docs/PRODUCTION-CHECKLIST.md`.

## Before Updating

From the downstream application:

```bash
php fnlla doctor
php fnlla security:audit
php fnlla upgrade:check --target=2.1.0
php fnlla ops:backup-plan --output=framework/backup-plan.json
```

Take a database backup, a storage backup and a copy of `.env` before applying a
framework update.

## Update Workflow

Use the standard update command from the exported project repository:

```bash
php fnlla framework:update --check
php fnlla framework:update --dry-run
php fnlla framework:update --apply
```

Review the dry-run report at
`storage/framework/updates/fnlla/dry-run-report.json` before applying changes.
Never run the update from the maintainer framework source repository.

## After Updating

Run:

```bash
php fnlla version:status
php fnlla project:acceptance --json
php scripts/test.php
php scripts/lint.php
php fnlla migrate
php fnlla optimize:warm
php fnlla security:audit --strict
php fnlla perf:budget --iterations=5 --max-regression=20 --max-regression-ms=1000
```

Then verify the application manually:

- login and logout;
- admin, operator and client route access;
- CRUD screens;
- business forms and file upload;
- queued work;
- mail/log output;
- `/api/health`;
- client preview and maintenance screens.

## Project Boundary Check

After the framework update, confirm that product-owned files still represent the
product and not the upstream framework skeleton:

- root `README.md` describes the downstream project, owner and maintainer
- `.fnlla/framework-lock.json` exists and records the previous framework base
- `routes/`, `views/`, `database/` and application controllers contain project
  code rather than only placeholder pages
- secrets remain in `.env` or the hosting secret store, never in Git
- generated files under `storage/` and `dist/` were not committed

If a project still looks generic after updating to 2.1.0, run
`project:claim`, rebuild the first application-specific screen and add product
E2E tests before treating the update as release-ready.

## Documentation To Read Next

- `docs/README.md` for the full documentation map.
- `docs/BUILDING-WITH-FNLLA.md` for the application build path.
- `docs/BUSINESS-APP-REFERENCE.md` for reference app acceptance criteria.
- `docs/PRODUCTION-CHECKLIST.md` for the deployment gate.
- `docs/PUBLIC-API.md` for surfaces covered by the minor-release contract.

## Notes For Maintainers

The release workflow tests project export behavior and keeps the update path
visible in CI. For 2.1.0, release evidence should include a project generated
from 2.0.3, an update check toward 2.1.0 and the normal release gate output.
