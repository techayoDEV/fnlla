# Building Websites and Web Apps with FNLLA PHP

## What this guide is for

This guide explains how to build new server-rendered websites and web applications on top of `fnlla-php`.

It is written for the official stack:

- `fnlla-php` as the application framework
- the vendored runtime under `public/vendor/fnlla-web/` as the only supported UI layer
- PHP 8.3
- MySQL

If you follow the patterns below, your project will stay aligned with the current framework contract and with the intended TechAyo LTD delivery style.

Before using this guide for a real delivery, read [`STARTING-A-NEW-PROJECT.md`](./STARTING-A-NEW-PROJECT.md). That document explains the official starter-export workflow and when not to build directly inside the maintained `techayoDEV/fnlla-php` repository.

When you need the exact responsibilities of the project scripts and validation commands, read [`PROJECT-SCRIPTS-REFERENCE.md`](./PROJECT-SCRIPTS-REFERENCE.md).

## The working model

FNLLA PHP is built around a small and explicit request flow:

1. The request enters through `public/index.php`.
2. Bootstrapping happens in `bootstrap/app.php` and `bootstrap/common.php`.
3. The router reads definitions from `routes/web.php`.
4. A controller or closure handles the request.
5. A response is returned as HTML, JSON, redirect or another supported response type.
6. HTML views render through `views/layouts/app.php` and `views/pages/`.

Keep that flow in mind when building anything new. The framework is intentionally small enough that most delivery work should fit into that lifecycle without extra abstraction layers.

## Official UI rule

Every new website or application built on `fnlla-php` must use the built-in vendored UI runtime shipped with the framework.

That means:

- keep the shared shell structure from `views/layouts/app.php`
- keep using `public/vendor/fnlla-web/` as the source of runtime UI assets
- keep `section`, `container`, `card`, grid and the related runtime layout conventions
- do not introduce Tailwind, Bootstrap, Bulma, Foundation, UIkit, Materialize or Semantic UI

The framework already enforces parts of that contract during bootstrap and validation.

## Recommended build sequence

When starting a new delivery on top of FNLLA PHP, the safest sequence is:

1. Set local environment values in `.env`.
2. Confirm the vendored runtime is synced and valid.
3. Define the pages and flows you need.
4. Add routes.
5. Add or extend controllers.
6. Add views.
7. Add forms, validation and flash feedback where needed.
8. Add database tables and migrations when persistence is required.
9. Add auth or authorization boundaries when a page is protected.
10. Run tests, lint and runtime validation before shipping.

## Local setup for a new project

Copy the environment template:

```bash
copy .env.example .env
```

Then fill the important values:

- `APP_URL`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `CONTACT_NOTIFICATION_EMAIL`

The template defaults are intentionally local-development friendly:

- `APP_ENV=development`
- `APP_DEBUG=true`
- `SESSION_SECURE=false`

That keeps sessions, flashes and CSRF-protected forms working over plain `http://127.0.0.1` during local setup.
Before production deployment, switch those values back to production-safe settings and serve the app over HTTPS.
If the deployment sits behind a reverse proxy, set `TRUSTED_PROXIES` so forwarded client IP and HTTPS headers are only accepted from explicitly trusted proxy addresses.

Recommended first checks:

```bash
php fnlla fnlla-web:sync
php fnlla fnlla-web:validate
php scripts/test.php
php scripts/lint.php
php scripts/validate-version-manifest.php
```

Those commands are intentionally the downstream-project subset. The framework docs builder stays in the upstream `techayoDEV/fnlla-php` repository and is not part of the exported starter.

Start the local server:

```bash
php -S 127.0.0.1:8080 -t public public/router.php
```

Then open `http://127.0.0.1:8080`.

If you run FNLLA PHP under Apache, configure the virtual host so the document root is `public/`.
The framework already ships with `public/.htaccess` for route rewriting.

## How to add a new page

The normal page workflow is:

1. Add a route in `routes/web.php`.
2. Add a controller method in `src/Controllers/`.
3. Add a page template in `views/pages/`.
4. Return the page through the shared layout.

Example route:

```php
$router->get("/services", [HomeController::class, "services"])->name("services");
```

Example controller method:

