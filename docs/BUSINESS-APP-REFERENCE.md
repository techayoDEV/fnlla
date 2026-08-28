# Business App Reference

FNLLA 2.1 ships a reference blueprint for a small professional web application.
It is intentionally plain PHP and MySQL: the goal is to prove the framework
surface needed for business delivery without turning FNLLA into a heavy product
starter kit.

## Create the Reference Project

Start from a normal project export:

```bash
php fnlla make:project ../fnlla-business-reference "FNLLA Business Reference"
cd ../fnlla-business-reference
php fnlla project:claim --product "FNLLA Business Reference" --owner "Example LTD" --developer "TechAyo LTD"
```

Use `resources/business-reference/2.1/blueprint.json` as the implementation
map. The blueprint names the routes, tables, roles, gates, tests and production
checks that a complete business application should contain.

## Required Business Surface

A release-quality reference application should demonstrate:

- login, logout and protected routes;
- roles for `admin`, `operator` and `client`;
- gates for admin, operations and client-owned records;
- dashboard metrics backed by the database;
- CRUD for clients or accounts;
- a contact or service-request form with validation, old input, flash feedback,
  redirect and CSRF;
- file upload with type, extension and byte-size limits;
- log or HTTP mail delivery through `mailer()`;
- migrations, seeders and a repository class;
- queue dispatch and `queue:work`;
- `/api/health` and the browser maintenance screen;
- password-protected client preview before public launch;
- `ops:backup-plan`, `security:audit --strict` and `optimize:warm` before
  deployment.

## Suggested Schema

Use four tables for the first version:

- `users`: identity, password hash, role and enabled status.
- `clients`: company or account records owned by the business.
- `service_requests`: submitted requests, status, assigned user and optional
  uploaded attachment path.
- `activity_log`: append-only operational events for audit and support.

Keep ownership fields explicit. For example, store `client_id` on records that
clients can view, and gate access through a single helper or policy method.

## Repository Pattern

Use repositories to keep controllers small and to document the data contract:

```php
final class ClientRepository
{
    public function index(int $page = 1): array
    {
        return db()
            ->table("clients")
            ->orderBy("name")
            ->paginate(25, $page);
    }

    public function create(array $values): int
    {
        return db()->transaction(
            static fn () => db()->table("clients")->insertGetId($values)
        );
    }
}
```

The framework query builder remains intentionally small. Public 2.1 data
contracts are `db()`, `DatabaseManager::transaction()`,
`QueryBuilder::offset()` and `QueryBuilder::paginate()`.

## Auth And Permissions

Use three business roles:

- `admin`: can manage users, clients, settings, maintenance and deployment
  checks.
- `operator`: can use the dashboard, handle requests and update operational
  records.
- `client`: can view only their own records and submit business forms.

Recommended gate names:

- `admin.access`
- `operations.access`
- `client.records.view`
- `client.records.update`

Every protected route should have one positive test and one unauthorized-flow
test. Unauthorized users should receive a redirect to login or a 403 response;
the behavior must be deliberate and covered.

## Form Workflow

Use the same workflow for every business form:

1. Render form with a CSRF field.
2. Validate request values and uploaded files.
3. Store validation errors and old input in session flash.
4. Redirect back on failure.
5. Wrap database writes and side effects in a transaction where consistency
   matters.
6. Queue follow-up work or write log-mailer output.
7. Redirect to a named route with a success flash message.

For uploads, store only server-generated filenames, reject executable
extensions and keep public download routes behind auth/gates.

## Test Matrix

Minimum tests for the reference application:

- guest cannot access dashboard or CRUD screens;
- each role reaches only its allowed routes;
- client users cannot read another client's records;
- CRUD create/update/delete persists the expected database state;
- invalid form input flashes errors and old input;
- valid form input queues or logs a mail event;
- `/api/health` returns 200 in normal mode;
- client preview is password-protected when enabled;
- strict security audit passes for production configuration.

## Release Proof

Before using the reference app as release evidence, run:

```bash
php scripts/test.php
php scripts/lint.php
php scripts/validate-version-manifest.php
php scripts/validate-release-metadata.php
php scripts/build-docs.php --check
php fnlla ops:backup-plan --output=framework/backup-plan.json
php fnlla security:audit --strict
php fnlla optimize:warm
```

The generated backup plan is operational evidence only. Do not commit
environment-specific secrets, dumps, uploads, logs, queues, sessions or cache
files.
