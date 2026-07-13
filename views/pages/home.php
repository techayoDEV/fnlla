<?php

declare(strict_types=1);
?>
<section class="section">
  <div class="container">
    <section class="process-section" aria-label="Starter growth steps">
      <div class="section-header mb-0">
        <p class="process-kicker">How teams work on it</p>
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
