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
- `.env.example` is now a short starter template and `.env.full.example`
  carries the complete environment reference.
- Runtime AI documents the opt-in Fionn bridge as a strict external API
  boundary, not an embedded private AI brain.

## Compatibility

2.1.1 is intended to be a low-risk patch upgrade for applications already on
2.1.0. It adds release-readiness evidence and does not intentionally change the
public application programming model.

Expected downstream impact:

- no route rewrite is required
- no database schema change is required by FNLLA itself
- exported projects can keep their application controllers, routes and views
- production release pipelines should add `project:acceptance` and verified
  backup-plan generation as new gates
- teams enabling Fionn should keep Fionn memory, models, evals and learning
  queues outside FNLLA and expose only the approved chat API endpoint
- projects using custom CI should refresh their command list from
  `docs/PROJECT-SCRIPTS-REFERENCE.md`

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

## Before Building A Commercial Product

For a new paid product based on FNLLA, use 2.1.1 as the starting framework line:

```bash
php fnlla make:project ../my-product "My Product"
cd ../my-product
php fnlla project:claim --product "My Product" --owner "Owner LTD" --developer "Developer LTD"
php fnlla project:acceptance --json
```

Then make the first product commit in the exported project, not in the framework
repository. That commit should normally include project identity, environment
example values, first migrations, first route/controller/view flow and the first
application tests.

## Release Evidence

For a downstream 2.1.1 release candidate, collect:

- `project:acceptance --json` output
- strict security audit output
- backup plan with `--verify`
- performance budget output
- product E2E results for login, roles, CRUD, forms and uploads
- staging restore drill note with exact backup timestamp and restore target

The framework can prove the base is healthy. The product repository must still
prove that the business workflows are correct.
