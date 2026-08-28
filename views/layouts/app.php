<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA VIEW LAYOUT
File: views\layouts\app.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Defines the shared delivery shell for server-rendered pages built on FNLLA's integrated UI surface.
*/

$pageStatus = flash("status");
$layoutChromeMode = (string) ($layoutChromeMode ?? "default");
$isClientPreviewChrome = $layoutChromeMode === "client-preview";
$currentPath = current_path();
$hasDocumentationWorkspace = has_local_docs_workspace();
$isDocsPath = $currentPath === "/docs" || str_starts_with($currentPath, "/docs/");
$maintenanceAccess = maintenance_access();
$developerAccess = developer_access();
$isMaintenanceLocked = $maintenanceAccess->enabled() && !$maintenanceAccess->isUnlocked();
$hasDeveloperPanelRoute = app(\Fnlla\Php\Routing\Router::class)->routeByName("developer.panel") !== null;
$hasDeveloperHealthRoute = app(\Fnlla\Php\Routing\Router::class)->routeByName("health") !== null;
$hasFrameworkUpdateRoute = app(\Fnlla\Php\Routing\Router::class)->routeByName("maintenance.framework_update") !== null;
$developerSessionActive = $developerAccess->isUnlocked() && $hasDeveloperPanelRoute;
$publicNavigationAvailable = !$isMaintenanceLocked || $developerSessionActive;
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
  <link rel="stylesheet" href="<?= h(asset("vendor/fnlla-runtime/assets/css/fnlla-runtime.css")) ?>">
  <link rel="stylesheet" href="<?= h(asset("assets/app.css")) ?>">
</head>
<body data-fnlla-theme="default"<?= $isClientPreviewChrome ? ' class="client-preview-layout"' : "" ?>>
  <?php if (!$isClientPreviewChrome): ?>
  <header class="project-header">
    <section class="section project-header-section">
      <div class="container">
        <nav class="navbar" aria-label="Primary navigation">
          <a class="navbar-brand project-brand" href="<?= h(route("home")) ?>">
            <span class="project-brand-mark" aria-hidden="true">FN</span>
            <span class="project-brand-name"><?= h((string) config("app.name")) ?></span>
          </a>
          <button class="btn btn-outline btn-sm navbar-toggle" type="button" data-fnlla-nav-toggle aria-controls="primary-navigation-panel" aria-expanded="false" aria-label="Toggle navigation menu">Menu</button>
          <div class="navbar-panel" id="primary-navigation-panel">
            <ul class="navbar-menu">
              <?php if ($publicNavigationAvailable): ?>
              <li><a class="project-nav-link" href="<?= h(route("home")) ?>" <?= $currentPath === "/" ? 'aria-current="page"' : "" ?>>Home</a></li>
              <li><a class="project-nav-link" href="<?= h(route("about")) ?>" <?= $currentPath === "/about" ? 'aria-current="page"' : "" ?>>About</a></li>
              <li><a class="project-nav-link" href="<?= h(route("services")) ?>" <?= $currentPath === "/services" ? 'aria-current="page"' : "" ?>>Services</a></li>
              <li><a class="project-nav-link" href="<?= h(route("contact")) ?>" <?= $currentPath === "/contact" ? 'aria-current="page"' : "" ?>>Contact</a></li>
              <?php else: ?>
              <li><span class="project-nav-link" aria-current="page">Maintenance access required</span></li>
              <?php endif; ?>
            </ul>
            <div class="navbar-actions project-navbar-actions">
              <?php if ($developerSessionActive): ?>
              <div class="dropdown project-operations-dropdown">
                <button class="btn btn-outline btn-sm project-dropdown-toggle" type="button" data-fnlla-dropdown-toggle aria-label="Open developer operations menu">
                  DEV OPERATIONS
                  <span class="project-ops-badge">Active</span>
                </button>
                <div class="dropdown-menu project-dropdown-menu" role="menu">
                  <p class="project-dropdown-heading">Developer session</p>
                  <p class="project-dropdown-meta">Expires <?= h((string) date("H:i T", $developerAccess->expiresAt())) ?></p>
                  <a class="dropdown-item" role="menuitem" href="<?= h(route("developer.panel")) ?>" <?= $currentPath === "/developer/panel" ? 'aria-current="page"' : "" ?>>Panel overview</a>
                  <?php if ($hasDeveloperHealthRoute): ?>
                  <a class="dropdown-item" role="menuitem" href="<?= h(route("health")) ?>" <?= $currentPath === "/maintenance/health" ? 'aria-current="page"' : "" ?>>Health status</a>
                  <?php endif; ?>
                  <?php if ($hasFrameworkUpdateRoute): ?>
                  <a class="dropdown-item" role="menuitem" href="<?= h(route("maintenance.framework_update")) ?>" <?= $currentPath === "/maintenance/framework-update" ? 'aria-current="page"' : "" ?>>Framework updates</a>
                  <?php endif; ?>
                  <form class="project-dropdown-form" action="<?= h(route("developer.lock")) ?>" method="post">
                    <?= csrf_field() ?>
                    <button class="dropdown-item project-dropdown-danger" role="menuitem" type="submit">Lock developer session</button>
                  </form>
                </div>
              </div>
              <?php endif; ?>
              <?php if ($hasDocumentationWorkspace && $publicNavigationAvailable): ?>
              <a class="btn btn-ghost btn-sm project-nav-link" href="<?= h(route("docs.home")) ?>" <?= $isDocsPath ? 'aria-current="page"' : "" ?>>Docs</a>
              <?php endif; ?>
            </div>
          </div>
        </nav>
      </div>
    </section>
  </header>
  <?php endif; ?>

  <?php if (!$isClientPreviewChrome && is_array($pageStatus) && isset($pageStatus["title"], $pageStatus["text"])): ?>
  <section class="section pt-1 pb-0" id="page-status">
    <div class="container">
      <div class="alert alert-<?= h((string) ($pageStatus["variant"] ?? "info")) ?>" role="<?= (($pageStatus["variant"] ?? "") === "danger" || ($pageStatus["variant"] ?? "") === "warning") ? "alert" : "status" ?>">
        <h2 class="alert-title"><?= h((string) $pageStatus["title"]) ?></h2>
        <p class="alert-text"><?= h((string) $pageStatus["text"]) ?></p>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <main>
    <?= $content ?>
  </main>

  <?php if (!$isClientPreviewChrome): ?>
  <footer class="project-footer">
    <section class="section">
      <div class="container">
        <p><strong class="project-footer-brand"><?= h((string) config("app.name")) ?></strong></p>
        <p>This project base is the beginning of the real application.</p>
        <?php if ($publicNavigationAvailable): ?>
        <p class="project-footer-links">
          <a href="<?= h(route("home")) ?>">Home</a>
          <a href="<?= h(route("about")) ?>">About</a>
          <a href="<?= h(route("services")) ?>">Services</a>
          <a href="<?= h(route("contact")) ?>">Contact</a>
        </p>
        <?php else: ?>
        <p><a class="project-footer-locked-link" href="<?= h(route("maintenance.home")) ?>">Return to maintenance access</a></p>
        <?php endif; ?>
      </div>
    </section>
  </footer>
  <?php endif; ?>

  <script nonce="<?= h(csp_nonce()) ?>" src="<?= h(asset("vendor/fnlla-runtime/assets/js/fnlla-runtime.js")) ?>"></script>
</body>
</html>
