# Starting a New Project with FNLLA

## Short answer

Do not treat the `techayoDEV/fnlla` repository itself as the normal place where a new client website or new web application should be built.

The recommended workflow is:

1. Keep `techayoDEV/fnlla` as the framework source and project-export base.
2. Export a new working project into its own directory.
3. Give that new directory its own project name and its own Git repository.
4. Build the actual website or application there.

## Why not just clone fnlla and build directly inside it

If you clone `techayoDEV/fnlla` and start editing it directly for every new website, you mix together two different concerns:

- framework maintenance
- one specific downstream project

That quickly becomes messy because:

- framework repo metadata stays mixed with project metadata
- project-specific pages and migrations start polluting the framework source
- release history becomes harder to separate
- every new project starts from a manual copy decision instead of a repeatable process

## Official recommended workflow

Use the built-in project export command from the maintained `techayoDEV/fnlla` repository:

```bash
php fnlla make:project ../my-new-project "My New Project"
```

That command exports a clean working project base into a new directory outside the framework repository.

For a published release, download the explicit `fnlla-source.zip` attachment from
GitHub Releases, verify it against `fnlla-downloads.sha256`, and extract it as your
framework source. Do not substitute a moving `main` checkout when reproducing a
release. Before publication, these instructions apply only to an approved draft
or retained CI artifact, not an already available 2.2.0 download.

After export, run these commands from the new project directory:

```sh
composer install
php scripts/test.php
php scripts/lint.php
php fnlla route:list
```

