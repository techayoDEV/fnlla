<?php

declare(strict_types=1);
?>
<section class="section">
  <div class="container">
    <div class="section-header">
      <p class="section-kicker">Product Direction</p>
      <h1 class="section-title">FNLLA is a framework for owned PHP application delivery.</h1>
      <p class="section-text">FNLLA is produced by TechAyo LTD to make small and growing web applications easier to understand, operate, release and extend from the first commit onward.</p>
    </div>

    <div class="grid grid-3 gap-md">
      <?php foreach ($visionPillars as $pillar): ?>
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
    <section class="process-section" aria-label="FNLLA product direction principles">
      <div class="section-header mb-0">
        <p class="process-kicker">Direction principles</p>
      </div>
      <div class="process-grid">
        <?php foreach ($directionPrinciples as $principle): ?>
        <article class="process-step">
          <span class="process-step-number"><?= h($principle["number"]) ?></span>
          <h3 class="process-step-title"><?= h($principle["title"]) ?></h3>
          <p class="process-step-text"><?= h($principle["text"]) ?></p>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
  </div>
</section>

<section class="section">
  <div class="container">
    <article class="feature-card">
      <p class="feature-kicker">Product lead</p>
      <h2 class="content-title">Concept and product direction are led by <a href="https://techayo.co.uk/marcin" rel="noopener noreferrer">Marcin</a>.</h2>
      <p class="content-text">This notice records product accountability without changing the project ownership: FNLLA remains a TechAyo LTD project released under the MIT License.</p>
    </article>
  </div>
</section>
