# FNLLA Documentation Map

This directory is the long-form documentation set for FNLLA. The repository
root `README.md` is intentionally short; this map points to the documents that
carry the full technical, operational and release detail.

## Recommended Reading Order

1. [STARTING-A-NEW-PROJECT.md](./STARTING-A-NEW-PROJECT.md) - how to create a real downstream product with `make:project`.
2. [BUILDING-WITH-FNLLA.md](./BUILDING-WITH-FNLLA.md) - how to build routes, controllers, views, forms, persistence and protected areas.
3. [PUBLIC-API.md](./PUBLIC-API.md) - which framework surfaces are stable across minor releases.
4. [PRODUCTION-CHECKLIST.md](./PRODUCTION-CHECKLIST.md) - the production security, backup, restore and deployment gate.
5. [ENVIRONMENT.md](./ENVIRONMENT.md) - the short `.env.example`, full `.env.full.example`, client preview and Fionn bridge contract.
6. [RELEASE-AND-OPERATIONS.md](./RELEASE-AND-OPERATIONS.md) - release workflow, GitHub Actions, SBOM/checksum assets, restore evidence and operational commands.
7. [PERFORMANCE.md](./PERFORMANCE.md) - CLI and HTTP performance profiling, baseline management and regression budgets.

## Build A Commercial Application

FNLLA is a framework, not a finished SaaS starter. For a real product:

```bash
php fnlla make:project ../my-product "My Product"
cd ../my-product
php fnlla project:claim --product "My Product" --owner "Owner LTD" --developer "Developer LTD"
php fnlla project:acceptance --json
```

After that point, product-specific code belongs in the exported project:

- identity model and login UX;
- business roles and permissions;
- domain tables and migrations;
- CRUD workflows;
- dashboards and reports;
- forms, uploads, mail and queue jobs;
- application E2E tests;
- deployment and monitoring configuration.

Use [BUSINESS-APP-REFERENCE.md](./BUSINESS-APP-REFERENCE.md) as the checklist
for the first serious business application built on FNLLA.

## Documentation Groups

### Project Delivery

- [STARTING-A-NEW-PROJECT.md](./STARTING-A-NEW-PROJECT.md) defines the framework-vs-product boundary and the first commands after export.
- [BUILDING-WITH-FNLLA.md](./BUILDING-WITH-FNLLA.md) is the main engineering guide for application code.
- [BUSINESS-APP-REFERENCE.md](./BUSINESS-APP-REFERENCE.md) describes the minimum business surface expected from a professional reference application.
- [PROJECT-SCRIPTS-REFERENCE.md](./PROJECT-SCRIPTS-REFERENCE.md) documents the scripts and CLI commands kept in exported projects.

### Operations And Security

- [PRODUCTION-CHECKLIST.md](./PRODUCTION-CHECKLIST.md) is the deploy gate.
- [ENVIRONMENT.md](./ENVIRONMENT.md) explains environment layers, client preview and the Fionn bridge boundary.
- [RELEASE-AND-OPERATIONS.md](./RELEASE-AND-OPERATIONS.md) documents release, restore, supply-chain and GitHub Actions workflows.
- [PERFORMANCE.md](./PERFORMANCE.md) explains baselines and p95 budgets.
- [AI-CONTEXT.md](./AI-CONTEXT.md) explains local redacted review artefacts.

### Versioning And Change Management

- [PUBLIC-API.md](./PUBLIC-API.md) names the stable API surface.
- [MIGRATION.md](./MIGRATION.md) describes the major-upgrade workflow.
- [UPGRADE-2.1.md](./UPGRADE-2.1.md) covers `2.0.x -> 2.1.0`.
- [UPGRADE-2.1.1.md](./UPGRADE-2.1.1.md) covers `2.1.0 -> 2.1.1`.
- [MAJOR-RELEASE-CHECKLIST.md](./MAJOR-RELEASE-CHECKLIST.md) is the maintainer release-owner checklist.
- [TECH-DEBT-AND-FUTURE-PROOFING.md](./TECH-DEBT-AND-FUTURE-PROOFING.md) records implemented hardening and non-blocking backlog posture.

### Ecosystem

- [TECHAYO-ECOSYSTEM.md](./TECHAYO-ECOSYSTEM.md) explains where FNLLA sits in the TechAyo-managed ecosystem.

## Source And Generated Docs

Markdown files are the source of truth. HTML files under `docs/*.html` are
generated from the Markdown sources and the shared docs shell:

```bash
php scripts/build-docs.php
php scripts/build-docs.php --check
```

When a Markdown file changes, rebuild the HTML docs and run the docs sync check
before release work.

## Quality Standard

Professional FNLLA documentation should be:

- concrete enough to run locally without guessing;
- explicit about the boundary between framework and downstream product;
- security-aware by default;
- careful with public API promises;
- honest about what FNLLA does not provide;
- linked rather than duplicated across documents;
- backed by commands that can be validated in CI or a project repository.
