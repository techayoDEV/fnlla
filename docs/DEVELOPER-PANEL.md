# FNLLA Developer Panel

The Developer Panel is FNLLA's private technical workspace for projects created
with `make:project`. It is not the product admin panel, CMS, CRM or customer
back office. It exists so a developer can prepare, inspect, protect and update a
project without mixing those operations into the public application.

## Purpose

Use the Developer Panel for:

- first local project setup and named developer access;
- client preview and maintenance access;
- service disable/reopen controls for emergency project shutdown;
- framework update checks and audited update application;
- health, runtime, storage and release-readiness review;
- privacy-light operations summaries and consent-aware integration status;
- developer activity review and audit export;
- a lightweight technical Kanban workspace.
- TOTP two-factor protection for named developer accounts;
- passkey adapter-readiness metadata for project-owned WebAuthn providers;
- notification, analytics, release-readiness and integration review pages;
- optional project leadership and system-information records with explicit
  confirmation by the named person;
- clear framework attribution: FNLLA is produced by TechAyo Limited.

Do not use the Developer Panel for:

- customer records;
- product-specific CRM or CMS workflows;
- billing, bookings, orders, quotes, documents or contracts;
- business user management for the finished application;
- private client data;
- private Fionn knowledge, model data, learning queues or evals;
- tracking scripts enabled by default.

## The Boundary

FNLLA has three layers when a real product is built:

1. **Framework layer** - the maintained FNLLA base: routing, controllers,
   middleware, CLI, update logic, health, security audit, UI runtime and
   Developer Panel.
2. **Application layer** - the exported project repository created with
   `make:project`; this owns business code, database schema, product roles,
   customer data and public/private product workflows.
3. **External operations layer** - optional systems such as TechAyo central
   control, Fionn, Sentry, GA4, Clarity, mail providers and deployment
   platforms. FNLLA may expose a contract or adapter hook, but the private
   external system remains outside the public framework.

The hard rule is:

> FNLLA may ship the contract and safe defaults. The downstream project owns the
> business implementation and private data.

## Framework-Managed

The following belong in FNLLA core:

- `/developer` sign-in and `/developer/panel/*` technical routes;
- developer session TTL, absolute TTL, credential fingerprint and lock flow;
- developer role capability checks;
- project identity and preview settings stored through controlled environment
  writes;
- neutral `Product leadership`, `Project leadership`, `Led by` or `System
  information` blocks for named responsibility records;
- local and remote service-control contract;
- the optional TechAyo Remote Control plugin contract for projects that should
  be controllable from `https://techayo.co.uk/admin`;
- framework update routes and audit;
- health and release-readiness summaries;
- privacy-light analytics summaries without raw IP storage;
- consent events used by optional analytics or heatmap adapters;
- TOTP verification for developer accounts and an explicit passkey adapter
  contract;
- the reserved Fionn bridge policy, without the private Fionn brain.

## Project-Owned

The downstream product owns:

- business migrations, tables and repositories;
- application auth journeys and customer-facing account policies;
- business roles such as customer, staff, owner, supplier or tenant;
- product dashboards, reports, documents and data exports;
- forms, uploads and mail content specific to the business;
- real analytics destinations and heatmap vendor setup;
- production secrets, client data, backups and retention policies.

## Forbidden In Public FNLLA Core

Do not commit these to the public FNLLA repository:

- `.env` secrets, API tokens, DB dumps, logs, uploads or client backups;
- customer workflows or private operating procedures;
- private Fionn prompts, memory, learned data, model weights, queues or evals;
- TechAyo central admin business logic;
- industry-specific CRM/CMS/billing features;
- default-enabled analytics, heatmaps or marketing trackers.

## Developer Roles

Developer roles are technical roles. They are not business roles for the final
product.

- `owner_developer` and `admin` can perform all Developer Panel actions.
- `lead_developer` can manage identity, preview, service control, accounts,
  workspace, operations, audit export and framework updates.
- `operations_engineer` can manage preview, service control, operations, audit
  export, workspace and framework updates.
- `security_reviewer` can inspect operations, policy and export audit logs.
- `application_developer`, `developer` and `support_developer` can use the
  workspace and update their own profile.
