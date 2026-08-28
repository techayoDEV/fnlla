# Upgrade Notes: 2.1.0 to 2.1.1

FNLLA 2.1.1 is a patch release focused on starting commercial downstream
projects from a verifiable framework base. It does not turn FNLLA into a
finished product or SaaS starter; product-specific auth, roles, workflows and
E2E tests still belong in the project exported by `make:project`.

## New Checks

- `php fnlla project:acceptance --json` validates the project base before
  product work begins. It checks version/runtime files, writable storage, the
  exported framework lock and in-process HTTP probes for `/`, `/api/health` and
  `/maintenance`.
- `php fnlla perf:profile` and `php fnlla perf:budget` now include HTTP probe
  timings for `/` and `/api/health` in addition to CLI timings and project
  export timing.
- `php fnlla ops:backup-plan --verify` adds local readiness checks to the
  redacted backup/restore plan.

## Recommended Downstream Sequence

After updating an existing 2.1.0 project to 2.1.1, run:

```bash
php fnlla version:status
php fnlla project:acceptance --json
php fnlla ops:backup-plan --verify
php fnlla perf:profile --iterations=5 --write-baseline
php fnlla perf:budget --iterations=5 --max-regression=20 --max-regression-ms=1000
php scripts/test.php
php scripts/lint.php
```

If `project:acceptance` fails, fix the framework base before adding new
business code. Application-specific E2E tests should still be added in the
downstream repository once real login, role, CRUD, upload and form workflows are
defined.
