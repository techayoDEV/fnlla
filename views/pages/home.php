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

$hasDocumentationWorkspace = has_local_docs_workspace();
?>
<section class="section pt-1">
  <div class="container site-page-stack">
    <section class="hero hero-background" aria-label="FNLLA PHP landing hero">
      <div class="grid gap-md hero-copy hero-background-copy">
        <div class="d-flex flex-wrap items-center gap-md">
          <span class="tag">FNLLA PHP starter</span>
          <span class="badge">FNLLA Web only</span>
          <span class="badge">Server-rendered by default</span>
        </div>
        <h1 class="hero-title">Professional PHP delivery with a smaller, more legible runtime surface.</h1>
        <p class="hero-text">FNLLA PHP gives teams a focused application core for routes, controllers, views, forms and release hygiene while FNLLA Web handles the reusable section, component and interaction system.</p>
        <ul class="hero-proof-list">
          <li>Start from a real exported project instead of building client work inside the framework repo.</li>
          <li>Keep the UI contract local under <code>public/vendor/fnlla-web/</code> without third-party CDNs.</li>
          <li>Ship with explicit tests, lint, FNLLA Web validation and version metadata checks already in place.</li>
        </ul>
        <div class="hero-actions">
          <a class="btn btn-primary btn-xl" href="<?= h(route("platform")) ?>">Explore the platform</a>
          <?php if ($hasDocumentationWorkspace): ?>
          <a class="btn btn-outline" href="<?= h(route("docs.home")) ?>">Read the docs</a>
          <?php endif; ?>
          <a class="btn btn-outline" href="<?= h(route("contact")) ?>">Open the contact flow</a>
        </div>
        <div class="hero-background-meta">
          <article class="hero-background-chip">
            <span class="badge">Framework boundary</span>
            <p class="content-text mb-0">`fnlla/php` stays the maintainer workspace; each exported starter becomes the real downstream application repo.</p>
          </article>
          <article class="hero-background-chip">
            <span class="badge">Delivery model</span>
            <p class="content-text mb-0">Plain PHP request flow, one shared FNLLA Web shell, then only the application-specific logic your project actually needs.</p>
          </article>
          <article class="hero-background-chip">
            <span class="badge">Operational rule</span>
            <p class="content-text mb-0">Runtime sync, release metadata and project validation remain visible commands instead of hidden packaging side effects.</p>
          </article>
        </div>
      </div>
    </section>
  </div>
</section>

