<?php

declare(strict_types=1);
?>
<section class="section starter-hero">
  <div class="container">
    <div class="starter-hero-grid">
      <div class="starter-hero-copy">
        <p class="starter-kicker">FNLLA framework</p>
        <h1 class="starter-hero-title">An AI-ready framework for operated web products.</h1>
        <p class="starter-hero-text">
          Build public pages, private workflows and client-centred delivery with a Developer Operations Panel, local runtime AI, preview, maintenance and release checks already in the stack.
        </p>
        <div class="starter-hero-actions" aria-label="Primary actions">
          <a class="btn btn-primary" href="<?= h(route("contact")) ?>">Start the contact flow</a>
          <a class="btn btn-outline" href="<?= h(route("services")) ?>">View starter services</a>
        </div>
      </div>
      <figure class="starter-hero-media">
        <div class="starter-hero-screen" aria-hidden="true">
          <span></span>
          <span></span>
          <span></span>
        </div>
      </figure>
    </div>
    <div class="starter-stat-strip" aria-label="Starter delivery signals">
      <?php foreach ($heroStats as $stat): ?>
      <article class="starter-stat">
        <strong><?= h($stat["value"]) ?></strong>
        <span><?= h($stat["label"]) ?></span>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <section class="process-section" aria-label="Starter delivery approach">
      <div class="section-header mb-0">
        <p class="process-kicker">How teams use it</p>
      </div>
      <div class="process-grid">
        <?php foreach ($proofPoints as $step): ?>
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
    <section class="feature-section" aria-label="Starter tracks">
      <div class="section-header mb-0">
        <p class="feature-kicker">Why the shape matters</p>
      </div>
      <div class="grid grid-3 gap-md">
        <?php foreach ($serviceTracks as $principle): ?>
        <article class="feature-card">
          <h3 class="content-title"><?= h($principle["title"]) ?></h3>
          <p class="content-text"><?= h($principle["text"]) ?></p>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
  </div>
</section>