- `operator` can manage preview/service-control operations without account
  ownership.
- `client` can only open the panel, view the boundary and maintain its own
  developer profile if granted a developer account.

Projects may replace these roles in their own application layer. FNLLA's roles
only protect the technical control surface.

## Storage Policy

The default Developer Panel workspace and activity log are file-backed JSON
under `storage/framework/developer`. That keeps `make:project` portable and
works before an application database exists.

For higher-change teams, move long-lived workspace and audit history to
project-owned database tables. The UI and route contract can stay the same, but
the persistence should become part of the downstream application's operational
schema.

FNLLA ships the optional database installer:

```bash
php fnlla developer:install-storage --sql
php fnlla developer:install-storage
```

The installer creates tables for developer activity, workspace state,
notifications and analytics events. The default remains file-backed storage so
fresh `make:project` exports work before a database exists.

## Project Leadership

`/developer/panel/project-identity` includes an optional responsibility record
for the real person leading product direction, delivery or technical leadership.
It is not a promotional author card. It stores the delivery organisation, person
name, confirmation email, role or position, responsibility scope, optional
profile URL, visibility and confirmation status.

Use `admin` visibility for client systems where TechAyo or named-lead details
should be visible only in private system information and documentation. Use
`public` only when the public About page should show a small confirmed
`Product leadership` block. Public display requires the named person to sign in
with the matching developer email and confirm the record. Rejection keeps the
record private until the details are corrected.

## Security Model

Named developer accounts can enable TOTP from `/developer/panel/security`.
After TOTP is active, password-only login is rejected and the `/developer`
unlock form requires a current six-digit authenticator code.

Passkeys are intentionally kept as an adapter contract in FNLLA core. Full
WebAuthn attestation, credential storage, device policy and recovery workflow
belong to the downstream project or to an external control plane.

## Analytics Workspace

`/developer/panel/analytics` is a self-contained, privacy-light analytics
cockpit. It reports page views, request counts, daily and hourly trends, traffic
source buckets, host-only referrers, device buckets, form submissions,
conversion goals, HTTP status counts, slow routes, response-time averages and
backend consent rate from local aggregate metrics. It does not store raw IP
addresses, raw user agents or browser fingerprints.

The public cookie banner posts aggregate consent decisions to
`POST /fnlla/consent`. The endpoint records only the consent state
(`accepted_all`, `analytics_only`, `marketing_only`, `rejected_optional`),
optional source label and day bucket. It intentionally does not store raw IP
addresses, user agents or visitor identifiers.

The panel can update the local analytics posture through explicit `.env` keys:

- `OBSERVABILITY_METRICS_ENABLED`;
- `OBSERVABILITY_ANALYTICS_ENABLED`;
- `OBSERVABILITY_ANALYTICS_RETENTION_DAYS`;
- `OBSERVABILITY_ANALYTICS_SAMPLE_RATE`;
- `OBSERVABILITY_ANALYTICS_BOT_FILTERING`;
- `OBSERVABILITY_ANALYTICS_DEVICE_DETECTION`;
- `OBSERVABILITY_ANALYTICS_TRACK_QUERY_STRINGS`;
- `OBSERVABILITY_SLOW_ROUTE_THRESHOLD_MS`.

The default mode is internal-only. FNLLA does not need GA4, Matomo, Clarity or a
third-party script to provide the Developer Panel traffic cockpit.

## Integrations

FNLLA may expose integration hooks for GA4, Microsoft Clarity, Sentry, Fionn and
generic API callbacks. They must stay disabled by default. When enabled, GA4,
Clarity, heatmap adapter events and generic browser API hooks run from the
public layout only after analytics consent. Production CSP must explicitly allow
the required script or endpoint hosts before those browser adapters can load.

Rules:

- analytics and heatmaps load only after explicit consent;
- Fionn is accessed only through the audited bridge policy;
- remote service control must use HTTPS and allowed hosts;
- external adapters belong to project configuration or a separate package, not
  hard-coded framework core.

### TechAyo Remote Control Plugin

The TechAyo Remote Control plugin is a public FNLLA-side contract, not a hidden
private back office inside FNLLA. A project can opt in by setting:

