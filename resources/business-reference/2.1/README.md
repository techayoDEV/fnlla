# FNLLA 2.1 Business Reference

This blueprint is the maintained reference shape for a professional FNLLA
business application.

Start from a clean export:

```bash
php fnlla make:project ../fnlla-business-reference "FNLLA Business Reference"
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
- Log mailer for development and an HTTPS-pinned HTTP mail transport for
  production.
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

A 2.1-ready business application should pass:

```bash
php scripts/test.php
php scripts/lint.php
php fnlla security:audit --strict
php fnlla ops:backup-plan --json
php fnlla optimize:warm
```
