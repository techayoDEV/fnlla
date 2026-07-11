<?php

declare(strict_types=1);
?>
<section class="section pt-1">
  <div class="container">
    <section class="hero hero-compact" aria-label="Starter home introduction">
      <div class="grid gap-md hero-copy">
        <div class="d-flex flex-wrap items-center gap-md">
          <span class="tag">Starter home</span>
          <span class="badge">Application base</span>
          <span class="badge">Server-rendered by default</span>
        </div>
        <h1 class="hero-title">FNLLA ships a starter skeleton that developers are meant to grow into the real application, not work around.</h1>
        <p class="hero-text">The public pages, layout and form flow already form one coherent base. Replace the placeholder structure with the actual product map, content and workflows instead of building a second public surface beside the starter.</p>
        <div class="hero-actions">
          <a class="btn btn-primary btn-xl" href="<?= h(route("services")) ?>">View services</a>
          <a class="btn btn-outline" href="<?= h(route("about")) ?>">Read about the starter</a>
          <a class="btn btn-ghost" href="<?= h(route("contact")) ?>">Open contact</a>
        </div>
      </div>
    </section>
  </div>
</section>

<section class="section">
  <div class="container">
    <section class="feature-section" aria-label="Starter page map">
      <div class="section-header mb-0">
        <p class="feature-kicker">Starter page map</p>
        <h2 class="section-title">The default skeleton already gives the project a usable public structure.</h2>
        <p class="section-text">Each page is there to be reshaped directly by the downstream team rather than replaced by an entirely separate front-end.</p>
      </div>
      <div class="grid grid-2 gap-md">
        <?php foreach ($starterPages as $starterPage): ?>
        <article class="feature-card">
          <h3 class="content-title"><?= h($starterPage["title"]) ?></h3>
          <p class="content-text"><?= h($starterPage["text"]) ?></p>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
  </div>
</section>

<section class="section">
  <div class="container">
    <section class="process-section" aria-label="Starter growth steps">
      <div class="section-header mb-0">
        <p class="process-kicker">How teams work on it</p>
        <h2 class="section-title">The first implementation pass should deepen this skeleton, not abandon it.</h2>
      </div>
      <div class="process-grid">
        <?php foreach ($growthSteps as $step): ?>
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
    <section class="feature-section" aria-label="Starter principles">
      <div class="section-header mb-0">
        <p class="feature-kicker">Why this shape matters</p>
        <h2 class="section-title">A strong starter reduces the gap between &ldquo;the framework runs&rdquo; and &ldquo;the team can ship the product.&rdquo;</h2>
      </div>
      <div class="grid grid-3 gap-md">
        <?php foreach ($starterPrinciples as $principle): ?>
        <article class="feature-card">
          <h3 class="content-title"><?= h($principle["title"]) ?></h3>
          <p class="content-text"><?= h($principle["text"]) ?></p>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
  </div>
</section>

<section class="section">
  <div class="container">
    <section class="cta-section" aria-label="Starter call to action">
      <div class="cta-grid">
        <div class="grid gap-md cta-copy">
          <div class="d-flex flex-wrap items-center gap-md">
            <span class="tag">Starter rule</span>
            <span class="badge">Edit in place</span>
          </div>
          <h2 class="content-title">Build the final product by extending this starter directly through routes, controller actions, sections and containers.</h2>
          <p class="content-text">That keeps the application coherent from the beginning and lets maintenance, health and runtime rules stay linked without competing with the public experience.</p>
        </div>
      </div>
    </section>
  </div>
</section>
