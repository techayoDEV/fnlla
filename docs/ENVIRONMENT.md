# Environment Configuration

## Upload Prerequisite

Enable PHP `ext-fileinfo` in both the web SAPI and CLI when validating upload MIME
types. Check `php --ri fileinfo` for CLI and verify the web server's PHP configuration
separately. FNLLA detects MIME from file bytes; `UploadedFile::mimeType()` exposes
untrusted client metadata only. Missing Fileinfo or failed detection causes MIME
validation to throw instead of trusting the client's header. PHP 8.5 is supported
without calling the deprecated `finfo_close()` function.

FNLLA uses two environment templates:

- `.env.example` is the short starter for a new project.
- `.env.full.example` is the complete reference for operators and maintainers.

New developers should start with `.env.example`. Production owners, CI
maintainers and teams enabling optional adapters should review
`.env.full.example` and this document before deployment.

## Why The Starter Is Short

New integrated exports leave `FNLLA_MODULE_WORKSPACE`, `FNLLA_MODULE_ANALYTICS`,
`FNLLA_MODULE_HEATMAP` and `FNLLA_MODULE_CUSTOMER_PORTAL` true. Panel Settings
and explicit environment values can disable unwanted modules without installing
a second framework.
Selecting heatmaps also enables analytics; disabling modules preserves data.
Updates preserve explicit settings and the opt-in marker used by earlier exports.
Enabling modules does not bypass authentication, privacy consent or debug guards.

Persistent sessions enforce idle expiry via `SESSION_LIFETIME_MINUTES` (120) and
absolute expiry via `SESSION_ABSOLUTE_LIFETIME_MINUTES` (720). Expiry clears every
session identity and transient session data. See [runtime contracts](framework/RUNTIME-CONTRACTS.md).

A new FNLLA application should not begin with a wall of advanced switches. The
first decisions are usually:

- local or production environment;
- project name and optional browser-title slogan;
- application URL and optional asset URL;
- database credentials;
- mail transport;
- maintenance/client-preview password;
- developer maintenance access;
- whether runtime AI stays local or uses the FIONN AI bridge.

Everything else has framework defaults in `config/*.php`. Keep `.env` focused on
values that really differ per environment.

## File Responsibilities

`.env.example` is safe to copy into `.env` during local development. It contains
no secrets and should stay readable enough for a first project export.

`.env.full.example` is a catalogue. It names advanced keys for framework
updates, Redis, session hardening, CORS, CSP, mail HTTP relays, observability,
release signing and runtime AI tuning. Do not copy it blindly into production.
Use it to discover available knobs, then move only needed values into the real
environment.

The real `.env` belongs to the target machine, hosting secret store or CI secret
configuration. It must not be committed.

## Official FNLLA Identity

The framework has its own product identity that is separate from each
downstream website or application:

```dotenv
FNLLA_OFFICIAL_DOMAIN=fnlla.com
FNLLA_OFFICIAL_URL=https://fnlla.com
FNLLA_SUPPORT_EMAIL=support@fnlla.com
FNLLA_MAIL_FROM_ADDRESS=noreply@fnlla.com
FNLLA_MAINTAINER_URL=https://techayo.co.uk
```

Use these keys only for framework metadata, documentation, update metadata and
FNLLA-owned communication defaults. A downstream project should still set
`APP_NAME`, `APP_URL`, `MAIL_FROM_ADDRESS` and `CONTACT_NOTIFICATION_EMAIL` to
its own product, domain and mailbox. `make:project` keeps `APP_URL` empty and
uses neutral mail placeholders until the project owner configures them.

## Configuration Layers

FNLLA applications have three practical layers:

- Framework layer: `bootstrap/`, `src/`, `config/`, `public/vendor/fnlla-runtime/`
  and project-facing CLI commands. This layer is updated from official FNLLA
  releases.
- Application layer: product routes, controllers, views, migrations, seeders,
  repositories, tests and user-facing content. This layer belongs to the
  downstream product team.
