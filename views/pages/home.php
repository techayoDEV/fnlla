<?php

declare(strict_types=1);
?>
<section class="section starter-hero">
  <div class="container">
    <div class="starter-hero-copy">
      <p class="starter-kicker">Welcome</p>
      <h1 class="starter-hero-title"><?= h((string) config("app.name")) ?></h1>
      <p class="starter-hero-text">
        <?= h(trim((string) config("app.tagline", "")) ?: "Explore the project and get in touch.") ?>
      </p>
      <div class="starter-hero-actions" aria-label="Primary actions">
        <a class="btn btn-primary" href="<?= h(route("contact")) ?>">Contact us</a>
        <a class="btn btn-outline" href="<?= h(route("about")) ?>">About</a>
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
