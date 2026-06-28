<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP VIEW LAYOUT
File: views\layouts\app.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Defines the shared delivery shell for server-rendered pages built on FNLLA UI.
*/

$pageStatus = flash("status");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#1A4137">
  <title><?= h(($pageTitle ?? config("app.name")) . " | " . config("app.name")) ?></title>
  <link rel="stylesheet" href="<?= h(asset("vendor/fnlla-ui/assets/css/fnlla-ui.css")) ?>">
  <link rel="stylesheet" href="<?= h(asset("assets/app.css")) ?>">
</head>
<body data-fnlla-theme="default">
  <div class="wrapper">
    <header class="site-header-shell">
      <div class="container py-3">
        <nav class="navbar" aria-label="Primary navigation">
          <a class="navbar-brand" href="<?= h(route("home")) ?>">
            <span class="site-brand-text">
              <span class="site-brand-mark">FP</span>
              <?= h(config("app.name")) ?>
            </span>
          </a>
          <button class="btn btn-outline btn-sm navbar-toggle" type="button" data-fnlla-nav-toggle aria-controls="primary-navigation-panel" aria-expanded="false" aria-label="Toggle navigation menu">Menu</button>
          <div class="navbar-panel" id="primary-navigation-panel">
            <ul class="navbar-menu">
              <li><a href="<?= h(route("home")) ?>" <?= is_current_path("/") ? 'aria-current="page"' : "" ?>>Home</a></li>
              <li><a href="<?= h(route("about")) ?>" <?= is_current_path("/about") ? 'aria-current="page"' : "" ?>>About</a></li>
              <li><a href="<?= h(route("contact")) ?>" <?= is_current_path("/contact") ? 'aria-current="page"' : "" ?>>Contact</a></li>
              <?php if (auth()->check()): ?>
              <li><a href="<?= h(route("dashboard")) ?>" <?= is_current_path("/dashboard") ? 'aria-current="page"' : "" ?>>Dashboard</a></li>
              <?php if (gate()->allows("manage-admin-area")): ?>
              <li><a href="<?= h(route("admin")) ?>" <?= is_current_path("/admin") ? 'aria-current="page"' : "" ?>>Admin</a></li>
              <?php endif; ?>
              <?php else: ?>
              <li><a href="<?= h(route("login")) ?>" <?= is_current_path("/login") ? 'aria-current="page"' : "" ?>>Login</a></li>
              <?php endif; ?>
              <li class="dropdown" data-fnlla-dropdown>
                <button class="btn btn-outline btn-sm" id="resource-menu-trigger" type="button" data-fnlla-dropdown-toggle aria-expanded="false" aria-controls="resource-menu">Resources</button>
                <div class="dropdown-menu" id="resource-menu" aria-labelledby="resource-menu-trigger">
                  <a class="dropdown-item" href="<?= h(route("api.health")) ?>">JSON health route</a>
                  <a class="dropdown-item" href="https://github.com/fnlla/ui" target="_blank" rel="noreferrer">FNLLA UI repo</a>
                </div>
              </li>
            </ul>
            <div class="navbar-actions">
              <button class="btn btn-ghost btn-sm" type="button" data-fnlla-offcanvas-open="#starter-panel" aria-controls="starter-panel">Starter notes</button>
              <?php if (auth()->check()): ?>
              <form action="<?= h(route("logout")) ?>" method="post" class="d-inline">
                <?= csrf_field() ?>
                <button class="btn btn-primary btn-sm" type="submit">Sign out</button>
              </form>
              <?php else: ?>
              <a class="btn btn-primary btn-sm" href="<?= h(route("login")) ?>">Sign in</a>
              <?php endif; ?>
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

    <footer class="section site-footer">
      <div class="container">
        <div class="site-footer-grid">
          <article class="card site-card-muted">
            <h2 class="card-title">Small on purpose</h2>
            <p class="card-text">FNLLA PHP keeps the learning surface narrow: front controller, router, controllers, views and a few request helpers.</p>
          </article>
          <article class="card site-card-muted">
            <h2 class="card-title">UI already bundled</h2>
            <p class="card-text">The project vendors FNLLA UI locally, so its styles, scripts and icons can be shipped without external CDNs.</p>
          </article>
          <article class="card site-card-muted">
            <h2 class="card-title">Good first extension points</h2>
            <p class="card-text">Add route parameters, persistence, mail delivery or domain-specific services only when the app genuinely needs them.</p>
          </article>
        </div>
      </div>
    </footer>
  </div>

  <div class="offcanvas offcanvas-end" id="starter-panel" data-fnlla-offcanvas role="dialog" aria-modal="true" aria-labelledby="starter-panel-title" aria-describedby="starter-panel-description" hidden>
    <div class="offcanvas-panel">
      <div class="offcanvas-header">
        <div>
          <h2 class="content-title mb-1" id="starter-panel-title">Starter project notes</h2>
          <p class="content-text" id="starter-panel-description">This panel shows how FNLLA PHP can use FNLLA UI overlays without any custom JavaScript glue.</p>
        </div>
        <button class="btn btn-ghost btn-sm" type="button" data-fnlla-offcanvas-close data-fnlla-offcanvas-initial-focus>Close</button>
      </div>
      <div class="offcanvas-body">
        <section class="offcanvas-section" aria-label="What is included">
          <p class="offcanvas-kicker">Included</p>
          <div class="list-group list-group-nav">
            <div class="list-group-item"><span class="list-group-link">Plain PHP router and controller flow</span></div>
            <div class="list-group-item"><span class="list-group-link">FNLLA UI runtime vendored locally</span></div>
            <div class="list-group-item"><span class="list-group-link">Form validation, flashes and CSRF token handling</span></div>
          </div>
        </section>
        <div class="d-flex flex-wrap gap-md">
          <a class="btn btn-primary btn-sm" href="<?= h(route("contact")) ?>">Open contact flow</a>
          <a class="btn btn-outline btn-sm" href="<?= h(route("api.health")) ?>">Open JSON route</a>
        </div>
      </div>
    </div>
  </div>

  <div class="toast-stack" aria-live="polite" aria-label="Application notifications">
    <?php if (is_array($pageStatus) && (($pageStatus["toast"] ?? false) === true)): ?>
    <article class="toast toast-success" id="page-status-toast" data-fnlla-toast data-fnlla-toast-autohide="4500" role="status" hidden>
      <h2 class="toast-title"><?= h((string) $pageStatus["title"]) ?></h2>
      <p class="toast-text"><?= h((string) $pageStatus["text"]) ?></p>
      <div class="toast-actions">
        <button class="btn btn-ghost btn-sm" type="button" data-fnlla-toast-close>Dismiss</button>
      </div>
    </article>
    <?php endif; ?>
  </div>

  <script src="<?= h(asset("vendor/fnlla-ui/assets/js/fnlla-ui.js")) ?>"></script>
  <?php if (is_array($pageStatus) && (($pageStatus["toast"] ?? false) === true)): ?>
  <script>
    window.addEventListener("DOMContentLoaded", function () {
      if (window.FNLLAUI && typeof window.FNLLAUI.showToast === "function") {
        window.FNLLAUI.showToast("#page-status-toast");
      }
    });
  </script>
  <?php endif; ?>
</body>
</html>