<?php if ($hasDocumentationWorkspace): ?>
<section class="section">
  <div class="container">
    <div class="grid grid-2 gap-md">
      <article class="card site-card-muted">
        <span class="tag">Maintainer workspace</span>
        <h2 class="card-title mt-3">This browser surface is the framework repo, not the downstream project you normally ship.</h2>
        <p class="card-text">Use the running demo to inspect the request flow and shared UI contract. Use the local docs to understand the export workflow, delivery boundaries and the files a real project should change first.</p>
        <div class="grid gap-2">
          <p class="mb-0"><code>fnlla/php</code> stays the framework source of truth and docs workspace.</p>
          <p class="mb-0"><code>php fnlla make:project ../my-app "My App"</code> creates the real downstream repository.</p>
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
        <h2 class="section-title">A starter that stays compact, but no longer feels like a toy</h2>
        <p class="section-text">The platform is intentionally smaller than the largest PHP frameworks, but it already covers the concrete delivery surfaces that teams actually need for websites, portals and internal tools.</p>
      </div>
      <div class="stats-grid">
        <?php foreach ($platformStats as $stat): ?>
        <article class="stat-card">
          <p class="stat-value"><?= h($stat["value"]) ?></p>
          <p class="stat-label"><?= h($stat["label"]) ?></p>
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
        <p class="feature-kicker">Why teams can move faster here</p>
        <h2 class="section-title">The starter is designed to remove repeated delivery friction, not to impress with hidden abstraction.</h2>
        <p class="section-text">What matters is not how many layers the framework can hide. What matters is how quickly a team can understand the project, change it safely and release it with confidence.</p>
      </div>
      <div class="feature-grid">
        <div class="grid grid-3 gap-md">
          <?php foreach ($proofCards as $proofCard): ?>
          <article class="feature-card">
            <h3 class="content-title"><?= h($proofCard["title"]) ?></h3>
            <p class="content-text"><?= h($proofCard["text"]) ?></p>
          </article>
          <?php endforeach; ?>
        </div>
        <aside class="feature-section-aside">
          <h3 class="content-title">Best fit</h3>
          <ul class="feature-list">
            <li>service websites that need more structure than a static site</li>
            <li>authenticated client or staff portals built with plain PHP</li>
            <li>internal tools that benefit from a local UI runtime and explicit request flow</li>
            <li>teams that want maintainable delivery without a front-end build dependency</li>
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
        <p class="process-kicker">Suggested path</p>
        <h2 class="section-title">A healthier way to start a real FNLLA PHP project</h2>
        <p class="section-text">The framework repo and the downstream project are no longer treated as the same thing. That separation makes release history, ownership and maintenance much easier to keep honest.</p>
      </div>
      <div class="process-grid">
        <?php foreach ($workflowSteps as $step): ?>
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
    <section class="faq-section" aria-label="Starter FAQ">
      <div class="faq-layout">
        <div class="section-header mb-0">
          <p class="feature-kicker">Starter FAQ</p>
          <h2 class="section-title">The practical questions teams usually ask before committing to a smaller framework base</h2>
          <p class="section-text">This starter is meant to be approachable without being flimsy. These answers explain the intended working model more directly than a generic marketing promise would.</p>
        </div>
        <div class="accordion" data-fnlla-accordion data-fnlla-accordion-single>
          <?php foreach ($faqItems as $index => $faqItem): ?>
          <?php $isOpen = $index === 0; ?>
          <div class="accordion-item<?= $isOpen ? " is-open" : "" ?>">
            <button class="accordion-button" id="home-faq-trigger-<?= $index + 1 ?>" type="button" data-fnlla-accordion-button aria-expanded="<?= $isOpen ? "true" : "false" ?>" aria-controls="home-faq-panel-<?= $index + 1 ?>">
              <?= h($faqItem["question"]) ?>
            </button>
            <div class="accordion-panel" id="home-faq-panel-<?= $index + 1 ?>" role="region" aria-labelledby="home-faq-trigger-<?= $index + 1 ?>">
              <p class="content-text"><?= h($faqItem["answer"]) ?></p>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
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
            <span class="badge">Delivery-ready starter</span>
          </div>
          <h2 class="content-title">Use the current starter as the base, then replace the demo with the real product flow for your project.</h2>
          <p class="content-text">The best next move is usually not another framework evaluation. It is defining the project map, exporting a clean starter and validating the actual route, form and runtime boundary you plan to ship.</p>
          <ul class="hero-proof-list">
            <li>Start with <code>php fnlla make:project</code> so the application repo has its own lifecycle.</li>
            <li>Keep FNLLA Web synced locally and validate the contract before release work.</li>
            <li>Use the current demo pages only as a reference pattern, not as final product content.</li>
          </ul>
          <div class="d-flex flex-wrap gap-md">
            <a class="btn btn-primary btn-xl" href="<?= h(route("contact")) ?>">Review the form flow</a>
            <a class="btn btn-outline" href="<?= h(route("about")) ?>">Read the framework model</a>
          </div>
          <div class="cta-inline-notes">
            <p class="help-text mb-0">Good starting point for portals, service sites and internal operational surfaces.</p>
            <p class="help-text mb-0">Built to stay inside the supported FNLLA Web contract.</p>
          </div>
        </div>
        <div class="grid grid-2 gap-md cta-proof-grid">
          <article class="cta-proof">
            <p class="cta-proof-title">Project export</p>
            <p class="cta-proof-text">The starter now exports a cleaner downstream project surface instead of copying the whole maintainer docs workspace.</p>
          </article>
          <article class="cta-proof">
            <p class="cta-proof-title">Validation</p>
            <p class="cta-proof-text">Tests, lint, FNLLA Web checks and version checks already exist as first-party project commands.</p>
          </article>
          <article class="cta-proof">
            <p class="cta-proof-title">UI contract</p>
            <p class="cta-proof-text">Shared layout, navigation, overlays, forms and landing sections are all powered by the vendored FNLLA Web runtime.</p>
          </article>
          <article class="cta-proof">
            <p class="cta-proof-title">Scaling path</p>
            <p class="cta-proof-text">Routes, auth, migrations, queueing and scheduling allow the starter to grow beyond a brochure site without pivoting frameworks immediately.</p>
          </article>
        </div>
        <div class="cta-support">
          <div class="hero-inline-fact">
            <span class="badge">Public routes</span>
            <p class="content-text mb-0">Home, Platform, About and Contact already model a multi-page landing shell.</p>
          </div>
          <div class="hero-inline-fact">
            <span class="badge">Framework capability</span>
            <p class="content-text mb-0">Auth, authorization and protected areas remain available in the framework even when the starter surface no longer presents demo sign-in pages.</p>
          </div>
          <div class="hero-inline-fact">
            <span class="badge">Operational check</span>
            <p class="content-text mb-0">Use the health endpoint and validation scripts whenever the starter is reshaped into a real application.</p>
          </div>
        </div>
      </div>
    </section>
  </div>
</section>
