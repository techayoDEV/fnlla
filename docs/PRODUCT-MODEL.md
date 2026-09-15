# FNLLA Product Model

FNLLA is organised as the full commercial project product plus an explicit Core
edition. The split keeps the public runtime useful on its own while allowing
TechAyo to develop paid project-control features without turning Core into a
private fork.

## Product Names

| Product | Repository | Visibility | Role |
| --- | --- | --- | --- |
| FNLLA | TechAyo private maintainer source | Commercial product | Full product: licensed Starter package, Admin Panel, Developer Panel, Client Portal, setup, preview, diagnostics, analytics, updates and FIONN AI gateway UI. |
| FNLLA Core | `techayoDEV/fnlla-core` | Public | MIT-licensed PHP framework, CLI, contracts and core package without private operations surfaces. |
| fnlla.com | TechAyo website source | Product-site policy | Product website, public positioning, documentation surface and commercial access entrypoint. |
| FIONN AI | `techayoDEV/fionn-ai` | Private | Separate TechAyo AI service. FNLLA may connect to it through explicit adapters. |

`FNLLA` is the preferred name for the integrated commercial product.
`FNLLA Core` is the explicit core-only edition. `FNLLA OS` is not used as a
product name because FNLLA does not manage hardware or replace an operating
system.

## Starter Profiles

Use the product names for new commands and documentation:

```powershell
php fnlla make:project ../my-product "My Product" --profile=fnlla
php fnlla make:project ../my-api "My API" --profile=core
```

The maintained profile names are `fnlla` and `core`. Existing `platform`
automation remains accepted as a compatibility alias, but new automation should
use the explicit `--profile=fnlla` and `--profile=core` names.

## Core To FNLLA Upgrade

A Core project can be upgraded later to full FNLLA without rebuilding the
product surface:

```powershell
php fnlla fnlla:upgrade --source PATH_TO_FNLLA_SOURCE
php fnlla fnlla:upgrade --source PATH_TO_FNLLA_SOURCE --apply
```

The dry run reports additions, framework wiring updates and conflicts. The apply
run adds FNLLA-owned files, switches `.fnlla/project-profile` to `fnlla`,
writes `.fnlla/core-to-fnlla-upgrade.json`, refreshes the framework lock and
leaves application-owned files such as `app/`, `routes/web.php`, `views/pages/`,
`public/assets/app.css`, `.env`, `.env.example` and `README.md` untouched. If a
Core bootstrap file was changed locally, the command stops with a conflict so the
developer can merge that wiring deliberately.

## Repository Boundaries

FNLLA Core owns the stable framework surface:

- routing, middleware, controllers and PHP views;
- configuration, container, validation and HTTP primitives;
- database, migrations, sessions, cache, mail and queues;
- core CLI commands, health probes and extension contracts;
- public documentation, release metadata and compatibility gates.

The standalone FNLLA Core repository is generated from the maintained Core
manifest with:

```powershell
php scripts/export-fnlla-core-repo.php <target-fnlla-core-repo>
```

The export rewrites Core package metadata to `techayodev/fnlla-core` and
`techayoDEV/fnlla-core`; it must validate independently before publication.
The `.github/workflows/fnlla-core-sync.yml` workflow runs this export for FNLLA
pull requests and main pushes so Core-owned changes are checked before they can
drift away from the standalone repository.

FNLLA owns commercial project operations:

- licensed Starter distribution and commercial access flows;
- Admin Panel for business users and product operations;
- Project Setup, Developer Panel and Client Portal;
- maintenance, preview and service-control workflows;
- notification, readiness, update and audit surfaces;
- analytics, heatmap and error-monitor workbenches;
- FIONN AI gateway UI and commercial integration adapters.

The Admin Panel and Developer Panel are intentionally separate products inside
the full FNLLA experience. The Admin Panel belongs to the end customer's
business operation: customers, orders, licences, content, reporting and support
workflows. The Developer Panel belongs to the technical delivery team:
configuration, diagnostics, release readiness, framework updates, preview
control and maintenance evidence. Technical tooling should not be hidden inside
the business admin area, and business operations should not be treated as
developer settings.

Private customer workflows, product data, FIONN AI memory, model training,
provider secrets and managed-service operations must not move into the public
Core repository.

## FNLLA To Core Flow

The full FNLLA product may need new Core extension points. That work must move through the
public Core release process instead of direct private mutation.

1. FNLLA development identifies a missing Core primitive or contract.
2. FNLLA CI exports the Core repository and validates the exported package.
3. On a main push, the sync workflow opens or updates a proposal pull request
   against `techayoDEV/fnlla-core` when the export differs from Core `main`.
4. The Core pull request contains only public framework changes, tests and docs.
5. Core Quality, Hardening and Release Gate workflows pass for the exact commit.
6. A maintainer approves and releases FNLLA Core.
7. Full FNLLA updates its dependency to the published Core version.
8. FNLLA release notes declare the supported Core version range.

FNLLA releases are blocked when they depend on unreleased Core changes. Core
releases must never be created automatically from a full-product push without
Core review, release evidence and explicit publication approval.

## Versioning Rule

Core versions remain the public compatibility line, for example `FNLLA Core
2.3.x`. Full FNLLA versions are separate and declare a supported Core range such
as `requires FNLLA Core ^2.3`.

Core can release less often and with a stricter compatibility posture. FNLLA
can release more frequently for UI, operations and commercial integrations as
long as it consumes a stable Core contract.
