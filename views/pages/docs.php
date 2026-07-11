<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA VIEW TEMPLATE
File: views\pages\docs.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Defines the app-native landing page that connects the running demo with the local docs workspace.
*/
?>
<section class="section pt-1">
  <div class="container site-page-stack">
    <section class="hero hero-compact" aria-label="Documentation landing page">
      <div class="grid gap-md hero-copy">
        <div class="d-flex flex-wrap items-center gap-md">
          <span class="tag">Documentation hub</span>
          <span class="badge">Maintainer workspace</span>
          <span class="badge">Local docs</span>
        </div>
        <h1 class="hero-title">Use this page to bridge the running demo and the deeper framework docs without pretending they are the same thing.</h1>
        <p class="hero-text">The application surface shows how the starter behaves in a browser. The docs explain the repository contract, export workflow and how the built-in runtime should be used behind that surface.</p>
        <ul class="hero-proof-list">
          <li><code>/docs</code> is the app-native entry point for the local documentation workspace.</li>
          <li>The HTML docs remain the source for long-form reference and guide reading.</li>
          <li>Exported downstream projects do not inherit this maintainer docs workspace.</li>
        </ul>
        <div class="hero-actions">
          <a class="btn btn-primary" href="<?= h(route("docs.page", ["page" => "index.html"])) ?>">Open docs overview</a>
          <a class="btn btn-outline" href="<?= h(route("docs.page", ["page" => "starting-a-new-project.html"])) ?>">Read the starter workflow</a>
          <a class="btn btn-ghost" href="<?= h(route("home")) ?>">Back to starter</a>
        </div>
      </div>
      <div class="hero-inline-facts" aria-label="Documentation hub support facts">
        <div class="hero-inline-fact">
          <span class="badge">Framework repo</span>
          <p class="content-text mb-0">Keep framework behavior, docs and export rules here, where they can evolve together.</p>
        </div>
        <div class="hero-inline-fact">
          <span class="badge">Downstream app</span>
          <p class="content-text mb-0">Use <code>make:project</code> to create the real project repository with only the application-facing surface.</p>
        </div>
        <div class="hero-inline-fact">
          <span class="badge">Current shape</span>
          <p class="content-text mb-0">The guides stay authored in maintainable source files and the runtime simply exposes them locally.</p>
        </div>
      </div>
    </section>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="grid grid-2 gap-md">
      <article class="card site-card-muted">
        <span class="tag">Best starting point</span>
        <h2 class="card-title mt-3">The most important clarification for new teams is still the export boundary.</h2>
        <p class="card-text">If someone opens this repo in a browser and thinks “this must be the app I edit for a new client project”, they will almost certainly mix framework maintenance with delivery work. The docs should stop that confusion early.</p>
        <div class="d-flex flex-wrap gap-md mt-3">
          <a class="btn btn-primary btn-sm" href="<?= h(route("docs.page", ["page" => "starting-a-new-project.html"])) ?>">Start there</a>
          <a class="btn btn-outline btn-sm" href="<?= h(route("docs.page", ["page" => "project-scripts-reference.html"])) ?>">Check project scripts</a>
        </div>
      </article>

      <article class="feature-card">
        <p class="feature-kicker">Why this page exists</p>
        <h2 class="content-title">A friendlier entry point than dropping people straight into generated HTML.</h2>
        <p class="content-text">This page lives inside the same app shell as the demo, so it can explain what the docs are for before you dive into the full reference set. The long-form docs still do the heavy lifting after that handoff.</p>
      </article>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <section class="feature-section" aria-label="Reference documentation">
      <div class="section-header mb-0">
        <p class="feature-kicker">Reference set</p>
        <h2 class="section-title">Use these pages to understand the maintained repository and built-in runtime.</h2>
        <p class="section-text">These are the shorter pages you typically read when mapping the framework surface, release boundary and supported stack assumptions.</p>
      </div>
      <div class="grid grid-3 gap-md">
        <?php foreach ($referenceDocuments as $document): ?>
        <article class="feature-card">
          <p class="help-text mb-2"><?= h($document["kind"]) ?></p>
          <h3 class="content-title"><?= h($document["title"]) ?></h3>
          <p class="content-text"><?= h($document["summary"]) ?></p>
          <a class="btn btn-ghost btn-sm" href="<?= h(route("docs.page", ["page" => $document["file"]])) ?>">Open page</a>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
  </div>
</section>

<section class="section">
  <div class="container">
    <section class="feature-section" aria-label="Guide documentation">
      <div class="section-header mb-0">
        <p class="feature-kicker">Guide set</p>
        <h2 class="section-title">Use these long-form guides when the work stops being exploratory and starts becoming delivery.</h2>
        <p class="section-text">These documents are the ones teams usually return to while exporting a starter, shaping routes and checking which scripts belong in a real downstream application.</p>
      </div>
      <div class="grid grid-3 gap-md">
        <?php foreach ($guideDocuments as $document): ?>
        <article class="feature-card">
          <p class="help-text mb-2"><?= h($document["kind"]) ?></p>
          <h3 class="content-title"><?= h($document["title"]) ?></h3>
          <p class="content-text"><?= h($document["summary"]) ?></p>
          <a class="btn btn-ghost btn-sm" href="<?= h(route("docs.page", ["page" => $document["file"]])) ?>">Open guide</a>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
  </div>
</section>
