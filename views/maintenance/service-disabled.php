<?php

declare(strict_types=1);

$developerControl ??= [
    "title" => "Service paused by developer",
    "message" => "This service is temporarily disabled by the developer team. Please contact the project developer for assistance.",
    "contact" => "",
    "contact_phone" => "",
    "source" => "local",
    "local_disabled" => true,
    "remote_disabled" => false,
    "status" => "disabled",
    "reason" => "",
    "provider" => "",
];
$serviceStatus = (string) ($developerControl["status"] ?? "disabled");
$serviceReason = (string) ($developerControl["reason"] ?? "");
$serviceProvider = (string) ($developerControl["provider"] ?? "");
$serviceContact = trim((string) ($developerControl["contact"] ?? ""));
$serviceContactPhone = trim((string) ($developerControl["contact_phone"] ?? ""));
$serviceStatusLabel = ucfirst(str_replace("_", " ", $serviceStatus));
$serviceReasonLabel = ucfirst(strtolower(str_replace("_", " ", $serviceReason)));
?>
<section class="section client-preview-stage service-disabled-stage">
  <div class="container client-preview-shell">
    <section class="card client-preview-card service-disabled-card" aria-label="Service disabled">
      <div class="card-body client-preview-card-body">
        <p class="feature-kicker client-preview-kicker">Developer service control</p>
        <header class="client-preview-header">
          <div class="client-preview-header-copy">
            <h1 class="content-title client-preview-title"><?= h((string) ($developerControl["title"] ?? "Service paused by developer")) ?></h1>
            <p class="client-preview-meta">
              <span class="client-preview-meta-label">Status:</span>
              <span class="client-preview-meta-value"><?= h($serviceStatusLabel) ?></span>
              <?php if ($serviceReason !== ""): ?>
              <span class="client-preview-meta-label">Reason:</span>
              <span class="client-preview-meta-value"><?= h($serviceReasonLabel) ?></span>
              <?php endif; ?>
            </p>
          </div>
        </header>
        <div class="client-preview-note-row">
          <span class="client-preview-note-icon" aria-hidden="true">!</span>
          <p class="client-preview-note-text"><?= h((string) ($developerControl["message"] ?? "This service is temporarily disabled by the developer team.")) ?></p>
        </div>
        <?php if ($serviceContact !== "" || $serviceContactPhone !== ""): ?>
        <div class="client-preview-support-row">
          <span class="client-preview-support-icon" aria-hidden="true">@</span>
          <div class="client-preview-support-copy">
            <strong><?= h($serviceProvider !== "" ? "Contact " . $serviceProvider : "Contact the service provider") ?></strong>
            <?php if ($serviceContact !== ""): ?>
            <p class="client-preview-note-text"><?= h($serviceContact) ?></p>
            <?php endif; ?>
            <?php if ($serviceContactPhone !== ""): ?>
            <p class="client-preview-note-text"><?= h($serviceContactPhone) ?></p>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </section>
  </div>
</section>
