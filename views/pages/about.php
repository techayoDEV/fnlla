<?php

declare(strict_types=1);
?>
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
