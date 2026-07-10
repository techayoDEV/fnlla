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
<section class="section pt-1">
  <div class="container site-page-stack">
    <section class="hero hero-background" aria-label="FNLLA PHP landing hero">
      <div class="grid gap-md hero-copy hero-background-copy">
        <div class="d-flex flex-wrap items-center gap-md">
          <span class="tag">FNLLA PHP starter</span>
          <span class="badge">Project base</span>
          <span class="badge">Server-rendered by default</span>
        </div>
        <h1 class="hero-title">Build the real web project by modifying the starter itself, not by replacing it with a second front beside the framework.</h1>
        <p class="hero-text">FNLLA PHP now treats the shipped public starter as the beginning of the downstream application. Maintenance, health and CLI stay linked around it as framework capabilities instead of competing with the public project surface.</p>
        <ul class="hero-proof-list">
          <li>Use the starter routes, views and assets as the real basis of the project and replace them deliberately with product-specific content.</li>
          <li>Keep the UI contract local under <code>public/vendor/fnlla-web/</code> without third-party CDNs or a second design system.</li>
          <li>Leave framework checks, health review and update tooling attached, but outside the core public information architecture.</li>
        </ul>
        <div class="hero-actions">
          <a class="btn btn-primary btn-xl" href="<?= h(route("project.launch")) ?>">Open the project launch guide</a>
          <a class="btn btn-outline" href="<?= h(route("contact")) ?>">Open the working form flow</a>
          <?php if (!empty($showDocsWorkspace)): ?>
          <a class="btn btn-outline" href="<?= h(route("docs.home")) ?>">Read maintainer docs</a>
          <?php endif; ?>
        </div>
        <div class="hero-background-meta">
          <?php foreach ($foundationCards as $foundationCard): ?>
          <article class="hero-background-chip">
            <span class="badge"><?= h($foundationCard["title"]) ?></span>
            <p class="content-text mb-0"><?= h($foundationCard["text"]) ?></p>
          </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  </div>
</section>

<?php if (!empty($showDocsWorkspace)): ?>
<section class="section">
  <div class="container">
    <div class="grid grid-2 gap-md">
      <article class="card site-card-muted">
        <span class="tag">Maintainer workspace</span>
        <h2 class="card-title mt-3">The framework repo now mirrors the shipped starter model, but it is still the upstream source of truth.</h2>
        <p class="card-text">Use the running starter to inspect the actual project base, then use the local docs to understand export workflow, framework boundaries and the maintainer-only parts that should not leak into downstream application repos.</p>
        <div class="grid gap-2">
          <p class="mb-0"><code>techayoDEV/fnlla</code> still owns the shared runtime, docs and framework update contract.</p>
          <p class="mb-0"><code>php fnlla make:project ../my-app "My App"</code> exports this same starter into the real downstream repository.</p>
        </div>
        <div class="d-flex flex-wrap gap-md mt-3">
          <a class="btn btn-primary" href="<?= h(route("docs.page", ["page" => "starting-a-new-project.html"])) ?>">See the starter workflow</a>
          <a class="btn btn-outline" href="<?= h(route("docs.page", ["page" => "project-scripts-reference.html"])) ?>">Review project scripts</a>
        </div>
      </article>

      <div class="grid gap-md">
        <article class="feature-card">
          <p class="feature-kicker">Documentation</p>
          <h2 class="content-title">Overview</h2>
          <p class="content-text">Read the framework contract, repository map and supported stack assumptions without leaving the running local app.</p>
          <a class="btn btn-ghost btn-sm" href="<?= h(route("docs.home")) ?>">Open overview</a>
        </article>
        <article class="feature-card">
          <p class="feature-kicker">Workflow</p>
          <h2 class="content-title">Starting a new project</h2>
          <p class="content-text">Follow the maintained export workflow instead of building real client work directly inside this repository.</p>
          <a class="btn btn-ghost btn-sm" href="<?= h(route("docs.page", ["page" => "starting-a-new-project.html"])) ?>">Open guide</a>
        </article>
        <article class="feature-card">
          <p class="feature-kicker">Implementation</p>
          <h2 class="content-title">Building with FNLLA PHP</h2>
          <p class="content-text">Jump into the long-form implementation guide for routes, controllers, views, forms, MySQL and auth.</p>
          <a class="btn btn-ghost btn-sm" href="<?= h(route("docs.page", ["page" => "building-with-fnlla-php.html"])) ?>">Open guide</a>
        </article>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="container">
    <section class="stats-section" aria-label="FNLLA PHP summary stats">
      <div class="section-header mb-0">
        <h2 class="section-title">The starter stays compact, but it now behaves like the real beginning of the application.</h2>
        <p class="section-text">The goal is not a second framework showcase. The goal is a clean public base that downstream teams can own immediately while keeping framework capabilities close and explicit.</p>
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