```env
DEVELOPER_CONTROL_REMOTE_ENABLED=true
DEVELOPER_CONTROL_REMOTE_ENDPOINT=https://techayo.co.uk/admin/fnlla/projects/<project>/control.json
DEVELOPER_CONTROL_REMOTE_PROJECT_ID=<project-id>
DEVELOPER_CONTROL_REMOTE_TENANT=techayo
DEVELOPER_CONTROL_REMOTE_TOKEN=<project-token>
DEVELOPER_CONTROL_REMOTE_SIGNATURE_SECRET=<optional-hmac-secret>
```

When enabled, FNLLA polls the configured HTTPS endpoint and sends only technical
control headers:

- `Authorization: Bearer <token>`;
- `X-FNLLA-Control-Schema`;
- `X-FNLLA-Control-Tenant`;
- `X-FNLLA-Control-Project`;
- `X-FNLLA-Control-Timestamp`;
- `X-FNLLA-Control-Signature` when a signature secret is configured.

The expected response schema is `fnlla.techayo_remote_control_state.v1`:

```json
{
  "schema": "fnlla.techayo_remote_control_state.v1",
  "disabled": false,
  "title": "Service disabled by TechAyo Limited",
  "message": "This service is temporarily disabled by the developer team.",
  "contact": "support@example.com",
  "updated_at": "2026-08-29T12:00:00+00:00",
  "updated_by": "techayo-admin"
}
```

The TechAyo admin system is responsible for operator login, project
authorization, central audit, billing/account policy and emergency decisions.
FNLLA only consumes the resulting technical state. Customer data, private
project workflows, Fionn memory and any proprietary TechAyo admin logic must
remain outside public FNLLA.

## Workspace Kanban

`/developer/panel/workspace` is a shared technical Kanban board. It is meant for
framework setup and project delivery tasks, not customer work management.

Each card can track:

- column: Backlog, To-do, In progress, Review or Done;
- type: Task, Bug, Security, Release, Content or Research;
- priority, assignee email, due date, estimate and blocked state;
- a short checklist using `[ ]` and `[x]` lines, plus per-subtask colour,
  toggle, edit and delete actions;
- comments and linked attachments;
- drag-and-drop column moves and explicit position values for ordering;
- creator/updater metadata for the shared developer workspace.

`/developer/panel/notifications` stores read, archive and restore state for
current operational alerts. The state is global for the project, so one
developer's acknowledgement is visible to other developer sessions. For
long-lived accountability, the matching action is also written to the Developer
Panel activity log.

Teams that need long-lived history should install the database storage contract
with `php fnlla developer:install-storage`. Teams that need full product
project management should build or connect that as application-layer software.

## Release Checklist

Before treating a Developer Panel change as release-ready:

```bash
php scripts/test.php --filter ApplicationSurfaceTest
php scripts/test.php --filter FnllaRuntimeGuardTest
php scripts/lint.php
php scripts/validate-version-manifest.php
git diff --check
```

For a production release, also run:

```bash
php fnlla security:audit --strict
php fnlla project:acceptance --json
php fnlla ops:backup-plan --verify
php fnlla perf:budget --iterations=5 --max-regression=20 --max-regression-ms=1000
```

## Functional Closure Criteria

Developer Panel should be considered functionally complete when it covers these
technical workspace needs without becoming a business application:

- named developer accounts with role capabilities;
- TOTP for developer sign-in and an explicit passkey adapter contract;
- global project identity, preview and service-control settings;
- privacy-light analytics and consent-aware integration posture;
- notification center for actionable operational warnings;
- release-readiness gate for security, backup, cache, framework drift and
  acceptance posture;
- immutable audit entries with JSON and CSV export;
- optional database-backed storage installed by `developer:install-storage`;
- lightweight Kanban workspace for technical project delivery;
- optional TechAyo Remote Control plugin contract with signed requests and a
  documented response schema.

Anything beyond that should be project-owned unless it is a generic framework
contract. CRM, CMS, billing, bookings, customer support, document processing,
industry workflow and product analytics destinations must be implemented in the
application layer or through an explicit adapter package.
