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
- Defines the shared delivery shell for server-rendered pages built on FNLLA Runtime.
*/

$pageStatus = flash("status");
$currentPath = current_path();
$hasDocumentationWorkspace = has_local_docs_workspace();
$isDocsPath = $currentPath === "/docs" || str_starts_with($currentPath, "/docs/");
$maintenanceAccess = maintenance_access();
$developerAccess = developer_access();
$isMaintenanceLocked = $maintenanceAccess->enabled() && !$maintenanceAccess->isUnlocked();
$hasDeveloperPanelRoute = app(\Fnlla\Php\Routing\Router::class)->routeByName("developer.panel") !== null;
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
<body data-fnlla-theme="default">
  <header class="starter-header">
    <section class="section starter-header-section">
      <div class="container">
        <nav class="navbar" aria-label="Primary navigation">
          <a class="navbar-brand starter-brand" href="<?= h(route("home")) ?>">
            <span class="starter-brand-name"><?= h((string) config("app.name")) ?></span>
          </a>
          <button class="btn btn-outline btn-sm navbar-toggle" type="button" data-fnlla-nav-toggle aria-controls="primary-navigation-panel" aria-expanded="false" aria-label="Toggle navigation menu">Menu</button>
          <div class="navbar-panel" id="primary-navigation-panel">
            <ul class="navbar-menu">
              <?php if ($publicNavigationAvailable): ?>
              <li><a class="starter-nav-link" href="<?= h(route("home")) ?>" <?= $currentPath === "/" ? 'aria-current="page"' : "" ?>>Home</a></li>
              <li><a class="starter-nav-link" href="<?= h(route("about")) ?>" <?= $currentPath === "/about" ? 'aria-current="page"' : "" ?>>About</a></li>
              <li><a class="starter-nav-link" href="<?= h(route("services")) ?>" <?= $currentPath === "/services" ? 'aria-current="page"' : "" ?>>Services</a></li>
              <?php else: ?>
              <li><span class="starter-nav-link" aria-current="page">Maintenance access required</span></li>
              <?php endif; ?>
            </ul>
            <div class="navbar-actions starter-navbar-actions">
              <?php if ($developerSessionActive): ?>
              <a class="btn btn-outline btn-sm starter-dropdown-toggle" href="<?= h(route("developer.panel")) ?>" <?= $currentPath === $developerAccess->path() . "/panel" ? 'aria-current="page"' : "" ?>>
                DEV OPERATIONS
                <span class="starter-ops-badge">Active</span>
              </a>
              <?php endif; ?>
              <?php if ($hasDocumentationWorkspace && $publicNavigationAvailable): ?>
              <a class="btn btn-ghost btn-sm starter-nav-link" href="<?= h(route("docs.home")) ?>" <?= $isDocsPath ? 'aria-current="page"' : "" ?>>Docs</a>
              <?php endif; ?>
            </div>
          </div>
        </nav>
      </div>
    </section>
  </header>

  <?php if (is_array($pageStatus) && isset($pageStatus["title"], $pageStatus["text"])): ?>
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

  <footer class="starter-footer">
    <section class="section">
      <div class="container">
        <p><strong class="starter-footer-brand"><?= h((string) config("app.name")) ?></strong></p>
        <p>This starter is the beginning of the real application.</p>
        <?php if ($publicNavigationAvailable): ?>
        <p class="starter-footer-links">
          <a href="<?= h(route("home")) ?>">Home</a>
          <a href="<?= h(route("about")) ?>">About</a>
          <a href="<?= h(route("services")) ?>">Services</a>
        </p>
        <?php else: ?>
        <p><a class="starter-footer-locked-link" href="<?= h(route("maintenance.home")) ?>">Return to maintenance access</a></p>
        <?php endif; ?>
      </div>
    </section>
  </footer>

  <script src="<?= h(asset("vendor/fnlla-runtime/assets/js/fnlla-runtime.js")) ?>"></script>
</body>
</html>