<section class="section">
  <div class="container">
    <section class="feature-section" aria-label="Landing proof section">
      <div class="section-header mb-0">
        <p class="feature-kicker">Starter ownership</p>
        <h2 class="section-title">Downstream teams should feel like they are editing the app, not orbiting around a framework demo.</h2>
        <p class="section-text">That means the shipped surface should already resemble a project skeleton worth keeping, while framework-only concerns stay linked and explicit around it.</p>
      </div>
      <div class="feature-grid">
        <div class="grid grid-3 gap-md">
          <?php foreach ($foundationCards as $proofCard): ?>
          <article class="feature-card">
            <h3 class="content-title"><?= h($proofCard["title"]) ?></h3>
            <p class="content-text"><?= h($proofCard["text"]) ?></p>
          </article>
          <?php endforeach; ?>
        </div>
        <aside class="feature-section-aside">
          <h3 class="content-title">Best fit</h3>
          <ul class="feature-list">
            <li>service websites that need a real application base from day one</li>
            <li>authenticated portals and internal tools that grow from one working starter shell</li>
            <li>teams that want maintainable server-rendered delivery without a front-end build dependency</li>
            <li>projects that want operator routes available without putting them in the public navigation core</li>
          </ul>
        </aside>
      </div>
    </section>
  </div>
</section>

<section class="section">
  <div class="container">
    <section class="process-section" aria-label="Starter workflow">
      <div class="section-header mb-0">
        <p class="process-kicker">Launch tracks</p>
        <h2 class="section-title">The first project pass should turn the starter into the product, not replace it with a parallel surface.</h2>
        <p class="section-text">These tracks are the intended shape of that work across public content, application logic, operations and the upstream/downstream boundary.</p>
      </div>
      <div class="process-grid">
        <?php foreach ($launchTracks as $step): ?>
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

<section class="section">
  <div class="container">
    <section class="cta-section" aria-label="Landing call to action">
      <div class="cta-grid">
        <div class="grid gap-md cta-copy">
          <div class="d-flex flex-wrap items-center gap-md">
            <span class="tag">Next step</span>
            <span class="badge">Project-owned starter</span>
          </div>
          <h2 class="content-title">Start from this shell, then replace it with the real product flow for the downstream application.</h2>
          <p class="content-text">The healthiest next move is not building beside the starter. It is exporting the starter, reshaping its routes, copy and views into the actual product, and keeping framework work attached through maintenance and validation.</p>
          <ul class="contact-list">
            <?php foreach ($launchChecklist as $checkItem): ?>
            <li><?= h($checkItem) ?></li>
            <?php endforeach; ?>
          </ul>
          <div class="d-flex flex-wrap gap-md">
            <a class="btn btn-primary btn-xl" href="<?= h(route("project.launch")) ?>">Open project launch</a>
            <a class="btn btn-outline" href="<?= h(route("maintenance.framework_update")) ?>">Open framework updates</a>
          </div>
          <div class="cta-inline-notes">
            <p class="help-text mb-0">Use <code>make:project</code> when you want the same starter in a real downstream repo.</p>
            <p class="help-text mb-0">Built to stay inside the supported FNLLA Web contract while leaving operator tooling linked rather than embedded.</p>
          </div>
        </div>
        <div class="grid grid-2 gap-md cta-proof-grid">
          <article class="cta-proof">
            <p class="cta-proof-title">Public base</p>
            <p class="cta-proof-text">The starter is now the real application base teams extend, not a framework showcase they build around.</p>
          </article>
          <article class="cta-proof">
            <p class="cta-proof-title">Export parity</p>
            <p class="cta-proof-text">`make:project` exports the same starter model that the maintainer repo now serves locally.</p>
          </article>
          <article class="cta-proof">
            <p class="cta-proof-title">Operator separation</p>
            <p class="cta-proof-text">Maintenance and health stay available as linked surfaces without taking over the public navigation core.</p>
          </article>
          <article class="cta-proof">
            <p class="cta-proof-title">Scaling path</p>
            <p class="cta-proof-text">Routes, auth, migrations, queueing and scheduling still let the starter grow into larger application work without pivoting stacks.</p>
          </article>
        </div>
        <div class="cta-support">
          <div class="hero-inline-fact">
            <span class="badge">Public routes</span>
            <p class="content-text mb-0">Home, project launch and contact now model a project-facing starter shell instead of framework marketing pages.</p>
          </div>
          <div class="hero-inline-fact">
            <span class="badge">Framework capability</span>
            <p class="content-text mb-0">Auth, authorization and protected areas remain available in the framework even when the starter stays focused on the public application base.</p>
          </div>
          <div class="hero-inline-fact">
            <span class="badge">Operational check</span>
            <p class="content-text mb-0">Use maintenance, health and validation scripts whenever the starter is being reshaped into a real application.</p>
          </div>
        </div>
      </div>
    </section>
  </div>
</section>
