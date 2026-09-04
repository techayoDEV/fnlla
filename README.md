# FNLLA

![FNLLA framework cover](./docs/assets/brand/fnlla-cover.jpg)

[![Version](https://img.shields.io/badge/version-2.1.3-0f766e?style=flat-square)](./VERSION)
[![License](https://img.shields.io/badge/license-MIT-111827?style=flat-square)](./LICENSE.md)
[![Runtime](https://img.shields.io/badge/runtime-PHP%208.3%2B-2f65eb?style=flat-square)](./docs/PERFORMANCE.md)
[![Database](https://img.shields.io/badge/database-MySQL-2563eb?style=flat-square)](./docs/BUILDING-WITH-FNLLA.md)
[![Release Gate](https://img.shields.io/badge/release%20gate-Ubuntu%20%7C%20macOS%20%7C%20Windows-0f766e?style=flat-square)](./docs/RELEASE-AND-OPERATIONS.md)
[![UI Runtime](https://img.shields.io/badge/UI-integrated%20FNLLA%20runtime-18352f?style=flat-square)](./public/vendor/fnlla-runtime/README.md)

FNLLA is an AI-ready PHP framework for building operated web products: public
pages, private workflows, customer review surfaces and the developer operations
layer needed to keep a product understandable after launch. It is not just a
thin routing skeleton. FNLLA ships a local runtime AI surface, a private
Developer Operations Panel, maintenance and preview controls, release evidence,
first-party aggregate analytics, audit trails and update checks as one
maintained stack.

The honest positioning is simple: FNLLA helps a delivery team keep the client
and the live product at the centre. The product itself still belongs in the
exported project repository, but the framework keeps setup, preview, service
control, operations, handover evidence and AI-assisted review close enough that
they can be used from the first release.

FNLLA is produced, maintained and distributed by TechAyo LTD
([techayo.co.uk](https://techayo.co.uk)). It is released under the MIT License.

Official framework website: [fnlla.com](https://fnlla.com).
Official source repository: [techayoDEV/fnlla](https://github.com/techayoDEV/fnlla).

## What Matters

- **AI-ready framework, not finished product:** this repository is the
  framework source and project-export base. Product code belongs in the
  exported project.
- **Server-rendered PHP:** routes, controllers and plain PHP views are the
  normal development path.
- **Developer Operations Panel:** the private panel is the technical control
  centre for setup, preview, maintenance, customer review, operations,
  analytics, audit, release-readiness and framework updates.
- **Runtime AI:** the default AI driver is local and deterministic. The Fionn
  bridge is an opt-in provider boundary, not bundled private intelligence.
- **Integrated UI runtime:** `public/vendor/fnlla-runtime/` is part of the
  official stack and should stay the shared UI foundation.
- **Commercial readiness:** FNLLA includes routing, middleware, auth
  foundations, CSRF, sessions, MySQL access, migrations, queue primitives,
  health checks, maintenance preview, security audit, performance budget and
  release operations.
- **Verified exports:** from 2.1.1, `project:acceptance` checks a generated
  project base before business-specific code is added.

## Start A Real Project

```bash
php fnlla make:project ../my-product "My Product"
cd ../my-product
php fnlla project:claim --product "My Product" --owner "Owner LTD" --developer "Developer LTD"
php fnlla project:acceptance --json
php scripts/test.php
php scripts/lint.php
```

Then initialize a separate Git repository in the exported project and build the
real website or application there. Do not build commercial product code inside
`techayoDEV/fnlla`.

Read the full workflow in
[`docs/STARTING-A-NEW-PROJECT.md`](./docs/STARTING-A-NEW-PROJECT.md).

## Essential Commands

```bash
php fnlla list
php fnlla doctor
php fnlla project:acceptance --json
php fnlla security:audit --strict
php fnlla ops:backup-plan --verify
php fnlla app:map --json
php fnlla tech-debt:update --check
php fnlla perf:profile --iterations=5
php fnlla perf:budget --iterations=5 --max-regression=20 --max-regression-ms=1000
php fnlla release:prepare --major --target=2.1.3
```

Command responsibilities and downstream boundaries are documented in
[`docs/PROJECT-SCRIPTS-REFERENCE.md`](./docs/PROJECT-SCRIPTS-REFERENCE.md).

## Documentation

Start with [`docs/README.md`](./docs/README.md). It links the full
documentation set:

- [`docs/BUILDING-WITH-FNLLA.md`](./docs/BUILDING-WITH-FNLLA.md) for the
  practical build guide.
- [`docs/PUBLIC-API.md`](./docs/PUBLIC-API.md) for the stable public contract.
- [`docs/PRODUCTION-CHECKLIST.md`](./docs/PRODUCTION-CHECKLIST.md) for
  deployment and security gates.
- [`docs/DEVELOPER-PANEL.md`](./docs/DEVELOPER-PANEL.md) for the private
  technical workspace, security, analytics, TechAyo Remote Control adapter
  contract, storage and framework-vs-product boundary.
- [`docs/ENVIRONMENT.md`](./docs/ENVIRONMENT.md) for `.env.example`,
  `.env.full.example`, client preview and the Fionn bridge boundary.
- [`docs/RELEASE-AND-OPERATIONS.md`](./docs/RELEASE-AND-OPERATIONS.md) for
  releases, backups, restore flow, SBOM and GitHub Actions.
- [`docs/BUSINESS-APP-REFERENCE.md`](./docs/BUSINESS-APP-REFERENCE.md) for the
  reference business-application checklist.
- [`resources/business-reference/2.1/`](./resources/business-reference/2.1/) for
  the machine-readable blueprint behind the reference checklist.
- [`docs/PERFORMANCE.md`](./docs/PERFORMANCE.md) for baselines and HTTP probes.
- [`docs/AI-CONTEXT.md`](./docs/AI-CONTEXT.md) for local, redacted AI review
  packs and the opt-in Fionn bridge contract.
- [`docs/TECH-DEBT-AND-FUTURE-PROOFING.md`](./docs/TECH-DEBT-AND-FUTURE-PROOFING.md) for
  the self-checking technical-debt report and release cleanup policy.

The generated HTML documentation lives in `docs/*.html` and is rebuilt from the
Markdown sources with:

```bash
php scripts/build-docs.php
php scripts/build-docs.php --check
```

## Repository Shape

- `bootstrap/` - application bootstrap and runtime wiring.
- `config/` - environment-driven framework configuration.
- `database/` - migrations, seeders and factories.
- `docs/` - source and generated documentation.
- `public/` - HTTP entrypoints, project assets and integrated UI runtime.
- `resources/` - local runtime bundles, reference manifests and export
  templates.
- `routes/` - web, maintenance and console route definitions.
- `scripts/` - validation, release and maintainer scripts.
- `src/` - framework source code.
- `storage/` - runtime state; only placeholder `.gitignore` files belong in Git.
- `tests/` - framework and export regression tests.
- `views/` - server-rendered PHP templates.

## Release State

Current repository version: **2.1.3**.

The latest release-gate record described here is `v2.1.1`, which passed the
FNLLA release gate on Ubuntu, macOS and Windows, including strict security
audit, project acceptance, verified backup plan generation, performance budget
and export/update regression.

See [`CHANGELOG.md`](./CHANGELOG.md) and
[`docs/UPGRADE-2.1.1.md`](./docs/UPGRADE-2.1.1.md) for release notes.

## Governance

- License: [`LICENSE.md`](./LICENSE.md)
- Support boundary: [`SUPPORT.md`](./SUPPORT.md)
- Security policy: [`SECURITY.md`](./SECURITY.md)
- Code of conduct: [`CODE_OF_CONDUCT.md`](./CODE_OF_CONDUCT.md)
- Trademark notice: [`TRADEMARKS.md`](./TRADEMARKS.md)
