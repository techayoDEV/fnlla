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
- Defines the shared delivery shell for server-rendered pages built on FNLLA Web.
*/

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
              <span class="site-brand-mark">FP</span>
              <?= h(config("app.name")) ?>
            </span>
          </a>
          <button class="btn btn-outline btn-sm navbar-toggle" type="button" data-fnlla-nav-toggle aria-controls="primary-navigation-panel" aria-expanded="false" aria-label="Toggle navigation menu">Menu</button>
          <div class="navbar-panel" id="primary-navigation-panel">
            <ul class="navbar-menu">
              <li><a href="<?= h(route("home")) ?>" <?= is_current_path("/") ? 'aria-current="page"' : "" ?>>Home</a></li>
              <li><a href="<?= h(route("platform")) ?>" <?= is_current_path("/platform") ? 'aria-current="page"' : "" ?>>Platform</a></li>
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
                  <a class="dropdown-item" href="<?= h(route("platform")) ?>">Platform overview</a>
                  <a class="dropdown-item" href="<?= h(route("api.health")) ?>">JSON health route</a>
                  <a class="dropdown-item" href="https://github.com/fnlla/web" target="_blank" rel="noreferrer">FNLLA Web repo</a>
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

    <footer class="section site-footer-shell">
      <div class="container">
        <div class="footer p-4 radius-lg" aria-label="FNLLA PHP starter footer">
          <div class="footer-top">
            <div class="footer-lead">
              <p class="help-text mb-1">FNLLA PHP starter</p>
              <h2 class="footer-heading">A cleaner foundation for teams that want professional server-rendered delivery without a bloated framework shell.</h2>
              <p class="footer-note">The starter keeps request flow, UI runtime boundaries and validation commands visible, while FNLLA Web carries the reusable presentation system.</p>
            </div>
            <div class="footer-pillars">
              <article class="footer-pillar">
                <span class="badge">Readable request flow</span>
                <p class="footer-note">Trace bootstrap, routes, controllers and views without hidden generated layers.</p>
              </article>
              <article class="footer-pillar">
                <span class="badge">Local UI runtime</span>
                <p class="footer-note">Vendored FNLLA Web assets ship inside the project, with no external CDN dependency.</p>
              </article>
              <article class="footer-pillar">
                <span class="badge">Release hygiene</span>
                <p class="footer-note">Validation, version metadata and runtime sync remain explicit project commands.</p>
              </article>
            </div>
          </div>

          <div class="footer-body">
            <div class="footer-grid">
              <div class="footer-brand-block">
                <h3 class="footer-heading"><?= h(config("app.name")) ?></h3>
                <p class="footer-note">Use this starter for service websites, internal tools, authenticated portals and delivery surfaces that benefit from clarity, composability and an inspectable runtime contract.</p>
                <div class="footer-status">
                  <span class="badge">FNLLA Web only</span>
                  <span class="badge">PHP 8.3</span>
                  <span class="badge">MySQL ready</span>
                </div>
                <div class="footer-contact">
                  <p class="footer-note">Routes, controllers and templates are ready to replace with real product flow.</p>
                  <p class="footer-note">Cookie consent, overlays, tabs and mobile navigation already run on the bundled runtime.</p>
                </div>
              </div>

              <div class="footer-link-group">
                <h3 class="footer-heading">Explore</h3>
                <div class="footer-links">
                  <a href="<?= h(route("home")) ?>">Home</a>
                  <a href="<?= h(route("platform")) ?>">Platform</a>
                  <a href="<?= h(route("about")) ?>">About</a>
                  <a href="<?= h(route("contact")) ?>">Contact</a>
                </div>
              </div>

              <div class="footer-link-group">
                <h3 class="footer-heading">Starter flow</h3>
                <div class="footer-links">
                  <a href="<?= h(route("login")) ?>">Sign in demo</a>
                  <a href="<?= h(route("api.health")) ?>">Health endpoint</a>
                  <a href="https://github.com/fnlla/web" target="_blank" rel="noreferrer">FNLLA Web repo</a>
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
              <p class="footer-note">FNLLA PHP starter ships with the browser-title contract, an optional cookie-consent shell and one-way dependency on the vendored FNLLA Web runtime.</p>
            </div>
            <nav class="footer-legal" aria-label="Starter footer tools">
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
        <p class="consent-kicker">Cookie preferences</p>
        <h2 class="consent-title">Choose which optional cookies this project may use before analytics or personalization are introduced.</h2>
        <p class="consent-text">Necessary cookies keep sessions, CSRF protection and the runtime shell working. Optional categories should stay off until the downstream project has a confirmed business reason and a clear implementation plan for them.</p>
        <p class="consent-meta">This starter keeps consent first-party, local and transparent. Nothing here depends on external tag managers or third-party scripts.</p>
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
      <div class="grid grid-2 gap-md mb-3">
        <article class="feature-card">
          <h3 class="content-title">What this controls</h3>
          <p class="content-text mb-0">These settings decide whether the project may enable non-essential client-side behaviors such as analytics, preference storage or campaign attribution after the real product adds them.</p>
        </article>
        <article class="feature-card">
          <h3 class="content-title">How the choice is stored</h3>
          <p class="content-text mb-0">The starter stores the consent state in a first-party cookie only. No external consent vendor or remote preference service is required for the baseline implementation.</p>
        </article>
      </div>
      <div class="form-message mb-3" role="status">
        <h3 class="form-message-title">Developer note</h3>
        <p class="form-message-text mb-0">Keep optional categories disabled until the downstream project documents the purpose, retention model, legal basis and implementation owner for each one.</p>
      </div>
      <div class="consent-preferences">
        <ul class="consent-switch-list" aria-label="Cookie categories">
          <li class="consent-switch-item">
            <div class="consent-switch-head">
              <div class="consent-switch-copy">
                <p class="consent-switch-title">Necessary cookies</p>
                <p class="consent-switch-text">Required for sessions, request protection, consent persistence and the local runtime shell. These are always on because the application cannot operate safely without them.</p>
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
                <p class="consent-switch-text">Use this only for optional visitor preferences such as saved UI choices, remembered content variants or language conveniences that are not strictly required for the service to function.</p>
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
                <p class="consent-switch-text">Use this for measurement tools, funnel analysis or operational product insights only after the project decides which analytics stack is justified and how the data should be governed.</p>
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
                <p class="consent-switch-text">Use this only if the downstream project introduces campaign tracking, advertising pixels or attribution tooling and has clear ownership for the resulting data flow.</p>
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
        <button class="btn btn-ghost btn-sm" type="button" data-fnlla-consent-reset>Reset stored choice</button>
      </div>
    </div>
  </div>

  <div class="offcanvas offcanvas-end" id="starter-panel" data-fnlla-offcanvas role="dialog" aria-modal="true" aria-labelledby="starter-panel-title" aria-describedby="starter-panel-description" hidden>
    <div class="offcanvas-panel">
      <div class="offcanvas-header">
        <div>
          <h2 class="content-title mb-1" id="starter-panel-title">Starter project notes</h2>
          <p class="content-text" id="starter-panel-description">This panel shows how FNLLA PHP can use FNLLA Web overlays without any custom JavaScript glue.</p>
        </div>
        <button class="btn btn-ghost btn-sm" type="button" data-fnlla-offcanvas-close data-fnlla-offcanvas-initial-focus>Close</button>
      </div>
      <div class="offcanvas-body">
        <section class="offcanvas-section" aria-label="What is included">
          <p class="offcanvas-kicker">Included</p>
          <div class="list-group list-group-nav">
            <div class="list-group-item"><span class="list-group-link">Plain PHP router and controller flow</span></div>
            <div class="list-group-item"><span class="list-group-link">FNLLA Web runtime vendored locally</span></div>
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

  <script src="<?= h(asset("vendor/fnlla-web/assets/js/fnlla-web.js")) ?>"></script>
  <?php if (is_array($pageStatus) && (($pageStatus["toast"] ?? false) === true)): ?>
  <script>
    window.addEventListener("DOMContentLoaded", function () {
      if (window.FNLLAWEB && typeof window.FNLLAWEB.showToast === "function") {
        window.FNLLAWEB.showToast("#page-status-toast");
      }
    });
  </script>
  <?php endif; ?>
</body>
</html>