- Environment layer: `.env`, storage, logs, queue state, uploads, backups,
  deployment secrets and hosting configuration. This layer is never overwritten
  by framework updates.

`make:project` exports a working application base. After `project:claim`, the
project should treat FNLLA as the lower framework/runtime layer and build the
commercial product above it.

## Local Development Profile

Recommended local starter values:

```dotenv
APP_ENV=development
APP_DEBUG=true
APP_NAME=FNLLA Project
APP_TAGLINE=
APP_BRAND_LOGO=auto
APP_URL=http://127.0.0.1:8080
SESSION_SECURE=false
DB_HOST=127.0.0.1
DB_DATABASE=fnlla
DB_USERNAME=root
DB_PASSWORD=
MAIL_MAILER=log
```

Start the local server:

```bash
php -S 127.0.0.1:8080 -t public public/router.php
```

Then run:

```bash
php fnlla project:acceptance --json
php scripts/test.php
php scripts/lint.php
```

## Production Profile

Production `.env` should be minimal and strict:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_NAME=Example Business App
APP_TAGLINE=Operations delivered clearly
APP_BRAND_LOGO=auto
APP_URL=https://example.com
SESSION_SECURE=true
TRUSTED_HOSTS=example.com,www.example.com
TRUSTED_PROXIES=
SECURITY_HEADERS_PRESET=strict
STRICT_TRANSPORT_SECURITY=max-age=31536000; includeSubDomains
MAIL_MAILER=http
MAIL_HTTP_ENDPOINT=https://mail-relay.example.com/send
MAIL_HTTP_TOKEN=replace-with-secret
MAIL_HTTP_ALLOWED_HOSTS=mail-relay.example.com
```

Production-specific secrets should come from the platform secret store where
possible. A committed file should never contain database passwords, API tokens,
private keys, customer data, backup credentials or FIONN AI service tokens.

`APP_TAGLINE` is optional and is appended only to browser document titles. For
example, `Contact | Example Business App - Operations delivered clearly`. Leave
it empty if the product already has a short enough project name. `APP_BRAND_LOGO`
points to the public asset used as the header and browser icon mark. The default
`auto` value uses the canonical outline favicon only while `APP_NAME` is still `FNLLA`;
after the project is named, the public brand mark falls back to generated
initials unless the project sets its own public asset path or URL. Set the value
to `none` or leave it empty when no image mark should be rendered.

## Project Leadership

FNLLA supports an optional responsibility record for projects where the product
or delivery lead should be named without turning the footer or About page into a
marketing byline.

```dotenv
PROJECT_LEADERSHIP_ORGANIZATION=TechAyo Limited
PROJECT_LEADERSHIP_PERSON_NAME="Name Surname"
PROJECT_LEADERSHIP_PERSON_EMAIL=lead@example.com
PROJECT_LEADERSHIP_PERSON_ROLE="Director of TechAyo"
PROJECT_LEADERSHIP_RESPONSIBILITY="product direction, roadmap and technical delivery"
PROJECT_LEADERSHIP_PROFILE_URL=https://example.com/contact
PROJECT_LEADERSHIP_VISIBILITY=admin
PROJECT_LEADERSHIP_STATUS=pending
```

Visibility modes:

- `disabled` keeps the block off.
- `admin` shows the record only in the Developer Panel and documentation.
- `public` allows public display only after the named person confirms it from a
  matching developer account or a lead developer approves the project record.

Changing the named person, email, role, organisation, responsibility scope or
profile URL resets confirmation to `pending`. This lets a project developer
prepare the record without falsely presenting another person as responsible for
product direction or technical delivery.

## Client Preview

Client preview is maintenance mode with a clear user-facing purpose: the site is
online for private review, but public access is password-protected.

Minimal preview setup:

```dotenv
MAINTENANCE_MODE_ENABLED=true
MAINTENANCE_ACCESS_PASSWORD=replace-with-client-preview-password
CLIENT_PREVIEW_ENABLED=true
CLIENT_PREVIEW_TITLE=Private client preview is active
CLIENT_PREVIEW_STATUS_TITLE=Password-protected preview mode is enabled
```

Rotate the preview password before sharing with a real client and again before
public launch.

## Developer Access And Service Control

Developer access is separate from client preview. Client preview protects the
public site for customer review. Developer access unlocks operational screens
for the team maintaining the project.

FNLLA 2.2.0 supports named developer accounts:

```dotenv
DEVELOPER_ACCESS_ENABLED=true
DEVELOPER_ACCESS_EMAIL=lead@example.com
DEVELOPER_ACCESS_USERS=
```

`DEVELOPER_ACCESS_USERS` is managed by the developer panel after first setup.
Each account has its own email login, display name, role and password hash. The
project settings changed from any developer account remain global because they
belong to the application environment, not to one developer session.

The panel also keeps a shared developer activity log. When one developer rotates
preview access, updates identity, changes panel settings or disables the public
service, other unlocked developer sessions can see that operational event in the
dashboard.

Developer workspace and activity storage are file-backed by default:

```dotenv
DEVELOPER_ACTIVITY_LOG_DRIVER=file
DEVELOPER_ACTIVITY_LOG_PATH=framework/developer/activity.jsonl
DEVELOPER_ACCESS_TOTP_ISSUER="${APP_NAME}"
DEVELOPER_WORKSPACE_DRIVER=file
DEVELOPER_WORKSPACE_PATH=framework/developer/workspace.json
DEVELOPER_PRIVATE_TODO_PATH=framework/developer/private-todos.json
DEVELOPER_NOTIFICATIONS_STATE_PATH=framework/developer/notifications-state.json
```

For a production team that expects frequent developer-panel changes, switch the
technical workspace and activity log to database storage after the project
database is configured:

```dotenv
DEVELOPER_ACTIVITY_LOG_DRIVER=database
DEVELOPER_ACTIVITY_LOG_TABLE=fnlla_developer_activity_log
DEVELOPER_WORKSPACE_DRIVER=database
DEVELOPER_WORKSPACE_TABLE=fnlla_developer_workspace_state
DEVELOPER_NOTIFICATIONS_TABLE=fnlla_developer_notifications
DEVELOPER_ANALYTICS_EVENTS_TABLE=fnlla_developer_analytics_events
```

These tables are still technical operations data. Business records, customer
activity, product audit events and application admin workflows belong to the
downstream application schema, not to FNLLA core.

`DEVELOPER_PRIVATE_TODO_PATH` stores per-developer private notes and personal
tasks. It is local technical state, keyed to the signed-in developer email, and
is separate from the shared Kanban workspace and Customer Portal.

## Customer Portal Access

Customer access is intentionally separate from Developer Panel access. A lead
developer can create a customer account in **Access & security**, copy the
first-login link or send it through the configured mail driver. The customer
sets their own password from that invitation and then signs in through the
private customer URL.

```dotenv
CUSTOMER_ACCESS_ENABLED=true
CUSTOMER_ACCESS_PATH=/client
CUSTOMER_ACCESS_USERS=
CUSTOMER_ACCESS_INVITE_TTL_HOURS=72
CUSTOMER_ACCESS_TTL_MINUTES=240
CUSTOMER_ACCESS_ABSOLUTE_TTL_MINUTES=720
```

`CUSTOMER_ACCESS_USERS` is managed by the panel after invitations are created.
It stores the customer email, display name, optional company, allowed portal
sections, a password hash after first login and a short-lived invitation hash.
Do not hand-edit it unless you are rotating access during deployment.

The customer portal is read-only. It can expose the client-visible Kanban
cards, aggregate analytics, aggregate heatmap summaries and a public-preview
link. Individual Kanban tasks can be hidden from the customer while remaining
available to developers. The portal is not a CRM, billing system or general
customer database.

## Local Developer Analytics

FNLLA ships first-party analytics for the Developer Panel. It is intentionally
aggregate-only: page views, top routes, host-only referrers, device buckets,
form submissions, slow-route counts, response-time averages and configured
goals. It does not store raw IP addresses, raw user agents or browser
fingerprints.

```dotenv
OBSERVABILITY_METRICS_ENABLED=true
OBSERVABILITY_METRICS_PATH=framework/metrics.json
OBSERVABILITY_ANALYTICS_ENABLED=true
OBSERVABILITY_ANALYTICS_RETENTION_DAYS=90
OBSERVABILITY_ANALYTICS_SAMPLE_RATE=100
OBSERVABILITY_ANALYTICS_BOT_FILTERING=true
OBSERVABILITY_ANALYTICS_DEVICE_DETECTION=true
OBSERVABILITY_ANALYTICS_TRACK_QUERY_STRINGS=false
OBSERVABILITY_REGULATED_MAX_RETENTION_DAYS=30
OBSERVABILITY_REGULATED_EXCLUDED_PATHS=/developer,/maintenance,/client,/api
OBSERVABILITY_REGULATED_HEATMAP_ENABLED=false
OBSERVABILITY_SLOW_ROUTE_THRESHOLD_MS=750
DEBUG_TOOLBAR=false
DEBUG_REQUEST_HISTORY=false
DEBUG_RUNTIME_ISSUES=true
```

The observability analytics values can be changed from
`/developer/panel/analytics` by a developer role with panel-settings permission.
FNLLA writes only these explicit environment keys and records the change in the
developer audit log.

Operations / Observability links to Error Monitor, which exposes
`/developer/panel/debug/live` for the signed-in developer panel and public-page
debug toolbar. It returns local aggregate diagnostics, bounded request-history
entries and runtime issue counts. Runtime
issue tracking stores deduplicated 500-level issue fingerprints in
`storage/framework/developer/runtime-issues.json`; it does not store exception
messages, traces, headers, cookies, bodies, SQL text or bindings. A developer
with workspace permission can promote a runtime issue candidate into the
Technical debt register when it represents actual debt and can choose whether a
linked shared Kanban card should be created.

`/developer/panel/project-identity#runtime-environment` can switch the runtime
between `development` and `production` for a configured project. The panel writes
only the controlled runtime keys `APP_ENV`, `APP_DEBUG`, `DEBUG_TOOLBAR`,
`DEBUG_REQUEST_HISTORY` and `TRUSTED_HOSTS`. Selecting `production` forces debug
output, the toolbar and request history off, then surfaces HTTPS `APP_URL` and
trusted-host readiness in the setup checklist.

