<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP VIEW TEMPLATE
File: views\pages\platform.php
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
    <section class="hero hero-split" aria-label="Platform overview hero">
      <div class="grid gap-md hero-copy">
        <div class="d-flex flex-wrap items-center gap-md">
          <span class="tag">Platform surface</span>
          <span class="badge">Routing</span>
          <span class="badge">Auth</span>
          <span class="badge">Validation</span>
        </div>
        <h1 class="hero-title">A lean application platform with enough structure for real delivery, not just demo pages.</h1>
        <p class="hero-text">FNLLA PHP intentionally keeps the stack more traceable than heavyweight ecosystems while still shipping the runtime, auth, data and release foundations that practical work needs.</p>
        <ul class="hero-proof-list">
          <li>Named routes, middleware aliases and protected areas are already part of the maintained surface.</li>
          <li>Sessions, CSRF, validation, redirects and flash messaging already support real form workflows.</li>
          <li>Runtime sync and metadata validation keep framework and vendored UI state aligned.</li>
        </ul>
        <div class="hero-actions">
          <a class="btn btn-primary" href="<?= h(route("contact")) ?>">See the delivery flow</a>
          <a class="btn btn-outline" href="<?= h(route("about")) ?>">Read the framework model</a>
        </div>
      </div>
      <aside class="hero-panel" aria-label="Platform snapshot">
        <div class="hero-panel-intro">
          <span class="badge">Snapshot</span>
          <h2 class="hero-panel-title">What ships in the box</h2>
          <p class="hero-panel-text">The starter is small enough to inspect directly, but broad enough to support multi-page sites, authenticated areas and operational commands without framework pivots.</p>
        </div>
        <div class="hero-metric-list">
          <div class="hero-metric">
            <p class="hero-metric-value">HTTP</p>
            <p class="hero-metric-label">front controller, routing and middleware pipeline</p>
          </div>
          <div class="hero-metric">
            <p class="hero-metric-value">UI</p>
            <p class="hero-metric-label">vendored FNLLA Web runtime for layout and interaction</p>
          </div>
          <div class="hero-metric">
            <p class="hero-metric-value">Ops</p>
            <p class="hero-metric-label">sync, validation, migrations, queueing and scheduling commands</p>
          </div>
        </div>
      </aside>
    </section>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="tabs" data-fnlla-tabs>
      <div class="tab-list" data-fnlla-tab-list aria-label="Platform capabilities">
        <?php foreach ($platformTabs as $index => $tab): ?>
        <?php $isActive = $index === 0; ?>
        <button class="tab-button" id="platform-tab-<?= $index + 1 ?>" type="button" data-fnlla-tab aria-selected="<?= $isActive ? "true" : "false" ?>" aria-controls="platform-panel-<?= $index + 1 ?>"><?= h($tab["label"]) ?></button>
        <?php endforeach; ?>
      </div>

      <?php foreach ($platformTabs as $index => $tab): ?>
      <section class="tab-panel" id="platform-panel-<?= $index + 1 ?>" aria-labelledby="platform-tab-<?= $index + 1 ?>">
        <h2 class="content-title"><?= h($tab["title"]) ?></h2>
        <p class="content-text"><?= h($tab["text"]) ?></p>
      </section>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <section class="feature-section" aria-label="Platform capabilities">
      <div class="section-header mb-0">
        <p class="feature-kicker">Core capabilities</p>
        <h2 class="section-title">The platform grows by adding practical layers, not by obscuring the basics.</h2>
        <p class="section-text">Each capability stays close enough to the repository surface that a delivery team can inspect the implementation instead of treating the framework as an untouchable black box.</p>
      </div>
      <div class="feature-grid">
        <div class="grid grid-3 gap-md">
          <?php foreach ($capabilityCards as $capabilityCard): ?>
          <article class="feature-card">
            <h3 class="content-title"><?= h($capabilityCard["title"]) ?></h3>
            <p class="content-text"><?= h($capabilityCard["text"]) ?></p>
          </article>
          <?php endforeach; ?>
        </div>
        <aside class="feature-section-aside">
          <h3 class="content-title">Repository map</h3>
          <ul class="feature-list">
            <li><code>routes/</code> keeps HTTP and console entry definitions readable</li>
            <li><code>src/Controllers/</code> owns page and form behavior</li>
            <li><code>views/</code> renders the shared FNLLA Web shell and page templates</li>
            <li><code>scripts/</code> keeps validation and sync tasks close to the project</li>
          </ul>
        </aside>
      </div>
    </section>
  </div>
</section>

<section class="section">
  <div class="container">
    <section class="stats-section" aria-label="Platform stats">
      <div class="section-header mb-0">
        <h2 class="section-title">Official stack assumptions stay explicit</h2>
        <p class="section-text">The goal is not to support every possible permutation. The goal is to make the supported path reliable, inspectable and easy to maintain.</p>
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
    <section class="faq-section" aria-label="Platform FAQ">
      <div class="faq-layout">
        <div class="section-header mb-0">
          <p class="feature-kicker">Platform FAQ</p>
          <h2 class="section-title">Clarifying the trade-offs up front makes the starter easier to use well.</h2>
          <p class="section-text">The platform works best when teams understand what it is optimized for and what boundaries are intentional rather than accidental.</p>
        </div>
        <div class="accordion" data-fnlla-accordion data-fnlla-accordion-single>
          <?php foreach ($platformFaqs as $index => $faqItem): ?>
          <?php $isOpen = $index === 0; ?>
          <div class="accordion-item<?= $isOpen ? " is-open" : "" ?>">
            <button class="accordion-button" id="platform-faq-trigger-<?= $index + 1 ?>" type="button" data-fnlla-accordion-button aria-expanded="<?= $isOpen ? "true" : "false" ?>" aria-controls="platform-faq-panel-<?= $index + 1 ?>">
              <?= h($faqItem["question"]) ?>
            </button>
            <div class="accordion-panel" id="platform-faq-panel-<?= $index + 1 ?>" role="region" aria-labelledby="platform-faq-trigger-<?= $index + 1 ?>">
              <p class="content-text"><?= h($faqItem["answer"]) ?></p>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  </div>
</section>
