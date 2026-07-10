<?php

declare(strict_types=1);
?>
<section class="section pt-1">
  <div class="container site-page-stack">
    <section class="hero hero-compact" aria-label="Project launch introduction">
      <div class="grid gap-md hero-copy">
        <div class="d-flex flex-wrap items-center gap-md">
          <span class="tag">Project launch</span>
          <span class="badge">Delivery guide</span>
          <span class="badge">Developer onboarding</span>
          <span class="badge">Release hygiene</span>
        </div>
        <h1 class="hero-title">Turn the starter into the real product with a deliberate project flow instead of ad-hoc edits.</h1>
        <p class="hero-text">This page is the built-in guide for the first downstream delivery pass. It points to the files that matter first, the commands that should become normal habits and the operational routes that should stay linked rather than merged into the public product surface.</p>
        <div class="hero-actions">
          <a class="btn btn-primary btn-xl" href="<?= h(route("contact")) ?>">Open the working form flow</a>
          <a class="btn btn-outline" href="<?= h(route("maintenance.framework_update")) ?>">Open framework updates</a>
          <a class="btn btn-ghost" href="<?= h(route("health")) ?>">Open health status</a>
        </div>
      </div>
    </section>
  </div>
</section>

<section class="section">
  <div class="container">
    <section class="process-section" aria-label="Project launch tracks">
      <div class="section-header mb-0">
        <p class="process-kicker">Delivery tracks</p>
        <h2 class="section-title">Four tracks make the starter feel owned, not abandoned.</h2>
      </div>
      <div class="process-grid">
        <?php foreach ($launchTracks as $track): ?>
        <article class="process-step">
          <span class="process-step-number"><?= h($track["number"]) ?></span>
          <h3 class="process-step-title"><?= h($track["title"]) ?></h3>
          <p class="process-step-text"><?= h($track["text"]) ?></p>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
  </div>
</section>

<section class="section">
  <div class="container">
    <section class="feature-section" aria-label="Files and commands to touch first">
      <div class="grid grid-2 gap-md">
        <article class="feature-card">
          <h2 class="content-title">Files to replace or review first</h2>
          <ul class="contact-list">
            <?php foreach ($launchFiles as $launchFile): ?>
            <li><code><?= h($launchFile) ?></code></li>
            <?php endforeach; ?>
          </ul>
        </article>
        <article class="feature-card">
          <h2 class="content-title">Commands to normalize early</h2>
          <ul class="contact-list">
            <?php foreach ($launchCommands as $launchCommand): ?>
            <li><code><?= h($launchCommand) ?></code></li>
            <?php endforeach; ?>
          </ul>
        </article>
      </div>
    </section>
  </div>
</section>

<section class="section">
  <div class="container">
    <section class="cta-section" aria-label="Project launch call to action">
      <div class="cta-grid">
        <div class="grid gap-md cta-copy">
          <div class="d-flex flex-wrap items-center gap-md">
            <span class="tag">Operational note</span>
            <span class="badge">Framework maintenance included</span>
          </div>
          <h2 class="content-title">Use <code>/maintenance/framework-update</code> as the controlled path for downstream framework updates.</h2>
          <p class="content-text">The maintenance surface can auto-detect a sibling <code>fnlla</code> repository when one exists, while still tolerating older local <code>fnlla-php</code> checkouts, produce a structured drift report and apply only safe framework-managed changes. That keeps framework work explicit without requiring every developer to memorise the update internals.</p>
          <div class="d-flex flex-wrap gap-md">
            <a class="btn btn-primary btn-xl" href="<?= h(route("maintenance.framework_update")) ?>">Open framework updates</a>
            <a class="btn btn-outline" href="<?= h(route("home")) ?>">Back to home</a>
          </div>
        </div>
      </div>
    </section>
  </div>
</section>