Developer service control is a stronger lock than client preview:

```dotenv
FNLLA_POLICY_PROFILE=standard
FNLLA_REGULATED_MODE=false
DEVELOPER_CONTROL_DISABLED_CONTACT=developer@example.com
DEVELOPER_CONTROL_SERVICE_PROVIDER=TechAyo Limited
DEVELOPER_CONTROL_SUSPENDED_TITLE=Services suspended
DEVELOPER_CONTROL_SUSPENDED_MESSAGE=Your services have been suspended. Please contact your service provider.
DEVELOPER_CONTROL_REMOTE_ENABLED=false
DEVELOPER_CONTROL_REMOTE_ENDPOINT=
DEVELOPER_CONTROL_REMOTE_TOKEN=
DEVELOPER_CONTROL_REMOTE_PROJECT_ID="${PROJECT_ID}"
DEVELOPER_CONTROL_REMOTE_TENANT=techayo
DEVELOPER_CONTROL_REMOTE_SIGNATURE_SECRET=
```

When enabled locally, public routes and the customer portal return a
service-disabled screen and API requests return `503` JSON. Developer routes
stay reachable so the team can recover the site.

`FNLLA_POLICY_PROFILE=regulated` or `FNLLA_REGULATED_MODE=true` switches the
Developer Panel into a stricter operating profile. It keeps telemetry
consent-only, caps analytics retention, disables query-string tracking and
requires extra framework-apply evidence in production-like workflows.

