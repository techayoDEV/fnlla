<?php

declare(strict_types=1);
?>
<section class="section starter-hero">
  <div class="container">
    <div class="starter-hero-grid">
      <div class="starter-hero-copy">
        <p class="starter-kicker">Welcome</p>
        <h1 class="starter-hero-title"><?= h((string) config("app.name")) ?></h1>
        <p class="starter-hero-text">
          <?= h(trim((string) config("app.tagline", "")) ?: "Explore the project and get in touch.") ?>
        </p>
        <div class="starter-hero-actions" aria-label="Primary actions">
          <a class="btn btn-primary" href="<?= h(route("contact")) ?>">Contact us</a>
          <a class="btn btn-outline" href="<?= h(route("about")) ?>">About this project</a>
        </div>
      </div>
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