Open the Full starter locally to complete Project Setup before deployment. There
is no default developer password. For production, serve only `public/`, configure
HTTPS and the canonical `APP_URL`, disable `APP_DEBUG`,
`DEVELOPER_ACCESS_SETUP_UI_ENABLED` and `MAINTENANCE_SETUP_UI_ENABLED`, and follow
the [production checklist](RELEASE-AND-OPERATIONS.md#production-readiness-checklist).
Verify real mail delivery and queue scheduling for email-based password recovery;
the documented server-owner recovery link is a separate fallback, not a mail test.

### One Integrated Starter

Omitting `--profile` creates the integrated FNLLA starter immediately, including
in a terminal. Project Setup creates the first developer account; the private
panel, UI runtime, diagnostics and updates are included. Workspace, analytics,
heatmaps and the customer portal are all enabled by default, including before
`.env` exists. Disable unwanted modules later in Panel Settings or environment
configuration.
Heatmaps also require analytics. These switches do not delete code or stored data.
Updates preserve explicit environment settings and any earlier installation's
`.fnlla/modules-opt-in` marker; new exports no longer create that marker.

For an explicit advanced installation chooser, including when piping input:

```powershell
php fnlla make:project ../my-project "My Project" --interactive
```

Choose `1`/`plain`, `2`/`full`, or `q` to cancel. Enter creates FNLLA (`full`).
Numeric aliases remain compatible. Scripts may pass `--profile=full` explicitly;
`--no-interaction` is retained. Input is never read unless `--interactive` is
explicit. `make:project --help` does not create files.

This is a choice during installation, not a browser switch after installation.
An already exported full project contains panel code; a web toggle cannot turn
it into the physically minimal Composer starter. Never expose a public endpoint
that deletes or replaces the installed framework.

### Advanced Core-Only Export

For an API or application with its own frontend and operations stack, omit the
panel, optional modules and UI distribution explicitly:

```powershell
php fnlla make:project ../my-api "My API" --profile=plain
```

Plain starts with `/`, `/api/health`, generic error pages and focused tests.
It keeps routing, middleware, validation, database, authentication, sessions,
cache, queues and mail. It does NOT ship panel services, maintenance/client
preview, analytics, heatmaps, Kanban, AI, UI assets or their update scripts.
Application classes live in `app/` under `App\\`; the engine is the independent
`techayodev/fnlla-core` Composer library in `packages/fnlla-core/`.

Run `composer install` in the export. The bundled path repository needs no
public package registry; Composer mirrors it into `vendor/`. Before installation,
the offline bootstrap resolves the bundled package directly. No absolute source
workspace paths are embedded. Test with `php scripts/test.php`, lint with
`php scripts/lint.php` and inspect routes with `php fnlla route:list`.

Plain uses Composer dependency updates, not `framework:update`. Its README
documents replacing the reviewed bundled package and updating the exact version.
A public Composer release channel is not yet published. Existing legacy plain
projects are not automatically converted: use a new export and migrate only
application-owned code/configuration. Changing the profile marker is not migration.

Full keeps `.env.full.example`, `VERSION` and `MANIFEST.json`; plain does not need
these integrated-distribution files. Both keep Composer metadata, a short
`.env.example`, `README.md` and the license at root. Full support/trademark
references live in `docs/framework/`. The remaining instructions on this page
describe full projects unless explicitly marked otherwise.

In full, the panel migration moves to `database/optional/developer-panel/`; normal
project migration runs do not create its tables. Preview their SQL with
`php fnlla developer:install-storage --dry-run` and deliberately install them
only when selecting database-backed tooling.

The UI guard checks shipped assets without enforcing a downstream site's
header, footer or CSS framework choice. `FNLLA_RUNTIME_VALIDATE_MARKUP=true`
opts into the maintainer markup checks. Network sync is explicit by default:
`php fnlla fnlla-runtime:sync`.

### Developer tools and ownership

Workspace / Project work links to Technical debt, which provides source-marker
scans and manual entries, owner, priority, due date, notes and
open/in-progress/accepted/resolved states.
Acceptance requires a reason and an `accepted_until` expiry. Items can carry
issue/PR, ADR and evidence references for release review. Scans preserve triage;
stale edits are rejected.
The existing `tech-debt:update` report and the triage register are separate:
accepting an item never suppresses a release check.

Operations / Observability links to Error Monitor, which controls an opt-in
toolbar. It requires `APP_DEBUG`, a local/development/testing environment and a developer session with
`operations.view`. Changing the switch additionally requires
`panel.settings.write`. Production, staging, guests, JSON, HEAD and downloads
do not receive a toolbar. `DEBUG_TOOLBAR=false` is the starter default.

The toolbar displays duration, peak memory, route, request ID and database
execution metrics. It stores no SQL text, bindings, cookies, form data or
exception messages. At most 100 query summaries are retained per request.
Direct PDO calls are not instrumented. The toolbar does not replace Xdebug.
Error Monitor also offers optional bounded request history, disabled by default,
plus a live JSON endpoint for the signed-in developer panel. It records only
authorized developer requests in non-production debug environments, including JSON responses.
It never stores URLs, request IDs, inputs, headers or response bodies. Unexpected
500-level exceptions are deduplicated as runtime issue candidates and can be
promoted manually to Technical debt; FNLLA does not auto-accept runtime errors as
technical debt. The default history window is 200 entries / one hour, pruned on
reads and writes; disabling clears the records. This is not production APM. See
`docs/DEVELOPER-PANEL.md#diagnostic-storage-and-limits`.

Private state lives under `storage/framework/developer/` and is not exported.
Public routes, page templates and `public/assets/app.css` are project-owned.
Panel controllers and `developer-panel.css/js` are framework-managed. Old
public-file lock entries are retired without deleting their files.

CSS is separated into `app-base.css` (shared foundations), `app.css` (public)
and `developer-panel.css` (tooling). Existing projects require an explicit
layout/CSS migration; the updater must not replace client UI automatically.

Treat that exported surface as the real beginning of the application itself.
Do not build a second public front beside it.
Replace and extend the exported routes, views, assets and controllers directly, while leaving maintenance, health and CLI as linked framework capabilities around the project.

## What the export gives you

The exported project already includes:

- the FNLLA runtime
- the built-in runtime under `public/vendor/fnlla-runtime/`
- routes, controllers and views
- MySQL config and migration support
- auth, sessions, cookies and CSRF foundations
- lint, test and runtime validation scripts
- a project-base acceptance command for runtime, storage and HTTP smoke checks
- an application base with public pages for `/`, `/about`, `/contact`, `/terms` and `/privacy`
- a working starter contact form at `/contact` with CSRF, validation, old
  input, flash feedback, honeypot spam friction and log-mail delivery
- reusable public page-title hero markup under `views/partials/page-hero.php`
- a local-first `/maintenance/framework-update` page with a GitHub-backed update flow
- an optional password-protected maintenance access screen for client preview or staged review
- a browser-based first-time maintenance setup flow available from `/maintenance` on a fresh local project export
- a project README that explains the next steps

It also avoids copying framework-maintainer-only surfaces such as:

- `.git`
- `.github`
- framework browser docs under `docs/`
- the maintainer documentation checks and Markdown reference library
- local runtime residue from `storage/` such as logs, cache entries, queue files, session files and guard state
- framework governance files

For the exact script boundary, read the project-facing command reference in
[`RELEASE-AND-OPERATIONS.md`](./RELEASE-AND-OPERATIONS.md#project-facing-command-reference).

## What the new project should be

The exported directory should become the actual website or application repository.

That means the normal flow is:

1. Clone or pull the latest `techayoDEV/fnlla`.
2. Run `php fnlla make:project`.
3. Open the exported directory.
4. Initialize a new Git repository there.
5. Build the real project in that new directory.

Think about the exported project as three layers:

- FNLLA underneath: bootstrap, framework source, public runtime, default config,
  CLI and update machinery.
- Your product above it: routes, controllers, views, migrations, jobs, business
  roles, forms, tests and user-facing content.
- The environment around it: `.env`, secrets, uploads, logs, queue state,
  backups and hosting configuration.

Framework updates are allowed to refresh the lower layer. They should not
silently replace product work or environment data.

## Definition Of Ready

Both profiles keep the default Composer install small. After `composer install`,
run `php scripts/test.php`, `php scripts/lint.php` and `composer analyse` in the
export. The bundled analysis script uses PHPStan or Psalm when the project adds
one, and otherwise runs a dependency-light baseline. Projects that need full
PHPUnit/PHPStan can add those tools deliberately as dev dependencies. Production
uses `composer install --no-dev --optimize-autoloader` with the application's
committed lock file. See [Framework development](ARCHITECTURE-ROADMAP.md).

A freshly exported project is ready for commercial product work when:

- `php fnlla project:claim` has written the real product identity;
- `.env` exists locally and contains environment-specific values;
- `php fnlla project:acceptance --json` passes;
- `php scripts/test.php` and `php scripts/lint.php` pass;
- the team understands which files are framework-managed through
  `.fnlla/framework-lock.json`;
- the first product backlog has identified routes, tables, roles, forms,
  uploads and external integrations.

Do not start by deleting the framework's health, maintenance or runtime
surfaces. Keep them working while product code grows around them.

## Claim the project identity

After export, the directory is no longer just a generic base. Before real
delivery work starts, claim the project with the built-in command:

```bash
php fnlla project:claim \
  --product "Acme Service Portal" \
  --id ACME_SERVICE_PORTAL \
  --owner "Acme LTD" \
  --developer "Delivery Studio LTD" \
  --maintainer "Delivery Studio LTD"
```

The command writes project-owned metadata into `MANIFEST.json`, `.env.example`,
`README.md` and `config/app.php`. `.env.full.example` remains the full
operator reference for optional advanced keys.

Claimed metadata records:

- product or application name
- product identifier and slug
- owner, funder, client and system owner where applicable
- developer and implementation provider
- maintenance and update provider
- runtime/framework name, version and creator

The current framework metadata already records the FNLLA runtime and integrated
UI surface. Downstream project metadata should extend that structure with the
real product identity instead of leaving the exported application described only
as a template.

Do not copy client branding, proprietary business logic or project-specific
data back into `techayoDEV/fnlla`. Generic improvements discovered in a
downstream project should be reimplemented in FNLLA with framework-owned naming,
tests and documentation.

## Example

If your maintained framework lives here:

```text
/workspace/fnlla
```

Then a good new project export might be:

```bash
cd /workspace/fnlla
php fnlla make:project ../acme-service-portal "Acme Service Portal"
```

That creates:

```text
/workspace/acme-service-portal
```

and leaves the framework repository untouched.

## What to do right after export

Inside the new project directory:

1. Run `php fnlla project:claim --product "..." --owner "..." --developer "..."`.
2. Copy `.env.example` to `.env`; use `.env.full.example` only as a reference
   for advanced keys.
3. Set `APP_URL`.
4. Leave `ASSET_URL` empty unless the project serves CSS, JavaScript and images from a separate asset domain or CDN.
5. Set MySQL credentials.
6. Review `config/app.php`.
7. Run `php fnlla project:acceptance --json` before adding product-specific code.
8. Open `/`, `/about`, `/contact`, `/terms` and `/privacy` and treat them as the real project-base pages you will reshape.
9. Open `/contact`, submit a test enquiry with `MAIL_MAILER=log`, then decide
   whether the project needs persistence, queue delivery or a CRM/webhook
   adapter.
10. Replace the demo routes and pages with the real application flow.
11. Run:

```bash
php fnlla project:acceptance --json
php fnlla fnlla-runtime:validate
php fnlla framework:update --check
php fnlla framework:update --dry-run
php scripts/test.php
php scripts/lint.php
php scripts/validate-version-manifest.php
```

On Windows, optional command wrappers live under `scripts/windows/` to keep the
project root focused. `fnlla.cmd` remains in root as the normal CLI launcher.
The full root-file policy is maintained in
`docs/RELEASE-AND-OPERATIONS.md#root-file-policy`.

12. Use `/maintenance/framework-update` when you want to compare the project against the latest published FNLLA release, write a dry-run report and apply safe framework-managed changes from the official `techayoDEV/fnlla` GitHub channel.

13. When client preview should stay private, either open `/maintenance` locally
    and use the built-in setup form, or set `MAINTENANCE_MODE_ENABLED=true`,
    `CLIENT_PREVIEW_ENABLED=true` and
    `MAINTENANCE_ACCESS_PASSWORD=<your-password>` in `.env`.

14. Start the local server:

```bash
php -S 127.0.0.1:8080 -t public public/router.php
```

15. Open `http://127.0.0.1:8080` in your browser.

For Apache environments, use `public/` as the document root.
The exported project already contains `public/.htaccess`.

The exported `.env.example` is intentionally short and starts in
local-development mode so sessions and flash flows work over plain HTTP on
`127.0.0.1`. `.env.full.example` documents every supported environment key.
Before production deployment, copy only required advanced values into the real
environment, switch back to production-safe values and enable HTTPS.

## First Browser Visit: Complete

An unconfigured Complete (`--profile=full`) export contains no default developer
account or shared password. Copy `.env.example` to `.env`, start the local PHP
server with its document root set to `public`, and open `/` on localhost.
Project Setup collects the project identity, developer email, password and
confirmation. After saving, that account opens the Developer Panel. Later visits
to `/developer` use the email and password chosen during setup.

Before an account exists, a direct local visit to `/developer` (or the configured
`DEVELOPER_ACCESS_PATH`) redirects to the permitted setup screen. If maintenance
credentials already exist, setup is on `/maintenance`. Setup is restricted by
`DEVELOPER_ACCESS_SETUP_UI_ENABLED`, `DEVELOPER_ACCESS_SETUP_UI_LOCAL_ONLY`, the
request IP and environment-file writability. Do not disable local-only protection
on an exposed server to work around an access problem; configure locally first
or use a secured operator deployment procedure.

If you see sign-in immediately, verify that you are using the correct project
directory, port and entry path. Check whether `DEVELOPER_ACCESS_USERS` is populated
in `.env` or supplied externally, without posting its contents. A QA preview with
a preconfigured account is not a fresh starter. Never delete existing accounts to
force setup; use [account recovery](DEVELOPER-PANEL.md#developer-account-recovery)
instead.

Core (`--profile=plain`) intentionally has no Developer Panel or browser setup.

## First Product Commit

The first commit in a downstream product repository should normally contain:

- the exported FNLLA base;
- claimed product metadata;
- local `.env.example` defaults without secrets;
- a passing `project:acceptance` report in CI output, not committed as a file;
- the first product-specific route/controller/view or migration;
- no generated cache, session, queue, log, backup or upload artefacts.

That keeps the product repository clean while still proving the framework base
was not damaged before real implementation started.

## Which files you normally edit first

For a new project, the first files are usually:

- `config/app.php`
- `routes/web.php`
- `src/Controllers/PageController.php`
- `views/pages/`
- `public/assets/app.css`
- `database/migrations/`

## Simple Starter Surface

A practical simple starter should normally include:

- public pages: home, about, contact, terms and privacy;
- reusable page-title hero component for non-home pages;
- working contact form with validation, CSRF, old input and log mailer;
- cookie consent and editable privacy/terms snippets;
- health endpoint and maintenance/client-preview access;
- Developer Panel for project identity, preview, readiness, analytics,
  notifications, integrations and framework updates;
- basic auth/roles only when the first product workflow needs protected users;
- first migration and seed only when the project has real data to own;
- tests covering public routes, form validation, mail delivery and protected
  flows once they exist.

## Should there still be a separate template directory inside fnlla

No separate duplicated `template/` copy is recommended as the primary workflow.

The reason is simple:

- a duplicated template directory would copy large parts of the framework source
- that duplicate would drift over time
- maintainers would have to update the framework and the template copy separately

The export command is safer because it always uses the current maintained repository state as the source of truth.

It is also cleaner because it now exports the downstream application surface rather than copying the whole maintainer workspace.

## When cloning the repository directly is still acceptable

Cloning `techayoDEV/fnlla` directly is still fine when the goal is:

- framework maintenance
- hardening the project export base itself
- updating shared docs
- improving the common routing, auth, migration or UI contract

That is framework work, not downstream project work.

## Final rule

### Compact Export Contract

`resources/project-templates/v1/export-files.json` is the explicit starter file
list. New source files are not exported until a maintainer adds them to that
list. The exporter validates source containment before copying and creates empty
storage and upload directories. Ignored runtime files are never an export input.

Starters use `.fnlla/ui-distribution` set to `sprite`. The local sprite contains
all maintained icon names, including aliases, and travels with LICENSE/NOTICE.
Individual SVGs and the unused FNLLA PNG logo remain in the maintainer checkout.
The runtime synchronizer stages and validates the compact package before replacing
the installed runtime. Set the profile to `full` for individual SVG URLs; older
projects without a profile retain their full runtime distribution.

After updating source icons, rebuild the maintained sprite with PowerShell:

```powershell
. ./scripts/copy-fnlla-runtime.ps1
Write-FnllaIconSprite -IconsPath ./public/vendor/fnlla-runtime/assets/icons -OutputPath ./public/vendor/fnlla-runtime/assets/icons/sprite.svg
```

Project `release:prepare` validates tests, lint, runtime, versions, acceptance and
configuration. Maintainer release metadata, docs and public API snapshot checks
stay in the framework release gate. Application release preparation does not
purge live queues, sessions or logs; checksums exclude storage, uploads and local
environment variants. Run the real export regression tests and
`scripts/test-runtime-distribution.ps1` when changing either boundary.

Treat `techayoDEV/fnlla` as:

- the maintained framework repository
- the official project export source

Treat each exported directory as:

- one real website or one real web application project

That separation is the cleanest and most scalable way to work with FNLLA.