```php
public function services(Request $request): Response
{
    return $this->view("pages/services", [
        "pageTitle" => "Services",
        "services" => [
            [
                "title" => "Advisory",
                "text" => "Delivery guidance for teams building with FNLLA PHP.",
            ],
            [
                "title" => "Implementation",
                "text" => "Hands-on website and application delivery.",
            ],
        ],
    ]);
}
```

Example page template:

```php
<section class="section">
  <div class="container">
    <div class="stack gap-lg">
      <header class="stack gap-sm">
        <h1>Services</h1>
        <p class="content-text">Build the page with the shipped runtime primitives and plain PHP data output.</p>
      </header>

      <div class="grid grid-2 gap-md">
        <?php foreach ($services as $service): ?>
        <article class="card">
          <h2 class="card-title"><?= h($service["title"]) ?></h2>
          <p class="card-text"><?= h($service["text"]) ?></p>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
```

## How to structure views

Use these rules for page templates:

- keep page files under `views/pages/`
- let `views/layouts/app.php` own the global shell, header, footer and shared assets
- keep page templates focused on page content, not full HTML documents
- use helper functions like `h()`, `route()`, `asset()`, `auth()` and `csrf_field()` where appropriate
- keep markup aligned with the shipped runtime classes and layout patterns

Good default page structure:

```html
<section class="section">
  <div class="container">
    <div class="grid grid-2 gap-md">...</div>
  </div>
</section>
```

Alternate section pattern:

```html
<section class="section section-alt">
  <div class="container">
    <div class="card">...</div>
  </div>
</section>
```

## When to use controllers and when to use closures

Use closures only for very small runtime endpoints such as:

- health checks
- compact JSON diagnostics
- tiny one-off responses

Use controllers for:

- any page that has more than trivial view data
- forms
- auth flows
- persistence
- events, mail or side effects
- anything you may want to test or extend later

If a route looks like real product behavior, move it into a controller.

## Handling forms correctly

For forms:

- render them in a page view
- submit to a named POST route
- use `csrf_field()`
- validate incoming data in the controller
- flash errors and previous input back into the next request
- redirect after success

The existing contact flow is the reference pattern:

- GET page route for the form
- POST submit route with `csrf` and `throttle`
- controller validation
- flash status message
- redirect back to the page anchor

That pattern is already visible in:

- `routes/web.php`
- `src/Controllers/HomeController.php`
- `views/pages/contact.php`

## Validation pattern

Validate input inside controllers before doing work with it.

Typical pattern:

```php
$payload = [
    "email" => trim((string) $request->input("email", "")),
];

$this->validate($payload, [
    "email" => ["required", "email", "max:160"],
]);
```

Use validation for:

- forms
- query payloads
- route-driven actions
- administrative operations

Do not trust raw request input just because the UI already constrains it.

## Database workflow

Use MySQL for all official projects.

When you need persistence:

1. Create a migration.
2. Run the migration.
3. Add query logic or model-like data access through the query builder.
4. Add seeding only when it genuinely helps local setup or demo data.

Important commands:

```bash
php fnlla migrate
php fnlla migrate:rollback
php fnlla migrate:status
php fnlla db:seed
```

Where the database work lives:

- migrations in `database/migrations/`
- seeders in `database/seeders/`
- factories in `database/factories/`
- shared DB access in `src/Database/`

## Query builder usage

FNLLA PHP ships with a lean query builder. Use it when you need a direct and readable database interaction layer.

Typical use cases:

- loading dashboard data
- listing records
- resolving a user record for auth
- writing admin tools

Keep query logic out of views. Put it in controllers or in a dedicated service class resolved through the container.

## Authentication and protected areas

For protected pages:

- add the `auth` middleware
- add authorization where permission checks are needed
- keep login/logout flows explicit

Examples already exist:

- `/login`
- `/dashboard`
- `/admin`
- `/logout`

Typical protected route:

```php
$router->get("/dashboard", [AuthController::class, "dashboard"])
    ->middleware("auth")
    ->authorize("view-dashboard")
    ->name("dashboard");
```

Use authentication for identity.

Use authorization for capability checks such as:

- admin area access
- management tools
- project-specific operations

## Middleware usage

