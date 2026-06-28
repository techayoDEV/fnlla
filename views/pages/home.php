<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP VIEW TEMPLATE
File: views\pages\home.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Defines a maintained page template for the official FNLLA PHP demonstration surface.
*/
?>
<section class="section">
  <div class="container site-section-stack">
    <section class="hero hero-split" aria-label="FNLLA PHP hero">
      <div class="grid gap-md hero-copy">
        <span class="tag">Server-rendered starter</span>
        <h1 class="hero-title">Build lean PHP apps on top of FNLLA UI without pulling in a full-stack framework.</h1>
        <p class="hero-text">FNLLA PHP gives you a tiny application core for routes, controllers, views and forms while FNLLA UI handles the layout, components and interaction layer.</p>
        <ul class="hero-proof-list">
          <li>No Composer dependency is required for the starter itself.</li>
          <li>Published FNLLA UI assets are bundled locally under <code>public/vendor/fnlla-ui/</code>.</li>
          <li>The demo includes overlays, tabs, validation feedback and a JSON endpoint.</li>
        </ul>
        <div class="hero-actions">
          <a class="btn btn-primary" href="<?= h(url("contact")) ?>">Try the contact flow</a>
          <button class="btn btn-outline" type="button" data-fnlla-modal-open="#architecture-modal" aria-controls="architecture-modal">See the architecture</button>
        </div>
      </div>
      <aside class="hero-panel" aria-label="Runtime snapshot">
        <div class="hero-panel-intro">
          <p class="doc-panel-label">Runtime snapshot</p>
          <h2 class="hero-panel-title">What ships in the box</h2>
          <p class="hero-panel-text">The starter stays narrow enough that one developer can understand the whole request lifecycle in one sitting.</p>
        </div>
        <div class="hero-metric-list">
          <div class="hero-metric">
            <p class="hero-metric-value">5</p>
            <p class="hero-metric-label">core moving parts in the request flow</p>
          </div>
          <div class="hero-metric">
            <p class="hero-metric-value">0</p>
            <p class="hero-metric-label">external UI CDNs or JS framework dependencies</p>
          </div>
          <div class="hero-metric">
            <p class="hero-metric-value">1</p>
            <p class="hero-metric-label">vendored FNLLA UI runtime source of truth</p>
          </div>
        </div>
        <p class="help-text mb-0 site-hero-panel-note">Good default for internal tools, service sites and admin surfaces that benefit from clarity more than novelty.</p>
      </aside>
    </section>

    <div class="grid grid-3 gap-md">
      <?php foreach ($featureCards as $featureCard): ?>
      <article class="card">
        <h2 class="card-title"><?= h($featureCard["title"]) ?></h2>
        <p class="card-text"><?= h($featureCard["text"]) ?></p>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title">Framework capabilities</h2>
      <p class="section-text">These examples are intentionally close to real delivery tasks: route responses, page composition and form-handling feedback.</p>
    </div>

    <div class="tabs" data-fnlla-tabs>
      <div class="tab-list" data-fnlla-tab-list aria-label="Framework capabilities">
        <?php foreach ($runtimeTabs as $index => $tab): ?>
        <?php $isActive = $index === 0; ?>
        <button class="tab-button" id="capability-tab-<?= $index + 1 ?>" type="button" data-fnlla-tab aria-selected="<?= $isActive ? "true" : "false" ?>" aria-controls="capability-panel-<?= $index + 1 ?>"><?= h($tab["label"]) ?></button>
        <?php endforeach; ?>
      </div>

      <?php foreach ($runtimeTabs as $index => $tab): ?>
      <section class="tab-panel" id="capability-panel-<?= $index + 1 ?>" aria-labelledby="capability-tab-<?= $index + 1 ?>">
        <h3 class="content-title"><?= h($tab["title"]) ?></h3>
        <p class="content-text"><?= h($tab["text"]) ?></p>
      </section>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="grid grid-2 gap-md">
      <article class="card">
        <h2 class="card-title">Try a side workspace</h2>
        <p class="card-text">FNLLA UI overlays can stay purely declarative. The starter panel in the global header and the example panel below both run on the published runtime script.</p>
        <div class="d-flex flex-wrap gap-md">
          <button class="btn btn-primary" type="button" data-fnlla-offcanvas-open="#delivery-panel" aria-controls="delivery-panel">Open delivery panel</button>
          <a class="btn btn-outline" href="<?= h(url("api/health")) ?>">Open health endpoint</a>
        </div>
      </article>
      <article class="card">
        <h2 class="card-title">Use local assets only</h2>
        <p class="card-text">The project copies the published FNLLA UI runtime into its own public tree, which keeps the deployment path simple and offline-safe.</p>
        <div class="d-flex flex-wrap gap-md">
          <span class="badge">CSS bundled locally</span>
          <span class="badge">JS bundled locally</span>
          <span class="badge">Icons bundled locally</span>
        </div>
      </article>
    </div>
  </div>
</section>

<div class="modal" id="architecture-modal" data-fnlla-modal role="dialog" aria-modal="true" aria-labelledby="architecture-modal-title" aria-describedby="architecture-modal-description" hidden>
  <div class="modal-content">
    <div class="d-flex justify-between items-center mb-3">
      <h2 class="content-title mb-1" id="architecture-modal-title">Starter architecture</h2>
      <button class="btn btn-ghost btn-sm" type="button" data-fnlla-modal-close data-fnlla-modal-initial-focus>Close</button>
    </div>
    <p class="content-text" id="architecture-modal-description">The request enters through <code>public/index.php</code>, moves into <code>bootstrap/app.php</code>, reaches the router, then a controller, then a plain PHP view rendered inside one shared layout.</p>
  </div>
</div>

<div class="offcanvas offcanvas-end" id="delivery-panel" data-fnlla-offcanvas role="dialog" aria-modal="true" aria-labelledby="delivery-panel-title" aria-describedby="delivery-panel-description" hidden>
  <div class="offcanvas-panel">
    <div class="offcanvas-header">
      <div>
        <h2 class="content-title mb-1" id="delivery-panel-title">Delivery panel</h2>
        <p class="content-text" id="delivery-panel-description">This is a second FNLLA UI overlay on the same page, which demonstrates how the PHP starter can compose interactive surfaces without custom front-end state management.</p>
      </div>
      <button class="btn btn-ghost btn-sm" type="button" data-fnlla-offcanvas-close data-fnlla-offcanvas-initial-focus>Close</button>
    </div>
    <div class="offcanvas-body">
      <section class="offcanvas-section" aria-label="Current implementation steps">
        <p class="offcanvas-kicker">Current implementation steps</p>
        <div class="list-group list-group-nav">
          <div class="list-group-item"><span class="list-group-link">Define routes in <code>routes/web.php</code></span></div>
          <div class="list-group-item"><span class="list-group-link">Add a controller method under <code>src/Controllers/</code></span></div>
          <div class="list-group-item"><span class="list-group-link">Render a PHP template from <code>views/</code></span></div>
        </div>
      </section>
      <div class="d-flex flex-wrap gap-md">
        <a class="btn btn-primary btn-sm" href="<?= h(url("about")) ?>">Read the overview</a>
        <a class="btn btn-outline btn-sm" href="<?= h(url("contact")) ?>">Open the form flow</a>
      </div>
    </div>
  </div>
</div>