Browser-based framework apply remains disabled in regulated or production mode
until the project sets all required evidence flags:

```dotenv
FRAMEWORK_UPDATE_APPLY_BACKUP_CONFIRMED=false
FRAMEWORK_UPDATE_APPLY_SIGNED_ARTIFACT_CONFIRMED=false
FRAMEWORK_UPDATE_APPLY_MAINTENANCE_WINDOW_CONFIRMED=false
FRAMEWORK_UPDATE_APPLY_CI_APPROVAL_CONFIRMED=false
```

The remote control keys describe an optional external operations contract.
FNLLA does not include an external operator's business logic or private data. A
remote control endpoint must be HTTPS, token protected and host-allowlisted
before `security:audit --strict` accepts it. When enabled, FNLLA sends the
project id, tenant, schema, timestamp, bearer token and optional HMAC signature
headers, then consumes only the `fnlla.techayo_remote_control_state.v2`
`open`, `disabled` or `suspended` state. The `suspended` status is intended for
external provider or billing decisions, shows the configured service-provider
message and does not grant server access or application admin privileges to the
remote operator.

Developer Panel changes are project-global and are written to the shared
activity log. Review queue read, archive and restore state is keyed per signed-in
developer, so one developer can clear their own queue without hiding the same
global change notification from other developers.

