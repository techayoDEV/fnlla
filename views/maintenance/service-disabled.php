<?php

declare(strict_types=1);

$developerControl ??= [
    "title" => "Service disabled by developer",
    "message" => "This service is temporarily disabled by the developer team. Please contact the project developer for assistance.",
    "contact" => "",
    "source" => "local",
];
?>
<section class="section client-preview-stage service-disabled-stage">
  <div class="container client-preview-shell">
    <section class="card client-preview-card service-disabled-card" aria-label="Service disabled">
      <div class="card-body client-preview-card-body">
        <p class="feature-kicker client-preview-kicker">Developer service control</p>
        <header class="client-preview-header">
          <div class="client-preview-header-copy">
            <h1 class="content-title client-preview-title"><?= h((string) ($developerControl["title"] ?? "Service disabled by developer")) ?></h1>
            <p class="client-preview-meta">
              <span class="client-preview-meta-label">Source:</span>
              <span class="client-preview-meta-value"><?= h((string) ($developerControl["source"] ?? "local")) ?></span>
            </p>
          </div>
        </header>
        <div class="client-preview-note-row">
          <span class="client-preview-note-icon" aria-hidden="true">!</span>
          <p class="client-preview-note-text"><?= h((string) ($developerControl["message"] ?? "This service is temporarily disabled by the developer team.")) ?></p>
        </div>
        <?php if (trim((string) ($developerControl["contact"] ?? "")) !== ""): ?>
        <div class="client-preview-support-row">
          <span class="client-preview-support-icon" aria-hidden="true">@</span>
          <div class="client-preview-support-copy">
            <strong>Contact the developer team</strong>
            <p class="client-preview-note-text"><?= h((string) $developerControl["contact"]) ?></p>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </section>
  </div>
</section>
