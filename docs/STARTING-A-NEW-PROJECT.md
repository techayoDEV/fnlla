# Starting a New Project with FNLLA PHP

## Short answer

Do not treat the `fnlla/php` repository itself as the normal place where a new client website or new web application should be built.

The recommended workflow is:

1. Keep `fnlla/php` as the framework source and starter-export base.
2. Export a new working project into its own directory.
3. Give that new directory its own project name and its own Git repository.
4. Build the actual website or application there.

## Why not just clone fnlla-php and build directly inside it

If you clone `fnlla/php` and start editing it directly for every new website, you mix together two different concerns:

- framework maintenance
- one specific downstream project

That quickly becomes messy because:

- framework repo metadata stays mixed with project metadata
- project-specific pages and migrations start polluting the framework source
- release history becomes harder to separate
- every new project starts from a manual copy decision instead of a repeatable process

## Official recommended workflow

Use the built-in starter export command from the maintained `fnlla/php` repository:

```bash
php fnlla make:project ..\my-new-project "My New Project"
```

That command exports a clean working starter into a new directory outside the framework repository.

## What the export gives you

The exported project already includes:

- the FNLLA PHP runtime
- the vendored FNLLA UI runtime
- routes, controllers and views
- MySQL config and migration support
- auth, sessions, cookies and CSRF foundations
- lint, test and FNLLA UI validation scripts
- a project README that explains the next steps

It also avoids copying framework-maintainer-only repository metadata such as:

- `.git`
- `.github`
- framework release metadata files
- framework governance files

## What the new project should be

The exported directory should become the actual website or application repository.

That means the normal flow is:

1. Clone or pull the latest `fnlla/php`.
2. Run `php fnlla make:project`.
3. Open the exported directory.
4. Initialize a new Git repository there.
5. Build the real project in that new directory.

## Example

If your maintained framework lives here:

```text
C:\workspace\fnlla-php
```

Then a good new project export might be:

```bash
cd C:\workspace\fnlla-php
php fnlla make:project ..\acme-service-portal "Acme Service Portal"
```

That creates:

```text
C:\workspace\acme-service-portal
```

and leaves the framework repository untouched.

## What to do right after export

Inside the new project directory:

1. Copy `.env.example` to `.env`.
2. Set `APP_URL`.
3. Set MySQL credentials.
4. Review `config/app.php`.
5. Replace the demo routes and pages with the real application flow.
6. Run:

```bash
php fnlla fnlla-ui:validate
php scripts/test.php
php scripts/lint.php
php scripts/validate-version-manifest.php
```

7. Start the local server:

```bash
php -S 127.0.0.1:8080 -t public public/router.php
```

8. Open `http://127.0.0.1:8080` in your browser.

For Apache environments, use `public/` as the document root.
The exported project already contains `public/.htaccess`.

## Which files you normally edit first

For a new project, the first files are usually:

- `config/app.php`
- `routes/web.php`
- `src/Controllers/`
- `views/pages/`
- `public/assets/app.css`
- `database/migrations/`

## Should there still be a separate starter directory inside fnlla-php

No separate duplicated `starter/` copy is recommended as the primary workflow.

The reason is simple:

- a duplicated starter directory would copy large parts of the framework source
- that duplicate would drift over time
- maintainers would have to update the framework and the starter copy separately

The export command is safer because it always uses the current maintained repository state as the source of truth.

## When cloning the repository directly is still acceptable

Cloning `fnlla/php` directly is still fine when the goal is:

- framework maintenance
- hardening the starter base itself
- updating shared docs
- improving the common routing, auth, migration or UI contract

That is framework work, not downstream project work.

## Final rule

Treat `fnlla/php` as:

- the maintained framework repository
- the official starter export source

Treat each exported directory as:

- one real website or one real web application project

That separation is the cleanest and most scalable way to work with FNLLA PHP.
