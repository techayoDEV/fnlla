<?php

declare(strict_types=1);
?>
<section class="section pt-1">
  <div class="container site-page-stack">
    <section class="hero hero-compact" aria-label="Maintenance hub introduction">
      <div class="grid gap-md hero-copy">
        <div class="d-flex flex-wrap items-center gap-md">
          <span class="tag">Maintenance</span>
          <span class="badge">Operator hub</span>
          <span class="badge">Not part of public IA</span>
          <span class="badge">Project-safe</span>
        </div>
        <h1 class="hero-title">Operator tools stay available on a dedicated maintenance surface instead of living inside the public project navigation.</h1>
        <p class="hero-text">Use this area for framework upkeep, health review and machine-facing diagnostics while the starter evolves into the real downstream application with its own product content and information architecture.</p>
      </div>
    </section>
  </div>
</section>

<section class="section">
  <div class="container">
    <section class="feature-section" aria-label="Maintenance snapshot">
      <div class="section-header mb-0">
        <p class="feature-kicker">Current operator snapshot</p>
        <h2 class="section-title">The maintenance entry point keeps important signals visible before any framework work starts.</h2>
      </div>
      <div class="grid grid-2 gap-md">
        <?php foreach ($maintenanceHighlights as $highlight): ?>
        <article class="feature-card">
          <p class="feature-kicker"><?= h((string) ($highlight["label"] ?? "Signal")) ?></p>
          <h3 class="content-title mb-0"><?= h((string) ($highlight["value"] ?? "unknown")) ?></h3>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
  </div>
</section>

<section class="section">
  <div class="container">
    <section class="feature-section" aria-label="Maintenance tools">
      <div class="section-header mb-0">
        <p class="feature-kicker">Operator destinations</p>
        <h2 class="section-title">Choose the operational view you need without exposing these routes as the main public product surface.</h2>
      </div>
      <div class="grid gap-md">
        <?php foreach ($maintenanceCards as $card): ?>
        <article class="feature-card">
          <h3 class="content-title"><?= h((string) ($card["title"] ?? "Tool")) ?></h3>
          <p class="content-text"><?= h((string) ($card["text"] ?? "")) ?></p>
          <div class="d-flex flex-wrap gap-md">
            <a class="btn btn-outline btn-sm" href="<?= h((string) ($card["href"] ?? route("maintenance.home"))) ?>"><?= h((string) ($card["action"] ?? "Open")) ?></a>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
  </div>
</section>
