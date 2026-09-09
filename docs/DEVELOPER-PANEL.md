# FNLLA Developer Operations Panel

Edition 2.2.0 includes **Integrations > AI providers** for selecting local
knowledge, the built-in FIONN AI gateway by TechAyo, OpenAI API or Anthropic API. Configuration writes
require `panel.settings.write` and CSRF validation. Keys are never prefilled;
blank fields preserve them, and removal requires an explicit checkbox. Saving
does not contact a provider. Cloud access is off by default and requires PHP cURL,
an API key and a model. See [AI request boundaries](AI-CONTEXT.md#openai-api-and-anthropic-api)
before enabling external requests. The full UI self-hosts Space Grotesk for
interface text and JetBrains Mono for literal URLs, email, code, versions,
endpoints and technical metadata. Both families include 400/600 faces; navigation
labels remain Space Grotesk. FIONN AI uses Mono SemiBold as a standalone name.
Use `.fnlla-literal` for literal values and `.fnlla-fionn-name` for that name,
without applying monospace to ordinary paragraphs or every hyperlink.

Supporting text uses the shared 14 px minimum. Private preview and framework
update views load their styles through the framework-owned shell, not the
application's `app.css`. Cookie surfaces, forms, Kanban and diagnostic tools use
semantic light/dark colours. Tablet setup switches to a single-column form
instead of shrinking the wordmark or controls. These defaults do not add FNLLA
campaign artwork to the public application's identity.

About FNLLA identifies the framework's **Lead Developer / Product Manager -
Marcin Kordyaczny** independently of the application's own leadership record.

## Optional Modules

Panel Settings provides workspace, analytics, heatmap and customer portal
checkboxes. First-run Project Setup stores only identity and the first private
developer account. Only developers with `panel.settings.write` can save panel
configuration; POST and CSRF remain mandatory. All modules default to on in new
full exports. The heatmap choice also enables analytics. Older settings
submissions without module fields preserve their existing values. No switch
deletes module code or data.

### Error Monitor And Debug Tools

Operations / Error Monitor can independently enable the toolbar and request history.
Both require `APP_DEBUG=true`, a local/development/testing environment and an
unlocked developer with `operations.view`. Configuration and clearing also require
`panel.settings.write`, POST and CSRF. Guests and production requests do not get
the toolbar or request-history recording. Core/plain exports contain neither
feature.

The toolbar remains a per-request profiler, but it now polls
`/developer/panel/debug/live` for live aggregate counts while the signed-in
developer is browsing public HTML pages. The Error Monitor panel shows the same live
payload, bounded request history, runtime gate status, slow-route/status/method
aggregates, error fingerprint summaries and recent protected log errors. The live endpoint is excluded from
request-history and metrics recording so polling does not dominate the debug
data.

Unexpected 500-level exceptions are fingerprinted into
`storage/framework/developer/runtime-issues.json` when
`DEBUG_RUNTIME_ISSUES=true`. Runtime issue candidates contain exception class,
relative source location, route, method, status, occurrence counts, first/last
seen times, severity and the last request id. They do not store exception messages, traces,
headers, cookies, request bodies, response bodies, SQL text or bindings.
Developers with `workspace.write` can promote a candidate to the Technical debt
register and can explicitly create a linked Kanban card during that promotion.
FNLLA does not automatically turn every runtime error into accepted technical
debt or shared tasks. The intended workflow is detection, fingerprinting,
notification, runtime issue candidate, manual Technical debt promotion and
optional Kanban tracking.

History contains only UTC timestamp, normalized HTTP method, status, elapsed
milliseconds and PHP peak memory. No URLs, route parameters, IDs, headers, SQL,
credentials, cookies, payloads or exception text are retained. Default retention
is 200 entries / one hour; configuration is clamped to 1000 entries / 24 hours.
Expiration is lazy on reads/writes, not a background timer. Disabling history
clears stored entries. Use Clear history before switching the deployment to
production; an idle file is not removed automatically. Storage failure never
changes the observed application's response. Private history uses the existing
locked, atomic JSON store in `storage/framework/developer/request-history.json`.

`DEBUG_REQUEST_HISTORY=false` and `DEBUG_TOOLBAR=false` are the defaults.
`DEBUG_RUNTIME_ISSUES=true` records privacy-light issue candidates for developer
triage. The panel's saved switches override their environment defaults where a
switch exists.

### Diagnostic Storage And Limits

Set `APP_ENV=development` and `APP_DEBUG=true` locally. Sign in as a developer
with `operations.view`; `panel.settings.write` is additionally required to change
debug settings. Toolbar and request history are independent switches. Both
default to disabled (`DEBUG_TOOLBAR=false`, `DEBUG_REQUEST_HISTORY=false`). A
saved panel switch overrides its environment default. Refresh cached
configuration after editing environment variables.

History records only authorized developer requests, including JSON and failed
responses. It stores timestamp, normalized HTTP method, status, duration and peak
memory, never paths, IDs, query strings, SQL, bindings, headers, cookies,
request/response bodies or exception messages. Runtime issue tracking stores
deduplicated 500-level issue fingerprints separately and requires developer
promotion before it becomes Technical debt. The table shows newest first. PHP
peak memory is process-scoped; long-lived concurrent HTTP workers are not the
supported runtime model.

`config/debug.php` sets `history.max_entries` (default 200, maximum 1000) and
`history.retention_seconds` (default 3600, maximum 86400). Entries expire on the
next history read/write, not via a background timer. Disable history to erase
entries or select Clear history and Save. The save/redirect requests themselves
may appear when recording remains enabled. Clear before production deployment.

Private state is in `storage/framework/developer/request-history.json`, outside
the public web root and excluded from source exports. Corrupt or unwritable
optional telemetry must not interrupt application requests. Clear-history and
settings mutations are CSRF protected. Guests, staging and production are not
recorded. No shared-browser session, payload viewer, production APM or remote
telemetry service is included.

Full projects can independently switch modules off in `config/modules.php` or `.env`:

```dotenv
FNLLA_MODULE_WORKSPACE=false
FNLLA_MODULE_ANALYTICS=false
FNLLA_MODULE_HEATMAP=false
FNLLA_MODULE_CUSTOMER_PORTAL=false
```

Disabled endpoints return 404 before their controllers execute, including cached
routes; corresponding main navigation entries disappear. Analytics and heatmap
switches stop their collectors without disabling technical request metrics.
Customer subpages also require the corresponding workspace/analytics/heatmap module.
Existing full projects default to enabled for compatibility. Rebuild cached
configuration with `php fnlla config:cache` after editing `.env` if caching is used.
These switches do not remove code from disk. Use plain for physical exclusion.

## Update Recovery

Full updates snapshot all planned files and the framework lock, serialize installation
and roll back on installation or post-check failure. An interrupted update journal
blocks another installation. Stop application traffic and recover with:

```powershell
php scripts/rollback-framework-update.php
```

Recovery does not boot the application. Keep the journal/backups if recovery reports
a checksum or filesystem error. Validate the project before restoring traffic.
This restores framework files, not database migrations, `.env`, uploads, storage,
external effects of tests, or an entire zero-downtime deployment.

The current starter supports `--profile=plain` for a separate Composer core
without panel code, maintenance, AI, analytics or the UI distribution.
The full profile includes Technical debt and Debug sections, domain-specific
controllers, separate panel CSS and collapsible mobile navigation. See
[starter profiles and developer tools](./STARTING-A-NEW-PROJECT.md#full-or-plain-profile)
for the safety policy, private storage, optional migrations and update boundary.

The architecture review and remaining work are documented in
[ARCHITECTURE-ROADMAP.md](./ARCHITECTURE-ROADMAP.md).

The Developer Operations Panel is FNLLA's private technical workspace for
projects created with `make:project`. The route and compatibility name remain
Developer Panel, but the product role is broader: it is the operational control
centre for delivery teams building and maintaining a web product. It is not the
product admin panel, CMS, CRM or customer back office. It exists so developers
can prepare, inspect, protect, preview, hand over and update a project without
mixing those operations into the public application.

Official FNLLA framework identity is exposed through `config/framework.php`:
`https://fnlla.com` is the framework website, `support@fnlla.com` is the
framework support mailbox and `techayoDEV/fnlla` is the official release
repository. These values are metadata for the framework layer. They do not
replace a downstream project's `APP_NAME`, `APP_URL`, public logo or mail
sender.

## Purpose

Use the Developer Panel for:

- first local project setup and named developer access;
- client preview and maintenance access;
- service disable/reopen controls for emergency project shutdown;
- framework update checks and audited update application;
- health, runtime, storage and release-readiness review;
- privacy-light operations summaries and consent-aware integration status;
- developer activity review and audit export;
- customer portal invitations for read-only project review;
- dashboard overview for the current project state;
- a lightweight technical Kanban workspace;
- technical-debt snapshot checks before release work;
- TOTP two-factor protection for named developer accounts;
- passkey adapter-readiness metadata for project-owned WebAuthn providers;
- notification, analytics, release-readiness and integration review pages;
- optional project leadership and system-information records with explicit
  confirmation by the named person;
- clear framework attribution: FNLLA is produced by TechAyo Limited and
  published at `https://fnlla.com`.

Do not use the Developer Panel for:

- customer records;
- product-specific CRM or CMS workflows;
- billing, bookings, orders, quotes, documents or contracts;
- business user management for the finished application;
- private client data;
- private FIONN AI knowledge, model data, learning queues or evals;
- tracking scripts enabled by default.

## The Boundary

FNLLA has three layers when a real product is built:

1. **Framework layer** - the maintained FNLLA base: routing, controllers,
   middleware, CLI, update logic, health, security audit, UI runtime and
   Developer Panel.
2. **Application layer** - the exported project repository created with
   `make:project`; this owns business code, database schema, product roles,
   customer data and public/private product workflows.
3. **External operations layer** - optional systems such as central
   operations, AI providers, error reporting, analytics, mail and deployment
   platforms. FNLLA may expose a contract or adapter hook, but the private
   external system remains outside the public framework.

The hard rule is:

> FNLLA may ship the contract and safe defaults. The downstream project owns the
> business implementation and private data.

## Framework-Managed

The following belong in FNLLA core:

- `/developer` sign-in and `/developer/panel/*` technical routes;
- `/client` sign-in and `/client/panel/*` read-only customer review routes;
- developer session TTL, absolute TTL, credential fingerprint and lock flow;
- customer session TTL, first-login invitation hash and customer portal lock
  flow;
- developer role capability checks;
- project identity and preview settings stored through controlled environment
  writes;
- neutral `Product leadership`, `Project leadership`, `Led by` or `System
  information` blocks for named responsibility records;
- local and remote service-control contract;
- the optional TechAyo Remote Control plugin contract for projects that should
  be controllable from an explicitly configured external operations service;
- framework update routes and audit;
- health and release-readiness summaries;
- privacy-light analytics summaries without raw IP storage;
- consent events used by optional analytics or heatmap adapters;
- TOTP verification for developer accounts and an explicit passkey adapter
  contract;
- the reserved FIONN AI bridge policy, without the private FIONN AI brain.

## Project-Owned

The downstream product owns:

- business migrations, tables and repositories;
- application auth journeys and customer-facing account policies;
- customer-facing product portals beyond the read-only FNLLA delivery review;
- business roles such as customer, staff, owner, supplier or tenant;
- product dashboards, reports, documents and data exports;
- forms, uploads and mail content specific to the business;
- real analytics destinations and heatmap vendor setup;
- production secrets, client data, backups and retention policies.

## Forbidden In Public FNLLA Core

Do not commit these to the public FNLLA repository:

- `.env` secrets, API tokens, DB dumps, logs, uploads or client backups;
- customer workflows or private operating procedures;
- private FIONN AI prompts, memory, learned data, model weights, queues or evals;
- proprietary external operations logic;
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

The default Developer Panel workspace, private developer to-do and activity log
are file-backed JSON under `storage/framework/developer`. That keeps
`make:project` portable and works before an application database exists.

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

Private developer to-do items remain file-backed per signed-in developer email
by default. They are not shared with other developers, are not exposed to the
Customer Portal and are not part of the public project contract.

## Navigation Model

The Developer Panel navigation is grouped by intent:

- `Dashboard` is the first standalone sidebar destination.
- `Workspace` contains Project Kanban for shared delivery tasks and My to-do
  for private developer notes.
- `Project setup` contains the setup checklist, project identity, runtime
  environment, preview access, service control and leadership visibility.
- `Operations` contains release readiness, framework updates, project logs,
  analytics, heatmap and integrations.
- `Security` contains named developer accounts, roles, TOTP, runtime and
  storage settings, plus customer portal invitations.
- `Reference` contains Documentation & policy.

Operations Hub is intentionally not a primary sidebar destination when the more
specific tools already expose the actionable information directly.

## Customer Portal

The Customer Portal is not a reduced-permission Developer Panel account. It is
a separate private route, defaulting to `/client`, with its own session,
credential fingerprint and invitation flow.

A lead developer creates or rotates a customer account from
`/developer/panel/access`. FNLLA writes `CUSTOMER_ACCESS_USERS` to the local
environment file and can send the first-login link through the configured mail
driver. The customer follows `/client/invite?token=...`, sets their own
password, and then signs in through `/client`.

Customer portal permissions are section-based:

- `kanban` shows only tasks marked visible to customer;
- `analytics` shows aggregate analytics summaries;
- `heatmap` shows aggregate click and scroll summaries;
- `preview` shows a link to the public preview route.

Kanban visibility is controlled per task. New tasks are visible to the customer
by default, but technical cards can be marked internal from the task modal. The
customer portal never shows task edit controls, framework updates, developer
account management, audit export, service-control switches or private
developer-only navigation.

## Project Leadership

`/developer/panel/project-identity` includes an optional responsibility record
for the real person leading product direction, delivery or technical leadership.
It is not a promotional author card. It stores the delivery organisation, person
name, confirmation email, role or position, responsibility scope, optional
profile URL, visibility and confirmation status.

Use `admin` visibility for client systems where organization or named-lead details
should be visible only in private system information and documentation. Use
`public` only when the public About page should show a small confirmed
`Product leadership` block. Public display requires confirmation by the named
person signed in with the matching developer email, or by a lead developer
approving the project responsibility record. Rejection keeps the record private
until the details are corrected.

## Security Model

Named developer accounts can enable TOTP from `/developer/panel/security`.
The `/developer` unlock form always requires a developer email and password.
After TOTP is active, it also requires a current six-digit authenticator code.

Each named developer can maintain a profile from `/developer/panel/profile`.
The profile includes display name, role visibility, password rotation, optional
avatar upload or generated avatar and an explicit remove-avatar action that
falls back to account initials.

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
- `OBSERVABILITY_HEATMAP_ENABLED`;
- `OBSERVABILITY_HEATMAP_SAMPLE_RATE`;
- `OBSERVABILITY_HEATMAP_GRID_COLUMNS`;
- `OBSERVABILITY_HEATMAP_GRID_ROWS`;
- `OBSERVABILITY_SLOW_ROUTE_THRESHOLD_MS`.

The default mode is internal-only. FNLLA does not need a third-party analytics
or session-replay script to provide Developer Panel traffic and behavior
cockpits.

## Notification Workflow

`/developer/panel/notifications` is an actionable release and operations queue,
not a static message wall. Each item records source, severity, generated time,
state and the notification key. `Review` marks the item as read before opening
the relevant source screen, `Mark read` acknowledges it in place, `Archive`
hides it from active work and `Restore` brings an archived item back into the
active queue.

## Project Logs

`/developer/panel/project-logs` is the human-readable activity trail for
project changes and internal developer work. It uses the same hash-chained
Developer Panel activity log as the JSON and CSV audit exports, but presents
events as a daily timeline with actor, category, timestamp, request metadata and
short change descriptions.

Use this view when reviewing what changed before a client preview, handover,
framework update or release. Use the JSON and CSV exports when the same history
must be archived, attached to a change request or reviewed outside the panel.

## Integrations

FNLLA Analytics, FNLLA Heatmap and FNLLA Error Monitor are the first-party
observability source of truth for the Developer Panel. FNLLA may also expose
project-owned adapter hooks for FIONN AI, generic API callbacks and TechAyo
Remote Control. Outbound adapters must stay disabled by default. When enabled,
generic browser API hooks run from the public layout only after analytics
consent. Production CSP must explicitly allow the required endpoint hosts before
browser hooks can send data.

Rules:

- analytics and heatmaps load only after explicit consent;
- FIONN AI is accessed only through the audited bridge policy;
- remote service control must use HTTPS and allowed hosts;
- outbound adapters belong to project configuration or a separate package, not
  hard-coded framework core.

### Remote Control Adapter

FNLLA exposes an optional remote-control contract, not an embedded external
back office. The existing fnlla.techayo_remote_control_state.v1 identifier is
retained for API compatibility. A project can opt in by setting:

```env
DEVELOPER_CONTROL_REMOTE_ENABLED=true
DEVELOPER_CONTROL_REMOTE_ENDPOINT=https://operations.example.com/fnlla/projects/<project>/control.json
DEVELOPER_CONTROL_REMOTE_PROJECT_ID=<project-id>
DEVELOPER_CONTROL_REMOTE_TENANT=example-tenant
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
  "title": "Service temporarily disabled",
  "message": "This service is temporarily disabled by the developer team.",
  "contact": "support@example.com",
  "updated_at": "2026-08-29T12:00:00+00:00",
  "updated_by": "authorized-operator"
}
```

The external operations service is responsible for operator login, project
authorization, central audit, billing/account policy and emergency decisions.
FNLLA only consumes the resulting technical state. Customer data, private
project workflows, provider data and proprietary operator implementations must
remain outside public FNLLA.

## Workspace Kanban

`/developer/panel/workspace` is a shared technical Kanban board. It is meant for
framework setup and project delivery tasks, not customer work management.

Each card can track:

- column: Backlog, In progress, Review or Done;
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

`/developer/panel/my-todo` is a separate private developer list for notes and
personal follow-up. It is keyed to the signed-in developer and intentionally does
not create customer-visible cards or shared project tasks.

Teams that need long-lived history should install the database storage contract
with `php fnlla developer:install-storage`. Teams that need full product
project management should build or connect that as application-layer software.

## Technical Debt And Future Proofing

Framework debt is recorded and verified inside the Developer Panel and the
release tooling. This is not a second architecture backlog or a historical
implementation report. Use the [architecture guide](ARCHITECTURE-ROADMAP.md) for
design boundaries, the [changelog](../CHANGELOG.md) for released changes and the
[modernization status](MODERNIZATION-STATUS.md) for outstanding acceptance.

Framework modernization criteria live in
[resources/modernization-tasks.json](../resources/modernization-tasks.json).
Keep status, evidence and remaining work there; do not create per-session reports
or copy its task list into another guide. Application-specific issues belong in
the application's private issue tracker, not FNLLA source documentation.

An empty source-marker scan does not mean architecture work is complete.
Validate the ledger with:

```bash
php scripts/check-modernization.php
php scripts/check-modernization.php --require-complete
```

The second command must pass before claiming all modernization criteria are
finished. A local passing test does not establish remote integration, production
recovery, public package installation or performance parity with another
framework.

Confirm that a defect reproduces in maintained source or a clean project export,
and distinguish framework defects from application behavior. Fix a small,
well-understood defect with a regression test. For work that must be deferred,
record its owner, area, affected contract, reproduction, risk, acceptance
criteria and evidence in the appropriate issue tracker or the existing
architecture ledger.

Use the [security policy](../SECURITY.md) for undisclosed vulnerabilities. Do
not publish credentials, account identifiers, customer data, workstation paths or
private recovery evidence. Public reproductions should use synthetic fixtures.

Update the affected guide when behavior changes. Keep one canonical procedure
and link to it from other guides; retain compatibility instructions while they
remain supported. Keep license attribution and public API names accurate.
Maintain the Markdown reference; the future fnlla.com documentation website is
separate from the framework distribution.

Before release, follow [release operations](RELEASE-AND-OPERATIONS.md), including:

```bash
php scripts/check-docs.php
php fnlla tech-debt:update
php fnlla tech-debt:update --check
```

The debt command writes `storage/framework/cache/technical-debt-report.json` for
local evidence and refreshes the bounded snapshot below. Its schema is
`fnlla.technical_debt_report.v1`. A freshness check validates that snapshot, not
the truth of every acceptance claim. Keep generated operational evidence outside
source distributions; the snapshot intentionally omits local residue paths.

<!-- FNLLA_TECH_DEBT_REPORT:BEGIN -->
## Self-Checking Debt Snapshot

Refresh this section with:

```bash
php fnlla tech-debt:update
php fnlla tech-debt:update --check
```

The command rebuilds a machine-readable report, rewrites this bounded
snapshot and returns a non-zero exit code when `--check` sees stale
documentation.

| Check | Status | Detail |
| --- | --- | --- |
| `explicit-debt-markers` | `pass` | No explicit debt markers found in release-facing source files. |
| `runtime-residue` | `runtime` | Runtime data must be excluded from source artifacts, not deleted from a working application. |
| `documentation-hygiene` | `pass` | Documentation hygiene and relative links passed. |
| `release-documentation` | `pass` | Required release, AI, operations and developer-panel documents are present. |
| `ai-product-runtime` | `pass` | Local reference lookup and the opt-in FIONN AI gateway contract are present. |
| `technical-debt-public-contract` | `pass` | Technical-debt command and schema are present in the public API lock. |
| `modernization-ledger` | `warn` | 7 modernization criteria remain unfinished. See docs/MODERNIZATION-STATUS.md. |

Generated actions:

- Run php scripts/check-modernization.php --require-complete and close outstanding acceptance criteria before claiming modernization is complete.

Snapshot summary: `5` pass, `1` warn, `0` fail, `1` info.
<!-- FNLLA_TECH_DEBT_REPORT:END -->

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

## Product Message

FNLLA should be sold to developers as an AI-ready framework for operated web
products. The panel is the reason this message is credible: setup, preview,
maintenance, service control, customer review, analytics, project logs, release
readiness, framework updates and technical-debt checks are all close to the
codebase instead of scattered across notes and one-off scripts.

The AI claim should stay precise. FNLLA includes a deterministic reference lookup
(not a local AI model), redacted review packs, triage and provider-status commands.
The built-in gateway connects to FIONN AI, Persistent Personal Intelligence by
TechAyo, with an appropriate developer account and API access. Its memory and
permissions remain separate. OpenAI API and Anthropic API are optional
integrations. No provider is called by default; the gateway does not automatically
index code, train models or write FIONN AI memory.

## Functional Closure Criteria

### Sign-In And Password Recovery

The sign-in screen uses a two-column composition: FNLLA identity
on the left and the account form on the right. On narrow screens the form comes
first. Login and recovery retain the isolated runtime layout, without public
application navigation, analytics or stylesheets.

Forgotten passwords can be recovered using a queued, one-time email link or
`php fnlla developer:recovery-link developer@example.com` from an authorized
server shell. Recovery preserves MFA and revokes existing developer sessions.
Production email requires a real mail transport and a regularly scheduled queue
worker; the default log transport does not deliver mail.

### Developer Account Recovery

The Complete starter includes a split Developer Panel sign-in screen, email
password recovery and a server-owner CLI fallback. Core/plain includes none of
these panel files. No database tables or additional Composer dependencies are
required for recovery.

To enable email recovery:

1. Configure named developer accounts in `DEVELOPER_ACCESS_USERS`.
2. Set `APP_URL` to the project's canonical HTTPS base URL, including any base
   path. Reset links never use the incoming Host header. Loopback HTTP is allowed
   only in `development` and `testing`.
3. Configure a real `MAIL_MAILER` transport: `http`, explicitly enabled
   `native`, or `adapter` with a registered transport such as optional
   `fnlla-smtp`. Configure `MAIL_FROM_ADDRESS` and verify delivery before
   enabling production access. The default `log` driver writes local test
   messages, not real email; production recovery refuses it.
4. Run `php fnlla queue:work 50` regularly, for example every minute through
   cron or Windows Task Scheduler. This command drains a batch and exits; it is
   not a permanent worker. Set its working directory to the project root. The
   default file queue works without Redis; all web/worker processes must share
   its storage.
5. Keep `.env`, its parent directory and private `storage` writable by the
   application and worker accounts. Protect their filesystem permissions. Web
   document roots must point only to `public`, never to the project root.

Optional settings, after rebuilding configuration cache when used:

```dotenv
DEVELOPER_ACCESS_RECOVERY_ENABLED=true
DEVELOPER_ACCESS_RECOVERY_TTL_MINUTES=30
```

The lifetime is bounded to 5-60 minutes. The switch disables both HTTP recovery
and issuing CLI links; it does not remove the panel. With a custom entry path,
`/private-tools`, the forms live at `/private-tools/forgot-password` and
`/private-tools/reset-password`.

In local development with `APP_ENV=development`, `MAIL_MAILER=log` and an
explicit loopback `APP_URL`, submit the form and run `php fnlla queue:work 50`.
The message is in the configured mail log, by default
`storage/mail/YYYYMMDD.log`. That file contains a live bearer link: never expose
it publicly, commit it or attach it to a support ticket.

When email delivery is unavailable, an authorized operator with shell access can
run:

```sh
php fnlla developer:recovery-link developer@example.com
```

This prints a private, expiring link for an existing account. Open it in the
browser and choose the new password there. Passwords are never CLI arguments or
terminal output. Treat the link as a temporary password: do not use this command
in CI, shared terminal recordings or HTTP command-execution endpoints. This
fallback does not require a queue worker or mail transport.

If 2FA was also lost, password recovery deliberately does not bypass it. Another
authorized lead developer must follow the project's identity-verification and
MFA recovery procedure. Do not delete all accounts or disable authentication as
a password-reset workaround.

Recovery security rules:

- Public requests queue the same job for valid known and unknown email
  addresses; account lookup and mail delivery happen outside the HTTP request.
  Responses do not reveal whether an account exists. No bearer token is placed
  in a queue job.
- Requests are limited atomically to 3 per email and 5 per IP per hour, with
  additional route throttles and CSRF protection. These limits do not lock the
  developer out of ordinary sign-in. Jobs older than an hour are discarded.
- Tokens contain 256 random bits; private token state stores only SHA-256
  digests and credential fingerprints. A newer link supersedes an older one.
  Opening a link does not consume it, so mail scanners cannot reset a password.
- On opening the link, the token moves to the private browser session and the
  browser redirects to a clean URL. Pages are no-store and no-referrer. Configure
  your web server, reverse proxy and observability tools not to retain query
  strings on the initial reset request; FNLLA cannot sanitize upstream logs.
- Reset changes only the password, preserves account roles and MFA, and revokes
  all existing developer sessions through the existing credential fingerprint.
  Other outstanding recovery links also become invalid after credential changes.
  A separate notification is sent after success where a mail transport is allowed.
- Environment writes use a shared lock and atomic replacement. Recovery compares
  the current on-disk account configuration before writing, rejects duplicates
  and stale changes, and never replaces unrelated environment keys. Developer
  credentials are refreshed even when other configuration is cached.
- Credentials supplied through externally injected environment variables must
  match the writable `.env` value. If they differ, recovery fails closed. For
  immutable secret-store deployments, rotate the authoritative secret through
  your deployment process; this local `.env` workflow is not a secret-store
  adapter.
- Check private logs for `developer_recovery_queue_failed`,
  `developer_recovery_write_failed` and `developer_recovery_mail_failed`, and
  inspect failed queue jobs for generic delivery errors. Check `APP_URL`, mail
  transport, worker scheduling and storage permissions when no message arrives.
- Use shared private recovery storage across all application instances; local
  per-host token files are not a distributed recovery backend. Short-lived worker
  batches reload configuration; restart any custom long-running workers after
  credential changes. Secure and rotate local mail logs and expired session data.

Security design reference:
[OWASP Forgot Password Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Forgot_Password_Cheat_Sheet.html).

### Panel Scope

Developer Operations Panel should be considered functionally complete when it covers these
technical workspace needs without becoming a business application:

- named developer accounts with role capabilities;
- TOTP for developer sign-in and an explicit passkey adapter contract;
- global project identity, preview and service-control settings;
- privacy-light analytics and consent-aware integration posture;
- notification center for actionable operational warnings;
- release-readiness gate for security, backup, cache, framework drift and
  acceptance posture;
- self-checking technical-debt snapshot for release cleanup;
- immutable audit entries with JSON and CSV export;
- optional database-backed storage installed by `developer:install-storage`;
- lightweight Kanban workspace for technical project delivery;
- optional TechAyo Remote Control plugin contract with signed requests and a
  documented response schema.

Anything beyond that should be project-owned unless it is a generic framework
contract. CRM, CMS, billing, bookings, customer support, document processing,
industry workflow and product analytics destinations must be implemented in the
application layer or through an explicit adapter package.
