<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP CONSOLE SOURCE
File: src\Console\Commands\MakeProjectCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements the maintained CLI surface and scheduler-oriented console behavior.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Support\FrameworkLock;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class MakeProjectCommand extends Command
{
    public function name(): string
    {
        return "make:project";
    }

    public function description(): string
    {
        return "Export a clean FNLLA PHP starter into a new project directory.";
    }

    public function handle(array $arguments): int
    {
        $targetArgument = trim((string) ($arguments[0] ?? ""));
        $appNameArgument = trim(implode(" ", array_slice($arguments, 1)));

        if ($targetArgument === "") {
            $this->error("Usage: make:project <target-path> [App Name]");

            return 1;
        }

        $sourceRoot = $this->normalizePath(base_path());
        $targetPath = $this->resolveTargetPath($targetArgument);

        if ($this->pathsEqual($sourceRoot, $targetPath)) {
            $this->error("Target path cannot be the FNLLA PHP source repository itself.");

            return 1;
        }

        if ($this->isChildPath($targetPath, $sourceRoot)) {
            $this->error("Target path must be outside the FNLLA PHP source repository to avoid recursive copies.");

            return 1;
        }

        $appName = $appNameArgument !== "" ? $appNameArgument : $this->guessAppName($targetPath);
        $packageSlug = $this->slugify($appName);

        if ($packageSlug === "") {
            $this->error("Unable to derive a valid project slug from the provided name or path.");

            return 1;
        }

        try {
            $this->prepareTargetDirectory($targetPath);
            $this->copyStarterTree($sourceRoot, $targetPath);
            $this->customizeExport($targetPath, $appName, $packageSlug);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return 1;
        }

        $this->line("Exported FNLLA PHP starter to: " . $targetPath);
        $this->line("Application name: " . $appName);
        $this->line("");
        $this->line("Next steps:");
        $this->line("1. Open the new project directory.");
        $this->line("2. Copy .env.example to .env and set APP_URL plus MySQL credentials.");
        $this->line("3. Review routes/web.php, src/Controllers/ and views/pages/ and replace the demo surface with your project pages.");
        $this->line("4. Run php fnlla fnlla-web:validate, php scripts/test.php, php scripts/lint.php and php scripts/validate-version-manifest.php.");
        $this->line("5. Initialize a separate Git repository for the new website or application.");

        return 0;
    }

    private function prepareTargetDirectory(string $targetPath): void
    {
        if (is_dir($targetPath)) {
            $entries = scandir($targetPath);

            if ($entries === false) {
                throw new RuntimeException("Unable to inspect target directory: " . $targetPath);
            }

            $visibleEntries = array_values(array_diff($entries, [".", ".."]));

            if ($visibleEntries !== []) {
                throw new RuntimeException("Target directory must be empty: " . $targetPath);
            }

            return;
        }

        if (file_exists($targetPath) && !is_dir($targetPath)) {
            throw new RuntimeException("Target path already exists and is not a directory: " . $targetPath);
        }

        if (!mkdir($targetPath, 0777, true) && !is_dir($targetPath)) {
            throw new RuntimeException("Unable to create target directory: " . $targetPath);
        }
    }

    private function copyStarterTree(string $sourceRoot, string $targetRoot): void
    {
        $iterator = new FilesystemIterator($sourceRoot, FilesystemIterator::SKIP_DOTS);

        foreach ($iterator as $fileInfo) {
            $name = $fileInfo->getFilename();

            if (!$this->shouldExportRootEntry($name)) {
                continue;
            }

            $sourcePath = $fileInfo->getPathname();
            $targetPath = $targetRoot . DIRECTORY_SEPARATOR . $name;

            $this->copyPath($sourcePath, $targetPath);
        }
    }

    private function shouldExportRootEntry(string $name): bool
    {
        return in_array($name, [
            ".editorconfig",
            ".env.example",
            ".gitattributes",
            ".gitignore",
            "LICENSE.md",
            "MANIFEST.json",
            "SUPPORT.md",
            "TRADEMARKS.md",
            "VERSION",
            "bootstrap",
            "composer.json",
            "config",
            "database",
            "fnlla",
            "fnlla.cmd",
            "lang",
            "public",
            "routes",
            "scripts",
            "src",
            "storage",
            "tests",
            "update-fnlla-web.cmd",
            "views",
        ], true);
    }

    private function copyPath(string $sourcePath, string $targetPath): void
    {
        if (is_dir($sourcePath)) {
            if (!is_dir($targetPath) && !mkdir($targetPath, 0777, true) && !is_dir($targetPath)) {
                throw new RuntimeException("Unable to create directory during export: " . $targetPath);
            }

            $iterator = new FilesystemIterator($sourcePath, FilesystemIterator::SKIP_DOTS);

            foreach ($iterator as $fileInfo) {
                $name = $fileInfo->getFilename();
                $childSource = $fileInfo->getPathname();
                $childTarget = $targetPath . DIRECTORY_SEPARATOR . $name;
                $relativeSource = $this->normalizeSeparators(substr($childSource, strlen(base_path()) + 1));

                if ($this->shouldSkipRelativeEntry($relativeSource)) {
                    continue;
                }

                $this->copyPath($childSource, $childTarget);
            }

            return;
        }

        if (!copy($sourcePath, $targetPath)) {
            throw new RuntimeException("Unable to copy file during export: " . $sourcePath);
        }
    }

    private function shouldSkipRelativeEntry(string $relativePath): bool
    {
        if ($relativePath === "docs" || str_starts_with($relativePath, "docs/")) {
            return true;
        }

        if ($this->isRuntimeStatePath($relativePath)) {
            return true;
        }

        return in_array($relativePath, [
            "database/factories/UserFactory.php",
            "database/migrations/20260627180000_create_users_table.php",
            "database/migrations/20260627200000_add_role_to_users_table.php",
            "scripts/apply-techayo-metadata.ps1",
            "scripts/build-docs.php",
            "src/Console/Commands/MakeCommandCommand.php",
            "src/Console/Commands/MakeControllerCommand.php",
            "src/Console/Commands/MakeFactoryCommand.php",
            "src/Console/Commands/MakeMiddlewareCommand.php",
            "src/Console/Commands/MakeMigrationCommand.php",
            "src/Console/Commands/MakeProjectCommand.php",
            "src/Console/Commands/MakeSeederCommand.php",
            "src/Controllers/AuthController.php",
            "tests/ApplicationTest.php",
            "tests/AuthTest.php",
            "tests/EnvironmentConfigTest.php",
            "tests/FnllaWebGuardTest.php",
            "tests/FrameworkExtensionsTest.php",
            "tests/MakeProjectCommandTest.php",
            "tests/PageMetaTest.php",
            "tests/RequestTest.php",
            "tests/RouterTest.php",
            "tests/FrameworkUpdateCommandTest.php",
            "tests/ValidationTest.php",
            "test-fnlla-php.cmd",
            "lint-fnlla-php.cmd",
            "views/pages/about.php",
            "views/pages/admin.php",
            "views/pages/dashboard.php",
            "views/pages/login.php",
            "views/pages/platform.php",
        ], true);
    }

    private function isRuntimeStatePath(string $relativePath): bool
    {
        if ($relativePath === "storage/framework/fnlla-web-guard.json") {
            return true;
        }

        foreach ([
            "storage/database/",
            "storage/logs/",
            "storage/framework/cache/",
            "storage/framework/queue/",
            "storage/framework/sessions/",
        ] as $prefix) {
            if (str_starts_with($relativePath, $prefix)) {
                return basename($relativePath) !== ".gitignore";
            }
        }

        return false;
    }

    private function customizeExport(string $targetRoot, string $appName, string $packageSlug): void
    {
        $this->sanitizeExportedStorage($targetRoot);
        $this->rewriteAppConfig($targetRoot, $appName);
        $this->rewriteComposerMetadata($targetRoot, $appName, $packageSlug);
        $this->rewriteStarterReadme($targetRoot, $appName);
        $this->rewriteApplicationSurface($targetRoot, $appName);
        $this->rewriteDatabaseSurface($targetRoot);
        $this->rewriteProjectTests($targetRoot);
        $this->rewriteConsoleLaunchers($targetRoot);
        $this->rewriteProjectLaunchers($targetRoot);
        FrameworkLock::write($targetRoot, base_path(), $appName, $packageSlug);
    }

    private function sanitizeExportedStorage(string $targetRoot): void
    {
        $keepFiles = [
            "storage/database/.gitignore" => "*\n!.gitignore\n",
            "storage/framework/cache/.gitignore" => "# Keep the cache directory in the repository while ignoring runtime cache files.\n*\n!.gitignore\n",
            "storage/framework/queue/.gitignore" => "# Keep the queue directory in the repository while ignoring runtime queue files.\n*\n!.gitignore\n",
            "storage/framework/sessions/.gitignore" => "# Keep the sessions directory in the repository while ignoring runtime session files.\n*\n!.gitignore\n",
            "storage/logs/.gitignore" => "*\n!.gitignore\n",
        ];

        foreach ($keepFiles as $relativePath => $contents) {
            $absolutePath = $targetRoot . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $relativePath);
            $directory = dirname($absolutePath);

            if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new RuntimeException("Unable to create storage directory during export: " . $directory);
            }

            file_put_contents($absolutePath, $contents);
        }
    }

    private function rewriteAppConfig(string $targetRoot, string $appName): void
    {
        $path = $targetRoot . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "app.php";
        $contents = (string) file_get_contents($path);
        $replacement = "'name' => " . var_export($appName, true) . ",";
        $updated = preg_replace("/\"name\" => \"FNLLA PHP\",/", $replacement, $contents, 1);

        if (!is_string($updated)) {
            throw new RuntimeException("Unable to update config/app.php for exported project.");
        }

        file_put_contents($path, $updated);
    }

    private function rewriteComposerMetadata(string $targetRoot, string $appName, string $packageSlug): void
    {
        $path = $targetRoot . DIRECTORY_SEPARATOR . "composer.json";
        $decoded = json_decode((string) file_get_contents($path), true);

        if (!is_array($decoded)) {
            throw new RuntimeException("Unable to decode composer.json for exported project.");
        }

        $decoded["name"] = "project/" . $packageSlug;
        $decoded["description"] = $appName . " built on FNLLA PHP and FNLLA Web.";

        file_put_contents(
            $path,
            json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );
    }

    private function rewriteStarterReadme(string $targetRoot, string $appName): void
    {
        $readme = <<<MD
# {$appName}

This repository is a working application export generated from `fnlla/php`.

It is intended to be the beginning of a new server-rendered website or web application built on:

- FNLLA PHP
- FNLLA Web
- PHP 8.3
- MySQL

## What is already included

- the FNLLA PHP application core
- the vendored FNLLA Web runtime under `public/vendor/fnlla-web/`
- machine-readable release metadata in `MANIFEST.json`
- framework update baseline metadata in `.fnlla/framework-lock.json`
- a legacy compatibility lock in `.fnlla/starter-lock.json` for older update flows
- root legal and policy files: `LICENSE.md`, `SUPPORT.md`, `TRADEMARKS.md`
- a lean application surface: home page, contact flow and health endpoint
- sessions, cookies, CSRF, auth foundations and the rest of the core runtime under `src/`
- database directories ready for project-specific migrations and seeders
- local lint, test, version metadata and FNLLA Web validation scripts
- a local-first framework maintenance page at `/maintenance/framework-update`

## How to start working

1. Copy `.env.example` to `.env`.
2. Set `APP_URL` and your MySQL credentials.
3. Run:

```bash
php fnlla fnlla-web:validate
php scripts/test.php
php scripts/lint.php
php scripts/validate-version-manifest.php
```

4. Start the local server:

```bash
php -S 127.0.0.1:8080 -t public public/router.php
```

5. Open `http://127.0.0.1:8080` in your browser.
6. Use `http://127.0.0.1:8080/maintenance/framework-update` when you want a browser-based framework update check or safe apply flow.

The maintenance page is controlled through `FRAMEWORK_UPDATE_UI_ENABLED`, `FRAMEWORK_UPDATE_UI_LOCAL_ONLY`, `FRAMEWORK_UPDATE_UI_APPLY_ENABLED` and `FRAMEWORK_UPDATE_SOURCE_PATH` in `.env`.

For Apache environments, use `public/` as the document root.
The exported project already includes `public/.htaccess`.

The exported `.env.example` starts with local-development defaults so sessions work over plain HTTP on `127.0.0.1`.
Before production deployment, switch the environment back to production-safe values and enable HTTPS.

## What the export intentionally leaves behind

This exported application does not copy the full maintainer workspace from `fnlla/php`.

It intentionally leaves behind:

- framework-only browser docs under `docs/`
- the maintainer docs builder `scripts/build-docs.php`
- repository governance and contribution files such as `.git/`, `.github/`, `CODE_OF_CONDUCT.md` and `SECURITY.md`
- local runtime residue such as logs, cache entries, queue files, session files and FNLLA Web guard state

That keeps the downstream project focused on application delivery rather than framework maintenance.

## First files to replace or review

- `routes/web.php`
- `src/Controllers/HomeController.php`
- `views/pages/`
- `public/assets/app.css`
- `database/migrations/`
- `config/app.php`

## Important note

The exported project still contains a working application surface so the application runs immediately.

That surface is a starting point, not the final product. Replace the placeholder pages, routes and content with the real website or application flow for this project.

Use `LICENSE.md`, `SUPPORT.md` and `TRADEMARKS.md` to understand the upstream FNLLA code license, support boundary and branding rules that came with this application base.

## Useful commands

The application base keeps only the project-facing scripts, smoke tests and commands:

- `php scripts/test.php` runs the project-local smoke test harness kept under `tests/`
- `php scripts/lint.php` runs PHP syntax lint across the maintained project tree
- `php scripts/validate-fnlla-web.php` checks that the exported project still respects the FNLLA Web runtime contract
- `php scripts/validate-version-manifest.php` checks that `VERSION`, `MANIFEST.json` and the vendored FNLLA Web version stay aligned
- `php fnlla framework:update --check --source <path-to-fnlla-php>` checks framework drift against a maintained FNLLA PHP source repository
- `/maintenance/framework-update` provides the same framework-update workflow through a local-first maintenance page with buttons for check and safe apply
- `php fnlla version:sync` regenerates `MANIFEST.json` after an intentional version change
- `php fnlla fnlla-web:sync` or `update-fnlla-web.cmd` refresh the vendored FNLLA Web runtime from GitHub

The export intentionally leaves `make:*`, `make:project` and broader framework-internal test coverage in the upstream `fnlla/php` repository.

The full framework documentation remains in the upstream `fnlla/php` repository.

```bash
php fnlla list
php fnlla fnlla-web:sync
php fnlla fnlla-web:validate
php fnlla framework:update --check --source ..\fnlla-php
php fnlla route:list
php fnlla migrate
php fnlla migrate:rollback
php fnlla migrate:status
php fnlla version:status
php fnlla version:sync
php scripts/test.php
php scripts/lint.php
php scripts/validate-version-manifest.php
```

On Windows, the application export also includes:

```cmd
test-project.cmd
lint-project.cmd
update-fnlla-web.cmd
```
MD;

        file_put_contents($targetRoot . DIRECTORY_SEPARATOR . "README.md", $readme . PHP_EOL);
    }

    private function rewriteApplicationSurface(string $targetRoot, string $appName): void
    {
        $routes = <<<'PHP'
<?php

declare(strict_types=1);

/*
===============================================================================
PROJECT ROUTE DEFINITION
File: routes\web.php
Purpose:
- Registers the application-facing HTTP routes kept in the exported project.
===============================================================================
*/

use Fnlla\Php\Controllers\HomeController;
use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;

$router->get("/", [HomeController::class, "home"])->name("home");
$router->get("/contact", [HomeController::class, "contact"])->name("contact");
$router->post("/contact", [HomeController::class, "sendContact"])->middleware("csrf")->throttle(5, 1)->name("contact.submit");

$router->group([
    "prefix" => "api",
    "as" => "api.",
    "middleware" => "throttle",
], static function ($router): void {
    $router->get("/health", static fn (Request $request): Response => Response::json([
        "name" => config("app.name"),
        "status" => "ok",
        "timestamp" => gmdate(DATE_ATOM),
        "request_path" => $request->path(),
    ]))->name("health");
});
PHP;

        $controller = <<<'PHP'
<?php

declare(strict_types=1);

/*
===============================================================================
PROJECT CONTROLLER
File: src\Controllers\HomeController.php
Purpose:
- Keeps the exported application surface small and project-focused by default.
===============================================================================
*/

namespace Fnlla\Php\Controllers;

use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Validation\ValidationException;

final class HomeController extends Controller
{
    public function home(Request $request): Response
    {
        return $this->view("pages/home", [
            "pageTitle" => "Home",
            "pageTitleHome" => true,
            "foundationCards" => [
                [
                    "title" => "Application-first surface",
                    "text" => "The exported project starts as a working application shell instead of a second maintainer workspace.",
                ],
                [
                    "title" => "Full runtime still included",
                    "text" => "FNLLA Web, the PHP core and the validation scripts stay local so the product remains complete.",
                ],
                [
                    "title" => "Clear next steps",
                    "text" => "Replace routes, copy, forms and persistence with the real project flow without unpicking framework showcase pages first.",
                ],
            ],
            "deliverySteps" => [
                [
                    "number" => "1",
                    "title" => "Shape the app",
                    "text" => "Define the real page map, data model and user journeys for this project.",
                ],
                [
                    "number" => "2",
                    "title" => "Wire the flow",
                    "text" => "Add the routes, controllers, templates and persistence that belong to the delivery itself.",
                ],
                [
                    "number" => "3",
                    "title" => "Validate the build",
                    "text" => "Run FNLLA Web validation, lint, tests and version checks before calling the project ready.",
                ],
            ],
        ]);
    }

    public function contact(Request $request): Response
    {
        return $this->view("pages/contact", [
            "pageTitle" => "Contact",
            "pageTitleSection" => "Project",
            "contactTopics" => [
                "New website",
                "Portal or application",
                "Operations or support",
            ],
        ]);
    }

    public function sendContact(Request $request): Response
    {
        $payload = [
            "name" => trim((string) $request->input("name", "")),
            "company" => trim((string) $request->input("company", "")),
            "email" => trim((string) $request->input("email", "")),
            "topic" => trim((string) $request->input("topic", "")),
            "message" => trim((string) $request->input("message", "")),
        ];

        try {
            $this->validate($payload, [
                "name" => ["required", "string", "min:2", "max:120"],
                "company" => ["nullable", "string", "max:120"],
                "email" => ["required", "email", "max:160"],
                "topic" => ["required", "in:New website,Portal or application,Operations or support"],
                "message" => ["required", "string", "min:12", "max:3000"],
            ]);
        } catch (ValidationException $exception) {
            flash_set("old", $payload);
            flash_set("errors", $exception->errors());
            flash_set("status", [
                "variant" => "warning",
                "title" => "A few fields still need attention",
                "text" => "Review the highlighted inputs and submit the form again.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("contact") . "#contact-form");
        }

        flash_set("status", [
            "variant" => "success",
            "title" => "Request captured",
            "text" => "The application processed the form successfully and flashed the confirmation into the next request.",
            "toast" => true,
        ]);
        mailer()->to((string) env("CONTACT_NOTIFICATION_EMAIL", "team@example.com"))->send(
            "New project contact submission",
            "<p><strong>Name:</strong> " . h($payload["name"]) . "</p><p><strong>Email:</strong> " . h($payload["email"]) . "</p><p><strong>Topic:</strong> " . h($payload["topic"]) . "</p><p><strong>Message:</strong> " . nl2br(h($payload["message"])) . "</p>",
            "Name: {$payload["name"]}\nEmail: {$payload["email"]}\nTopic: {$payload["topic"]}\nMessage: {$payload["message"]}"
        );
        event("contact.form.submitted", [
            "payload" => $payload,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("contact") . "#contact-form");
    }
}
PHP;

        $layout = <<<'PHP'
<?php

declare(strict_types=1);

$pageStatus = flash("status");
$pageMeta = page_meta([
    "site" => (string) config("app.name"),
    "page" => (string) ($pageTitle ?? ""),
    "section" => (string) ($pageTitleSection ?? ""),
    "suffix" => (string) ($pageTitleSuffix ?? ""),
    "home" => (bool) ($pageTitleHome ?? false),
]);
?>
<!DOCTYPE html>
<html
  lang="en"
  data-fnlla-title-site="<?= h($pageMeta["site"]) ?>"
  <?php if ($pageMeta["page"] !== ""): ?>data-fnlla-title-page="<?= h($pageMeta["page"]) ?>"<?php endif; ?>
  <?php if ($pageMeta["section"] !== ""): ?>data-fnlla-title-section="<?= h($pageMeta["section"]) ?>"<?php endif; ?>
  <?php if ($pageMeta["suffix"] !== ""): ?>data-fnlla-title-suffix="<?= h($pageMeta["suffix"]) ?>"<?php endif; ?>
  <?php if ($pageMeta["home"] === true): ?>data-fnlla-title-home="true"<?php endif; ?>
>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#1A4137">
  <title><?= h($pageMeta["title"]) ?></title>
  <link rel="stylesheet" href="<?= h(asset("vendor/fnlla-web/assets/css/fnlla-web.css")) ?>">
  <link rel="stylesheet" href="<?= h(asset("assets/app.css")) ?>">
</head>
<body data-fnlla-theme="default">
  <div class="wrapper">
    <header class="site-header-shell">
      <div class="container py-3">
        <nav class="navbar" aria-label="Primary navigation">
          <a class="navbar-brand" href="<?= h(route("home")) ?>">
            <span class="site-brand-text">
              <span class="site-brand-mark">PR</span>
              <?= h(config("app.name")) ?>
            </span>
          </a>
          <button class="btn btn-outline btn-sm navbar-toggle" type="button" data-fnlla-nav-toggle aria-controls="primary-navigation-panel" aria-expanded="false" aria-label="Toggle navigation menu">Menu</button>
          <div class="navbar-panel" id="primary-navigation-panel">
            <ul class="navbar-menu">
              <li><a href="<?= h(route("home")) ?>" <?= is_current_path("/") ? 'aria-current="page"' : "" ?>>Home</a></li>
              <li><a href="<?= h(route("contact")) ?>" <?= is_current_path("/contact") ? 'aria-current="page"' : "" ?>>Contact</a></li>
              <li><a href="<?= h(route("maintenance.framework_update")) ?>" <?= is_current_path("/maintenance/framework-update") ? 'aria-current="page"' : "" ?>>Framework updates</a></li>
              <li><a href="<?= h(route("api.health")) ?>">Health</a></li>
            </ul>
            <div class="navbar-actions">
              <a class="btn btn-primary btn-sm" href="<?= h(route("contact")) ?>">Start the project</a>
            </div>
          </div>
        </nav>
      </div>
    </header>

    <?php if (is_array($pageStatus) && isset($pageStatus["title"], $pageStatus["text"])): ?>
    <section class="section pt-1 pb-0 site-status-anchor" id="page-status">
      <div class="container">
        <div class="alert alert-<?= h((string) ($pageStatus["variant"] ?? "info")) ?>" role="<?= (($pageStatus["variant"] ?? "") === "danger" || ($pageStatus["variant"] ?? "") === "warning") ? "alert" : "status" ?>">
          <h2 class="alert-title"><?= h((string) $pageStatus["title"]) ?></h2>
          <p class="alert-text"><?= h((string) $pageStatus["text"]) ?></p>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <main class="site-main">
      <?= $content ?>
    </main>

    <footer class="section site-footer-shell">
      <div class="container">
        <div class="footer p-4 radius-lg" aria-label="Project footer">
          <div class="footer-top">
            <div class="footer-lead">
              <p class="help-text mb-1">Application base</p>
              <h2 class="footer-heading">A lean application surface on top of the full FNLLA PHP and FNLLA Web product runtime.</h2>
              <p class="footer-note">The exported project keeps the runtime complete, but starts with a smaller app shell so delivery work can begin immediately.</p>
            </div>
            <div class="footer-pillars">
              <article class="footer-pillar">
                <span class="badge">Local runtime</span>
                <p class="footer-note">FNLLA Web assets ship inside the project with no CDN dependency.</p>
              </article>
              <article class="footer-pillar">
                <span class="badge">Real request flow</span>
                <p class="footer-note">Routes, controllers, views and form feedback stay explicit and easy to trace.</p>
              </article>
              <article class="footer-pillar">
                <span class="badge">Release hygiene</span>
                <p class="footer-note">Validation, lint and version checks remain first-party project commands.</p>
              </article>
            </div>
          </div>

          <div class="footer-body">
            <div class="footer-grid">
              <div class="footer-brand-block">
                <h3 class="footer-heading"><?= h(config("app.name")) ?></h3>
                <p class="footer-note">Replace the placeholder copy, routes and data model with the real delivery flow for this project.</p>
                <div class="footer-status">
                  <span class="badge">FNLLA Web</span>
                  <span class="badge">PHP 8.3</span>
                  <span class="badge">Project-first</span>
                </div>
              </div>

              <div class="footer-link-group">
                <h3 class="footer-heading">App routes</h3>
                <div class="footer-links">
                  <a href="<?= h(route("home")) ?>">Home</a>
                  <a href="<?= h(route("contact")) ?>">Contact</a>
                  <a href="<?= h(route("maintenance.framework_update")) ?>">Framework updates</a>
                  <a href="<?= h(route("api.health")) ?>">Health</a>
                </div>
              </div>

              <div class="footer-link-group">
                <h3 class="footer-heading">Project checks</h3>
                <div class="grid gap-2">
                  <p class="footer-note mb-0"><code>php scripts/test.php</code></p>
                  <p class="footer-note mb-0"><code>php scripts/lint.php</code></p>
                  <p class="footer-note mb-0"><code>php scripts/validate-fnlla-web.php</code></p>
                  <p class="footer-note mb-0"><code>php scripts/validate-version-manifest.php</code></p>
                </div>
              </div>
            </div>
          </div>

          <div class="footer-meta-bar mt-4">
            <div class="grid gap-2 footer-meta-copy">
              <p class="footer-note">The application base keeps the browser-title contract, consent shell and one-way dependency on the vendored FNLLA Web runtime.</p>
            </div>
            <nav class="footer-legal" aria-label="Project footer tools">
              <button class="btn btn-ghost btn-sm" type="button" data-fnlla-consent-open>Cookie settings</button>
            </nav>
          </div>
        </div>
      </div>
    </footer>
  </div>

  <aside class="consent-banner" data-fnlla-consent data-fnlla-consent-cookie="fnlla-php-consent" data-fnlla-consent-settings="#cookie-settings-modal" aria-label="Cookie consent banner">
    <div class="consent-banner-grid">
      <div class="consent-copy">
        <p class="consent-kicker">Cookie settings</p>
        <h2 class="consent-title">Choose which optional cookies this project may use.</h2>
        <p class="consent-text">Necessary cookies keep sessions, CSRF protection and the runtime shell working. Optional categories should be enabled only when the downstream project genuinely uses them.</p>
        <p class="consent-meta">The application keeps this first-party and local, with no external scripts required.</p>
      </div>
      <div class="consent-actions">
        <button class="btn btn-primary btn-sm" type="button" data-fnlla-consent-accept="all">Accept all</button>
        <button class="btn btn-outline btn-sm" type="button" data-fnlla-consent-open>Cookie settings</button>
        <button class="btn btn-ghost btn-sm" type="button" data-fnlla-consent-accept="necessary">Necessary only</button>
      </div>
    </div>
  </aside>

  <div class="modal" id="cookie-settings-modal" data-fnlla-modal data-fnlla-consent-modal data-fnlla-consent-cookie="fnlla-php-consent" role="dialog" aria-modal="true" aria-labelledby="cookie-settings-modal-title" hidden>
    <div class="modal-content">
      <div class="d-flex justify-between items-center mb-3">
        <h2 class="content-title mb-0" id="cookie-settings-modal-title">Cookie settings</h2>
        <button class="btn btn-ghost btn-sm" type="button" data-fnlla-modal-close data-fnlla-modal-initial-focus>Close</button>
      </div>
      <div class="consent-preferences">
        <ul class="consent-switch-list" aria-label="Cookie categories">
          <li class="consent-switch-item">
            <div class="consent-switch-head">
              <div class="consent-switch-copy">
                <p class="consent-switch-title">Necessary cookies</p>
                <p class="consent-switch-text">Required for sessions, request protection and the local runtime shell.</p>
              </div>
              <label class="switch">
                <input class="switch-input" type="checkbox" data-fnlla-consent-category="necessary" checked disabled>
                <span class="switch-slider" aria-hidden="true"></span>
                <span class="switch-label">Always on</span>
              </label>
            </div>
          </li>
          <li class="consent-switch-item">
            <div class="consent-switch-head">
              <div class="consent-switch-copy">
                <p class="consent-switch-title">Preferences</p>
                <p class="consent-switch-text">For optional visitor preferences such as UI-level personalization.</p>
              </div>
              <label class="switch">
                <input class="switch-input" type="checkbox" data-fnlla-consent-category="preferences">
                <span class="switch-slider" aria-hidden="true"></span>
                <span class="switch-label">Allow</span>
              </label>
            </div>
          </li>
          <li class="consent-switch-item">
            <div class="consent-switch-head">
              <div class="consent-switch-copy">
                <p class="consent-switch-title">Analytics</p>
                <p class="consent-switch-text">For downstream analytics that a real project explicitly decides to add.</p>
              </div>
              <label class="switch">
                <input class="switch-input" type="checkbox" data-fnlla-consent-category="analytics">
                <span class="switch-slider" aria-hidden="true"></span>
                <span class="switch-label">Allow</span>
              </label>
            </div>
          </li>
          <li class="consent-switch-item">
            <div class="consent-switch-head">
              <div class="consent-switch-copy">
                <p class="consent-switch-title">Marketing</p>
                <p class="consent-switch-text">For optional downstream campaigns and attribution flows when the project actually needs them.</p>
              </div>
              <label class="switch">
                <input class="switch-input" type="checkbox" data-fnlla-consent-category="marketing">
                <span class="switch-slider" aria-hidden="true"></span>
                <span class="switch-label">Allow</span>
              </label>
            </div>
          </li>
        </ul>
      </div>
      <div class="d-flex flex-wrap gap-md mt-3">
        <button class="btn btn-primary btn-sm" type="button" data-fnlla-consent-save>Save preferences</button>
        <button class="btn btn-outline btn-sm" type="button" data-fnlla-consent-accept="all">Accept all</button>
        <button class="btn btn-ghost btn-sm" type="button" data-fnlla-consent-reset>Reset</button>
      </div>
    </div>
  </div>

  <script src="<?= h(asset("vendor/fnlla-web/assets/js/fnlla-web.js")) ?>"></script>
</body>
</html>
PHP;

        $homeView = <<<'PHP'
<?php

declare(strict_types=1);
?>
<section class="section pt-1">
  <div class="container site-page-stack">
    <section class="hero hero-background" aria-label="Project application hero">
      <div class="grid gap-md hero-copy hero-background-copy">
        <div class="d-flex flex-wrap items-center gap-md">
          <span class="tag">Application-first export</span>
          <span class="badge">FNLLA Web included</span>
          <span class="badge">Server-rendered by default</span>
        </div>
        <h1 class="hero-title">Start from a real project shell, not from a second framework showcase.</h1>
        <p class="hero-text">This exported repository keeps the full FNLLA PHP and FNLLA Web runtime under the hood, but begins with a smaller application surface so teams can move straight into delivery work.</p>
        <ul class="hero-proof-list">
          <li>The public app shell already runs with the vendored FNLLA Web runtime.</li>
          <li>The contact flow demonstrates a real request lifecycle with CSRF, validation and flash feedback.</li>
          <li>The framework maintenance page adds a professional browser front-end for update checks and safe apply runs.</li>
          <li>The health endpoint and validation commands stay available for release hygiene.</li>
        </ul>
        <div class="hero-actions">
          <a class="btn btn-primary btn-xl" href="<?= h(route("contact")) ?>">Open the contact flow</a>
          <a class="btn btn-outline" href="<?= h(route("maintenance.framework_update")) ?>">Open framework updates</a>
          <a class="btn btn-outline" href="<?= h(route("api.health")) ?>">Check the health endpoint</a>
        </div>
      </div>
    </section>
  </div>
</section>

<section class="section">
  <div class="container">
    <section class="feature-section" aria-label="Foundation cards">
      <div class="section-header mb-0">
        <p class="feature-kicker">What stays included</p>
        <h2 class="section-title">The application surface is leaner, but the product runtime remains complete.</h2>
        <p class="section-text">Use this as the base for the real application and grow only the parts that belong to the project.</p>
      </div>
      <div class="grid grid-3 gap-md">
        <?php foreach ($foundationCards as $card): ?>
        <article class="feature-card">
          <h3 class="content-title"><?= h($card["title"]) ?></h3>
          <p class="content-text"><?= h($card["text"]) ?></p>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
  </div>
</section>

<section class="section">
  <div class="container">
    <section class="process-section" aria-label="Suggested delivery path">
      <div class="section-header mb-0">
        <p class="process-kicker">Suggested path</p>
        <h2 class="section-title">Three straightforward steps to turn the application base into the real product.</h2>
      </div>
      <div class="process-grid">
        <?php foreach ($deliverySteps as $step): ?>
        <article class="process-step">
          <span class="process-step-number"><?= h($step["number"]) ?></span>
          <h3 class="process-step-title"><?= h($step["title"]) ?></h3>
          <p class="process-step-text"><?= h($step["text"]) ?></p>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
  </div>
</section>
PHP;

        $contactView = <<<'PHP'
<?php

declare(strict_types=1);

$nameError = error_for("name");
$emailError = error_for("email");
$topicError = error_for("topic");
$messageError = error_for("message");
$allErrors = errors();
?>
<section class="section pt-1">
  <div class="container site-page-stack">
    <section class="hero hero-compact" aria-label="Contact page introduction">
      <div class="grid gap-md hero-copy">
        <div class="d-flex flex-wrap items-center gap-md">
          <span class="tag">Working form example</span>
          <span class="badge">Validation</span>
          <span class="badge">CSRF</span>
          <span class="badge">Flash feedback</span>
        </div>
        <h1 class="hero-title">A real project contact flow is already part of the exported application shell.</h1>
        <p class="hero-text">Use this page as the first delivery surface to reshape: replace the placeholder copy, route it to the real mailbox or CRM, and adjust the validation to the real project process.</p>
      </div>
    </section>
  </div>
</section>

<section class="section">
  <div class="container">
    <section class="contact-section" id="contact-form">
      <div class="contact-grid">
        <aside class="contact-card contact-summary-card" aria-label="Contact section summary">
          <p class="contact-kicker">Project baseline</p>
          <h2 class="contact-card-title">Keep one working server-rendered intake pattern and adapt it to the real delivery flow.</h2>
          <p class="contact-text">The exported project already includes request capture, validation, flash messages and redirect-after-post behavior.</p>
          <ul class="contact-list">
            <li>CSRF token verification on submit</li>
            <li>Session-backed flash messages</li>
            <li>Preserved input values after validation errors</li>
            <li>Mail and event hooks after a successful submit</li>
          </ul>
        </aside>

        <article class="cta-card contact-form-card">
          <form class="form contact-form" action="<?= h(route("contact.submit")) ?>" method="post" novalidate>
            <?= csrf_field() ?>

            <?php if ($allErrors !== []): ?>
            <div class="form-message form-message-error" role="alert" aria-labelledby="contact-form-error-title" aria-describedby="contact-form-error-text">
              <h3 class="form-message-title" id="contact-form-error-title">We still need a few details</h3>
              <p class="form-message-text" id="contact-form-error-text">Review the highlighted fields below before resubmitting the request.</p>
            </div>
            <?php endif; ?>

            <div class="grid grid-2 contact-field-grid">
              <div class="form-group contact-field">
                <label class="label" for="contact-name">Name</label>
                <input class="input" id="contact-name" name="name" type="text" autocomplete="name" placeholder="Your name" aria-describedby="<?= $nameError ? 'contact-name-error' : 'contact-name-help' ?>" <?= $nameError ? 'aria-invalid="true"' : "" ?> value="<?= h((string) old("name")) ?>" required>
                <div class="contact-field-meta">
                  <?php if ($nameError): ?>
                  <p class="error-text" id="contact-name-error"><?= h($nameError) ?></p>
                  <?php else: ?>
                  <p class="help-text" id="contact-name-help">Enter the person who owns the request.</p>
                  <?php endif; ?>
                </div>
              </div>

              <div class="form-group contact-field">
                <label class="label" for="contact-company">Company</label>
                <input class="input" id="contact-company" name="company" type="text" autocomplete="organization" placeholder="Your company" value="<?= h((string) old("company")) ?>">
                <div class="contact-field-meta">
                  <p class="help-text">Optional when the request is individual rather than organizational.</p>
                </div>
              </div>
            </div>

            <div class="grid grid-2 contact-field-grid">
              <div class="form-group contact-field">
                <label class="label" for="contact-email">Email</label>
                <input class="input" id="contact-email" name="email" type="email" autocomplete="email" placeholder="you@example.com" aria-describedby="<?= $emailError ? 'contact-email-error' : 'contact-email-help' ?>" <?= $emailError ? 'aria-invalid="true"' : "" ?> value="<?= h((string) old("email")) ?>" required>
                <div class="contact-field-meta">
                  <?php if ($emailError): ?>
                  <p class="error-text" id="contact-email-error"><?= h($emailError) ?></p>
                  <?php else: ?>
                  <p class="help-text" id="contact-email-help">Use the address where project updates should be sent.</p>
                  <?php endif; ?>
                </div>
              </div>

              <div class="form-group contact-field">
                <label class="label" for="contact-topic">Topic</label>
                <select class="select" id="contact-topic" name="topic" aria-describedby="<?= $topicError ? 'contact-topic-error' : 'contact-topic-help' ?>" <?= $topicError ? 'aria-invalid="true"' : "" ?>>
                  <?php $selectedTopic = (string) old("topic", "Portal or application"); ?>
                  <?php foreach ($contactTopics as $topicOption): ?>
                  <option value="<?= h($topicOption) ?>" <?= $selectedTopic === $topicOption ? "selected" : "" ?>><?= h($topicOption) ?></option>
                  <?php endforeach; ?>
                </select>
                <?php if ($topicError): ?>
                <p class="error-text" id="contact-topic-error"><?= h($topicError) ?></p>
                <?php else: ?>
                <p class="help-text" id="contact-topic-help">Choose the path that best matches the project request.</p>
                <?php endif; ?>
              </div>
            </div>

            <div class="form-group">
              <label class="label" for="contact-message">Message</label>
              <textarea class="textarea" id="contact-message" name="message" placeholder="Outline the goals, timing and any important implementation notes." aria-describedby="<?= $messageError ? 'contact-message-error' : 'contact-message-help' ?>" <?= $messageError ? 'aria-invalid="true"' : "" ?>><?= h((string) old("message")) ?></textarea>
              <?php if ($messageError): ?>
              <p class="error-text" id="contact-message-error"><?= h($messageError) ?></p>
              <?php else: ?>
              <p class="help-text" id="contact-message-help">A short project summary is enough for the initial application shell.</p>
              <?php endif; ?>
            </div>

            <div class="d-flex flex-wrap gap-md">
              <button class="btn btn-primary" type="submit">Submit request</button>
              <a class="btn btn-ghost" href="<?= h(route("home")) ?>">Back to home</a>
            </div>
          </form>
        </article>
      </div>
    </section>
  </div>
</section>
PHP;

        file_put_contents($targetRoot . DIRECTORY_SEPARATOR . "routes" . DIRECTORY_SEPARATOR . "web.php", $routes . PHP_EOL);
        file_put_contents($targetRoot . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR . "Controllers" . DIRECTORY_SEPARATOR . "HomeController.php", $controller . PHP_EOL);
        file_put_contents($targetRoot . DIRECTORY_SEPARATOR . "views" . DIRECTORY_SEPARATOR . "layouts" . DIRECTORY_SEPARATOR . "app.php", $layout . PHP_EOL);
        file_put_contents($targetRoot . DIRECTORY_SEPARATOR . "views" . DIRECTORY_SEPARATOR . "pages" . DIRECTORY_SEPARATOR . "home.php", $homeView . PHP_EOL);
        file_put_contents($targetRoot . DIRECTORY_SEPARATOR . "views" . DIRECTORY_SEPARATOR . "pages" . DIRECTORY_SEPARATOR . "contact.php", $contactView . PHP_EOL);
    }

    private function rewriteDatabaseSurface(string $targetRoot): void
    {
        $seeder = <<<'PHP'
<?php

declare(strict_types=1);

/*
===============================================================================
PROJECT DATABASE SEEDER
File: database\seeders\DatabaseSeeder.php
Purpose:
- Keeps the exported project ready for project-specific seed data without shipping demo users by default.
===============================================================================
*/

namespace Database\Seeders;

use Fnlla\Php\Database\Seeders\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Add project-specific seed data here when the application needs it.
    }
}
PHP;

        file_put_contents($targetRoot . DIRECTORY_SEPARATOR . "database" . DIRECTORY_SEPARATOR . "seeders" . DIRECTORY_SEPARATOR . "DatabaseSeeder.php", $seeder . PHP_EOL);
    }

    private function rewriteProjectTests(string $targetRoot): void
    {
        $bootstrapAutoloadTest = <<<'PHP'
<?php

declare(strict_types=1);

/*
===============================================================================
PROJECT TEST CASE
File: tests\BootstrapAutoloadTest.php
Purpose:
- Confirms the exported project can autoload the PSR-4 namespaces it actually ships.
===============================================================================
*/

namespace Fnlla\Php\Tests;

use PHPUnit\Framework\TestCase;

final class BootstrapAutoloadTest extends TestCase
{
    public function testFallbackAutoloaderResolvesExportedProjectNamespacesWithoutVendorAutoload(): void
    {
        self::assertFalse(is_file(base_path("vendor/autoload.php")));
        self::assertTrue(class_exists("Database\\Seeders\\DatabaseSeeder"));
        self::assertFalse(class_exists("Database\\Factories\\UserFactory"));
    }
}
PHP;

        file_put_contents($targetRoot . DIRECTORY_SEPARATOR . "tests" . DIRECTORY_SEPARATOR . "BootstrapAutoloadTest.php", $bootstrapAutoloadTest . PHP_EOL);
    }

    private function rewriteConsoleLaunchers(string $targetRoot): void
    {
        $launcher = <<<'PHP'
#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PROJECT LAUNCHER
File: fnlla
Purpose:
- Boots the exported FNLLA PHP project console and exposes downstream-safe commands.
===============================================================================
*/

use Fnlla\Php\Console\Commands\CacheClearCommand;
use Fnlla\Php\Console\Commands\FnllaWebSyncCommand;
use Fnlla\Php\Console\Commands\FnllaWebValidateCommand;
use Fnlla\Php\Console\Commands\FrameworkUpdateCommand;
use Fnlla\Php\Console\Commands\MigrateCommand;
use Fnlla\Php\Console\Commands\MigrateRollbackCommand;
use Fnlla\Php\Console\Commands\MigrateStatusCommand;
use Fnlla\Php\Console\Commands\QueueWorkCommand;
use Fnlla\Php\Console\Commands\RouteListCommand;
use Fnlla\Php\Console\Commands\ScheduleRunCommand;
use Fnlla\Php\Console\Commands\SeedCommand;
use Fnlla\Php\Console\Commands\StarterUpdateCommand;
use Fnlla\Php\Console\Commands\VersionStatusCommand;
use Fnlla\Php\Console\Commands\VersionSyncCommand;

if (in_array($_SERVER["argv"][1] ?? "", ["fnlla-web:sync", "fnlla-web:validate", "framework:update", "starter:update", "version:status", "version:sync"], true) && !defined("FNLLA_WEB_SKIP_AUTO_GUARD")) {
    define("FNLLA_WEB_SKIP_AUTO_GUARD", true);
}

$container = require __DIR__ . DIRECTORY_SEPARATOR . "bootstrap" . DIRECTORY_SEPARATOR . "console.php";

$console = $container->make(\Fnlla\Php\Console\Application::class);
$console->register(CacheClearCommand::class);
$console->register(FnllaWebSyncCommand::class);
$console->register(FnllaWebValidateCommand::class);
$console->register(FrameworkUpdateCommand::class);
$console->register(SeedCommand::class);
$console->register(MigrateRollbackCommand::class);
$console->register(MigrateCommand::class);
$console->register(MigrateStatusCommand::class);
$console->register(QueueWorkCommand::class);
$console->register(RouteListCommand::class);
$console->register(ScheduleRunCommand::class);
$console->register(StarterUpdateCommand::class);
$console->register(VersionStatusCommand::class);
$console->register(VersionSyncCommand::class);

exit($console->run($_SERVER["argv"] ?? []));
PHP;

        $windowsLauncher = <<<'CMD'
@echo off
REM ============================================================================
REM FNLLA PROJECT LAUNCHER
REM File: fnlla.cmd
REM Purpose: Provides a Windows launcher for downstream-safe FNLLA PHP project commands.
REM ============================================================================
setlocal
php "%~dp0fnlla" %*
CMD;

        file_put_contents($targetRoot . DIRECTORY_SEPARATOR . "fnlla", $launcher . PHP_EOL);
        file_put_contents($targetRoot . DIRECTORY_SEPARATOR . "fnlla.cmd", $windowsLauncher . PHP_EOL);
    }

    private function rewriteProjectLaunchers(string $targetRoot): void
    {
        $legacyLaunchers = [
            $targetRoot . DIRECTORY_SEPARATOR . "test-fnlla-php.cmd",
            $targetRoot . DIRECTORY_SEPARATOR . "lint-fnlla-php.cmd",
        ];

        foreach ($legacyLaunchers as $legacyLauncher) {
            if (is_file($legacyLauncher)) {
                unlink($legacyLauncher);
            }
        }

        $testLauncher = <<<'CMD'
@echo off
REM ============================================================================
REM FNLLA PROJECT LAUNCHER
REM File: test-project.cmd
REM Purpose: Runs the local FNLLA PHP project test suite for this application.
REM ============================================================================
setlocal
php "%~dp0scripts\test.php" %*
CMD;

        $lintLauncher = <<<'CMD'
@echo off
REM ============================================================================
REM FNLLA PROJECT LAUNCHER
REM File: lint-project.cmd
REM Purpose: Runs syntax lint and FNLLA Web validation for this project.
REM ============================================================================
setlocal
php "%~dp0scripts\lint.php" || exit /b %ERRORLEVEL%
php "%~dp0scripts\validate-fnlla-web.php" || exit /b %ERRORLEVEL%
php "%~dp0scripts\validate-version-manifest.php" || exit /b %ERRORLEVEL%
CMD;

        file_put_contents($targetRoot . DIRECTORY_SEPARATOR . "test-project.cmd", $testLauncher . PHP_EOL);
        file_put_contents($targetRoot . DIRECTORY_SEPARATOR . "lint-project.cmd", $lintLauncher . PHP_EOL);
    }

    private function resolveTargetPath(string $targetArgument): string
    {
        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $targetArgument) === 1 || str_starts_with($targetArgument, "\\\\") || str_starts_with($targetArgument, "/")) {
            return $this->normalizePath($targetArgument);
        }

        return $this->normalizePath((string) getcwd() . DIRECTORY_SEPARATOR . $targetArgument);
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $path);
        $segments = [];
        $prefix = "";

        if (preg_match('/^[A-Za-z]:/', $path) === 1) {
            $prefix = strtoupper(substr($path, 0, 2));
            $path = substr($path, 2);
        } elseif (str_starts_with($path, DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR)) {
            $prefix = DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR;
            $path = substr($path, 2);
        }

        $isAbsolute = str_starts_with($path, DIRECTORY_SEPARATOR);
        $parts = preg_split('/[\\\\\\/]+/', $path) ?: [];

        foreach ($parts as $part) {
            if ($part === "" || $part === ".") {
                continue;
            }

            if ($part === "..") {
                if ($segments !== [] && end($segments) !== "..") {
                    array_pop($segments);
                } elseif (!$isAbsolute) {
                    $segments[] = $part;
                }

                continue;
            }

            $segments[] = $part;
        }

        $normalized = implode(DIRECTORY_SEPARATOR, $segments);

        if ($prefix !== "") {
            return $prefix . DIRECTORY_SEPARATOR . $normalized;
        }

        return ($isAbsolute ? DIRECTORY_SEPARATOR : "") . $normalized;
    }

    private function normalizeSeparators(string $path): string
    {
        return str_replace("\\", "/", $path);
    }

    private function pathsEqual(string $left, string $right): bool
    {
        return strcasecmp(rtrim($left, "\\/"), rtrim($right, "\\/")) === 0;
    }

    private function isChildPath(string $childPath, string $parentPath): bool
    {
        $child = rtrim(strtolower($childPath), "\\/");
        $parent = rtrim(strtolower($parentPath), "\\/");

        return str_starts_with($child, $parent . DIRECTORY_SEPARATOR);
    }

    private function guessAppName(string $targetPath): string
    {
        $basename = basename(rtrim($targetPath, "\\/"));
        $basename = preg_replace('/[-_]+/', " ", $basename);

        return ucwords(trim((string) $basename));
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', "-", $value);

        return trim((string) $value, "-");
    }
}
