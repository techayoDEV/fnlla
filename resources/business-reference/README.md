# FNLLA Business Reference

Edition **2.2.2**.

This blueprint is the maintained reference shape for a professional FNLLA
business application.

Start from a clean export:

```bash
php fnlla make:project ../example-app "FNLLA Business Reference" --profile=full
```

Then build the application in the exported project, not inside the maintainer
repository.

## Required Business Surface

- Login and session-backed authentication.
- Role gates for `admin`, `operator` and `client`.
- CRUD around one core business record, such as `clients`.
- Dashboard with owner/operator metrics.
- Business form with validation, old input, flash feedback, CSRF and optional
  upload validation.
- Log mailer for development; an explicitly configured HTTPS HTTP transport or
  the optional SMTP adapter for production, with delivery-failure tests.
- Migrations, seeders and a small repository class wrapping query-builder calls.
- Queue job for deferred notification or audit work.
- `/api/health`, `/maintenance`, client preview and `ops:backup-plan`.

## Data Pattern

Keep the data layer explicit:

```php
$page = db()->table("clients")
    ->where("status", "active")
    ->orderBy("name")
    ->paginate(25, (int) $request->query("page", 1));
```

Use `db()->transaction()` for multi-table writes and keep project repositories
small enough to read in one pass.

## Release Proof

A business application targeting FNLLA 2.2.2 should pass:

```bash
php fnlla project:acceptance --json
php scripts/test.php
php scripts/lint.php
php fnlla security:audit --strict
php fnlla ops:backup-plan --verify
php fnlla perf:budget --iterations=5 --max-regression=20 --max-regression-ms=1000
php fnlla optimize:warm
```

## How To Use This Blueprint

This directory is not a generated application. It is a framework-owned
reference contract that downstream teams can use as a checklist while building
a real project created by `make:project`.

Recommended use:

- generate and claim a clean project
- implement one complete business flow at a time
- keep repositories and service classes small and explicit
- write product E2E tests after real roles and screens exist
- compare the finished product against `blueprint.json`
- keep release evidence in the downstream project, not in this framework
  blueprint directory

Do not copy this README as product documentation. A downstream product README
should describe the client, owner, deployment, environment variables, support
route and operational runbook for that specific application.

## Identity And Data Boundaries

Project Setup provisions Developer Panel access, not application users. Define
business authentication and gates in the application. Every `authorize:` entry
in `blueprint.json` names a declared gate; implement the role and record-ownership
checks before exposing the corresponding route. Collection queries must filter
by the authenticated client's owner ID even after a route-level gate succeeds.

The default seeder creates no accounts. For authentication fixtures, pass a hash
of an explicit test-only credential to the factory. Never seed shared passwords
or demo administrators into production. Keep external-effect reconciliation and
restore-drill evidence in the application, outside this public reference.