Middleware should handle cross-cutting HTTP concerns, not page-specific business logic.

Use middleware for:

- authentication
- authorization
- CSRF
- CORS
- throttling

If a rule belongs to every request in a route group or a protected area, middleware is the right tool.

## Route organization

Keep routes readable.

Recommended rules:

- define HTML routes near the top of `routes/web.php`
- group API routes together
- use route names consistently
- keep URL paths stable and descriptive
- prefer one controller action per route

Name routes early. It makes links, redirects and navigation safer:

```php
->name("projects.show")
```

Then use:

```php
route("projects.show", ["project" => $slug])
```

## Building JSON endpoints

FNLLA PHP is server-rendered first, but it can also expose JSON endpoints for app surfaces.

Use JSON responses for:

- health endpoints
- authenticated panel data
- partial client-side enhancements
- internal application surfaces

Example:

```php
return Response::json([
    "status" => "ok",
    "timestamp" => gmdate(DATE_ATOM),
]);
```

For structured resource output, use `JsonResource` when the payload deserves a stable shape.

## Using the container well

The container is there to keep controllers and services clean, not to hide everything behind indirection.

Good uses:

- injecting a service into a controller constructor
- centralizing mail, persistence or domain operations
- keeping repeated logic out of large controllers

Avoid unnecessary abstraction when a simple controller method is enough.

## A practical app structure

For a real project, a healthy shape often looks like this:

- `routes/web.php` for route registration
- `src/Controllers/` for HTTP actions
- `src/Services/` for reusable business logic if needed
- `views/pages/` for page templates
- `database/migrations/` for schema
- `database/seeders/` for local/demo data

Do not move too fast into heavy architecture. Start with readable controllers and only extract services when patterns repeat.

## How to add an admin or dashboard area

Recommended pattern:

1. Protect the route with `auth`.
2. Add an authorization gate for the role or capability.
3. Keep admin views under `views/pages/`.
4. Return dashboard-specific data from a controller.
5. Keep dangerous write actions behind POST routes with CSRF.

If the admin area grows, group routes by prefix and name:

```php
$router->group([
    "prefix" => "admin",
    "as" => "admin.",
    "middleware" => "auth",
], static function ($router): void {
    $router->get("/users", [AdminController::class, "users"])
        ->authorize("manage-admin-area")
        ->name("users.index");
});
```

## Styling and page composition rules

When building pages:

- compose layouts with the shipped runtime primitives first
- avoid one-off CSS unless the page truly needs it
- keep custom CSS in `public/assets/app.css`
- prefer reusable structural patterns over ad hoc markup

A good rule is:

- use the shipped runtime for layout, spacing, cards, grid, buttons, alerts and shells
- use project CSS only for branding or delivery-specific refinements

## What to avoid

Avoid these common mistakes:

- putting SQL or heavy logic inside views
- adding a second UI framework
- writing raw HTML documents inside page partials
- skipping CSRF on form POST routes
- using closures for large business flows
- mixing downstream project hacks into shared framework behavior without intent
- bypassing named routes and hardcoding links everywhere

## Suggested workflow for a new client project

For a brand-new website or application delivery, use this checklist:

1. Define the page map and protected areas.
2. Define the required data entities.
3. Add migrations for those entities.
4. Add routes for public pages, auth flows and admin flows.
5. Build controllers page by page.
6. Build views using the shipped runtime structure.
7. Add validation and flash feedback to every form.
8. Add auth and authorization guards.
9. Add seed data if it improves local setup.
10. Run validation and hardening checks before every release candidate.

## Pre-release checks

Before shipping a new project built on FNLLA PHP, run:

```bash
php scripts/test.php
php scripts/lint.php
php scripts/validate-fnlla-web.php
php fnlla route:list
```

When the release touches schema or seed data, also run:

```bash
php fnlla migrate:status
```

## Final recommendation

Build with the grain of the framework.

FNLLA PHP is strongest when you keep:

- routing explicit
- controllers readable
- views plain
- validation early
- MySQL usage disciplined
- UI work inside the built-in runtime contract
- release hygiene consistent

If a new website or application follows those rules, `fnlla-php` becomes a fast and stable base instead of a pile of custom exceptions.
