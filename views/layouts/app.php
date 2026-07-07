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
$currentPath = current_path();
$hasDocumentationWorkspace = has_local_docs_workspace();
$isDocsPath = $currentPath === "/docs" || str_starts_with($currentPath, "/docs/");
$isOperationsSurface = str_starts_with($currentPath, "/maintenance") || $currentPath === "/api/health" || $currentPath === "/health";
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
              <img class="site-brand-logo" src="<?= h(asset("assets/brand/fnlla-mark.svg")) ?>" alt="FNLLA logo">
              <?= h(config("app.name")) ?>
            </span>
          </a>
          <button class="btn btn-outline btn-sm navbar-toggle" type="button" data-fnlla-nav-toggle aria-controls="primary-navigation-panel" aria-expanded="false" aria-label="Toggle navigation menu">Menu</button>
          <div class="navbar-panel" id="primary-navigation-panel">
            <ul class="navbar-menu">
              <li><a href="<?= h(route("home")) ?>" <?= $currentPath === "/" ? 'aria-current="page"' : "" ?>>Home</a></li>
              <li><a href="<?= h(route("project.launch")) ?>" <?= $currentPath === "/project/launch" ? 'aria-current="page"' : "" ?>>Project Launch</a></li>
              <li><a href="<?= h(route("contact")) ?>" <?= $currentPath === "/contact" ? 'aria-current="page"' : "" ?>>Contact</a></li>
              <li><a href="<?= h(route("maintenance.home")) ?>" <?= str_starts_with($currentPath, "/maintenance") ? 'aria-current="page"' : "" ?>>Maintenance</a></li>
              <?php if ($hasDocumentationWorkspace): ?>
              <li><a href="<?= h(route("docs.home")) ?>" <?= $isDocsPath ? 'aria-current="page"' : "" ?>>Docs</a></li>
              <?php endif; ?>
            </ul>
            <div class="navbar-actions">
              <a class="btn btn-primary btn-sm" href="<?= h($isOperationsSurface ? route("api.health") : route("project.launch")) ?>"><?= $isOperationsSurface ? "Health JSON" : "Start the project" ?></a>
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
              <h2 class="footer-heading">The starter is the beginning of the real application, while maintenance and CLI stay linked as framework capabilities.</h2>
              <p class="footer-note">That keeps the public shell project-owned, but still leaves health, update checks and version validation available without inventing a second front-end beside the starter.</p>
            </div>
            <div class="footer-pillars">
              <article class="footer-pillar">
                <span class="badge">Starter-first</span>
                <p class="footer-note">Modify the shipped public starter directly instead of replacing a framework showcase beside it.</p>
              </article>
              <article class="footer-pillar">
                <span class="badge">Linked maintenance</span>
                <p class="footer-note">Framework upkeep lives on dedicated routes without taking over the public information architecture.</p>
              </article>
              <article class="footer-pillar">
                <span class="badge">One UI contract</span>
                <p class="footer-note">FNLLA Web remains the single supported runtime underneath the evolving project surface.</p>
              </article>
            </div>
          </div>

          <div class="footer-body">
            <div class="footer-grid">
              <div class="footer-brand-block">
                <h3 class="footer-heading"><?= h(config("app.name")) ?></h3>
                <p class="footer-note">Use this starter as the real base for service websites, portals, internal tools and other server-rendered application surfaces that grow by replacing starter content with project-specific delivery logic.</p>
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
                  <a href="<?= h(route("project.launch")) ?>">Project launch</a>
                  <a href="<?= h(route("contact")) ?>">Contact</a>
                  <?php if ($hasDocumentationWorkspace): ?>
                  <a href="<?= h(route("docs.home")) ?>">Docs</a>
                  <?php endif; ?>
                  <a href="<?= h(route("maintenance.home")) ?>">Maintenance</a>
                </div>
              </div>

              <div class="footer-link-group">
                <h3 class="footer-heading">Operator links</h3>
                <div class="footer-links">
                  <a href="<?= h(route("maintenance.home")) ?>">Maintenance hub</a>
                  <a href="<?= h(route("maintenance.framework_update")) ?>">Framework updates</a>
                  <a href="<?= h(route("api.health")) ?>">Health endpoint</a>
                  <a href="<?= h(route("health")) ?>">Health view</a>
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
              <p class="footer-note">FNLLA PHP starter ships as the application base itself, while docs, maintenance and validation remain linked capabilities around that base.</p>
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
