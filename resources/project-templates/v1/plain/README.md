# Plain FNLLA Project

This project contains the HTTP/application core, not the Developer Panel, UI runtime,
analytics, heatmaps, Kanban, customer portal, AI tools or demonstration website.

## Local Development

1. Copy `.env.example` to `.env` and set the application name and database credentials.
2. Run `composer install`. Core is a local `packages/fnlla-core` path package;
   downloading development tools requires package access. The fallback bootstrap
   works offline before Composer installation.
3. Run `php scripts/test.php`, `php scripts/lint.php` and `php fnlla route:list`.
4. Start `php -S 127.0.0.1:8080 -t public public/router.php` using an available port.

Application code belongs in `app/` (`App\`), routes in `routes/`, templates in `views/`.
The core (`Fnlla\Php\`) is a separate Composer library. Do not modify `vendor/`.
Database access is lazy: the homepage and `/api/health` do not require a database.
The health endpoint is liveness only, not database or deployment readiness.

After installation, `composer test:unit` runs real PHPUnit and `composer analyse`
runs PHPStan level 5 for app and core. `php scripts/test.php` stays available offline.
Commit composer.lock; development tools are not production dependencies.

## Core Updates

The core package is bundled locally, not assumed to exist on Packagist. Obtain a reviewed
new core package from a newer FNLLA plain export, replace `packages/fnlla-core`, update
the exact version in `composer.json`, then run `composer update techayodev/fnlla-core`.
Commit `composer.lock` and run project tests. Keep the previous deployment for rollback.
The full distribution's `framework:update` command is deliberately absent.
The package boundary is implemented; a public Composer release channel is not yet published.

## Production

Point the web server document root at `public/`, never the project root. Set
`APP_ENV=production`, `APP_DEBUG=false`, configure HTTPS and `SESSION_SECURE=true`.
Keep `.env`, storage and database backups private. Run migrations explicitly with
`php fnlla migrate`; rollback requires reviewing each migration's `down()` behavior.
Install locked dependencies with `composer install --no-dev --optimize-autoloader`.
The local smoke-test runner has a limited PHPUnit-compatible API, not full PHPUnit.
