<?php

declare(strict_types=1);
?>
<section class="section starter-page-hero">
  <div class="container">
    <div class="starter-page-heading">
      <p class="starter-kicker">Services</p>
      <h1>Turn these starter cards into the actual services, modules or workflows the project offers.</h1>
      <p>
        Use the page as a structured place for offers, product modules, internal capabilities or delivery tracks.
      </p>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="grid grid-4 gap-md">
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
        <p class="process-kicker">Delivery rhythm</p>
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
