<?php

declare(strict_types=1);
?>
<section class="section pt-1">
  <div class="container">
    <section class="hero hero-compact" aria-label="Services starter introduction">
      <div class="grid gap-md hero-copy">
        <div class="d-flex flex-wrap items-center gap-md">
          <span class="tag">Services page</span>
          <span class="badge">Starter section</span>
          <span class="badge">Replace with real offer</span>
        </div>
        <h1 class="hero-title">The services page is where the starter begins to look like the real product or service structure.</h1>
        <p class="hero-text">Whether the downstream project becomes a services website, a product surface or an authenticated portal, this page should evolve into a clear explanation of what is actually offered.</p>
        <div class="hero-actions">
          <a class="btn btn-primary" href="<?= h(route("contact")) ?>">Start a conversation</a>
          <a class="btn btn-outline" href="<?= h(route("about")) ?>">Read about the starter</a>
        </div>
      </div>
    </section>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="grid grid-3 gap-md">
      <?php foreach ($serviceCards as $serviceCard): ?>
      <article class="feature-card">
        <h2 class="content-title"><?= h($serviceCard["title"]) ?></h2>
        <p class="content-text"><?= h($serviceCard["text"]) ?></p>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <section class="process-section" aria-label="Service page delivery steps">
      <div class="section-header mb-0">
        <p class="process-kicker">How to grow it</p>
        <h2 class="section-title">Keep the page shape simple while the real service map, modules or workflows are still being defined.</h2>
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
    <section class="cta-section" aria-label="Services page call to action">
      <div class="cta-grid">
        <div class="grid gap-md cta-copy">
          <div class="d-flex flex-wrap items-center gap-md">
            <span class="tag">Starter rule</span>
            <span class="badge">One page map</span>
          </div>
          <h2 class="content-title">Expand this page by refining the offer itself, not by inventing a separate presentation layer beside the starter.</h2>
          <p class="content-text">When new content is needed, add sections, cards and calls to action here so the services page remains part of one coherent application skeleton.</p>
        </div>
      </div>
    </section>
  </div>
</section>
