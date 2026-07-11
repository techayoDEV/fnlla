<?php

declare(strict_types=1);
?>
<section class="section pt-1">
  <div class="container">
    <section class="hero hero-compact" aria-label="About starter introduction">
      <div class="grid gap-md hero-copy">
        <div class="d-flex flex-wrap items-center gap-md">
          <span class="tag">About page</span>
          <span class="badge">Starter section</span>
          <span class="badge">Project-owned content</span>
        </div>
        <h1 class="hero-title">This page is part of the starter foundation and should become the real about surface of the application.</h1>
        <p class="hero-text">Use it to explain the team, service model, product point of view or delivery story that belongs to the downstream project. The goal is not to create a second application beside this page, but to reshape this page into the real one.</p>
        <div class="hero-actions">
          <a class="btn btn-primary" href="<?= h(route("services")) ?>">View services</a>
          <a class="btn btn-outline" href="<?= h(route("contact")) ?>">Open contact</a>
        </div>
      </div>
    </section>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="grid grid-3 gap-md">
      <?php foreach ($aboutPillars as $pillar): ?>
      <article class="feature-card">
        <h2 class="content-title"><?= h($pillar["title"]) ?></h2>
        <p class="content-text"><?= h($pillar["text"]) ?></p>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <section class="process-section" aria-label="About page development path">
      <div class="section-header mb-0">
        <p class="process-kicker">How to extend it</p>
        <h2 class="section-title">Grow this page through clear content sections rather than replacing the starter with parallel templates.</h2>
      </div>
      <div class="process-grid">
        <?php foreach ($aboutSteps as $step): ?>
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
    <section class="cta-section" aria-label="About page call to action">
      <div class="cta-grid">
        <div class="grid gap-md cta-copy">
          <div class="d-flex flex-wrap items-center gap-md">
            <span class="tag">Starter rule</span>
            <span class="badge">Edit in place</span>
          </div>
          <h2 class="content-title">Treat this page as real project code from the first day of delivery.</h2>
          <p class="content-text">That means replacing the placeholder story with the actual one, then adding more sections only when the project truly needs them.</p>
        </div>
      </div>
    </section>
  </div>
</section>