## Runtime AI

The default runtime AI driver is local:

```dotenv
AI_RUNTIME_ENABLED=true
AI_RUNTIME_DRIVER=local
AI_RUNTIME_PATH=resources/fnlla-ai-runtime
AI_RUNTIME_LEARNING_ENABLED=false
```

Local runtime AI uses framework and project-owned knowledge only. It does not
call a model provider.

## FIONN AI Bridge

**FIONN AI is created by TechAyo.** The full starter ships its dedicated gateway
as part of FNLLA. Connect a separately operated AI brain through explicit server
configuration; its models and memory are not copied into the framework.
The local assistant stays the default. OpenAI API and Anthropic API are
additional optional integrations described in [AI configuration](AI-CONTEXT.md#openai-api-and-anthropic-api).

The public FNLLA repository contains only the bridge:

- selected provider resolution through `runtime_ai()`;
- normalized `fnlla.runtime_ai.answer.v1` responses;
- `POST` calls to the configured chat path;
- `learning_mode=false` on FNLLA-originated requests;
- sensitive context-key redaction;
- endpoint host allowlisting;
- HTTPS and service-token requirements for non-local endpoints;
- `ai:providers` status for CI and operators;
- `security:audit --strict` policy checks.

The FIONN AI brain stays outside FNLLA:

- model files;
- memory;
- reviewed knowledge;
- learning queues;
- evals;
- admin/training controls;
- private implementation code.

Local FIONN AI bridge example:

```dotenv
AI_RUNTIME_DRIVER=fionn
AI_FIONN_BRIDGE_ENABLED=true
AI_FIONN_ENDPOINT=http://127.0.0.1:8765
AI_FIONN_CHAT_PATH=/api/chat
AI_FIONN_ALLOWED_HOSTS=127.0.0.1,localhost
AI_FIONN_ALLOW_INSECURE_LOCALHOST=true
```

Non-local FIONN AI bridge example:

```dotenv
AI_RUNTIME_DRIVER=fionn
AI_FIONN_BRIDGE_ENABLED=true
AI_FIONN_ENDPOINT=https://fionn.internal.example
AI_FIONN_CHAT_PATH=/api/chat
AI_FIONN_ALLOWED_HOSTS=fionn.internal.example
AI_FIONN_API_TOKEN=replace-with-service-token
AI_FIONN_ALLOW_INSECURE_LOCALHOST=false
```

Before release, verify:

```bash
php fnlla ai:providers --json
php fnlla security:audit --strict
```

If FIONN AI is selected, the provider must report `provider_ready=true` and
`endpoint_allowed=true`.

## Operational Rule

Keep committed environment templates useful but boring. Real secrets, runtime
state and customer data belong in environment storage, not in Git. Optional
capabilities should be visible in `.env.full.example`, explained in docs and
enabled in production only after the matching audit command passes.
