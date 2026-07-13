<?php

declare(strict_types=1);

$service = (array) ($health["service"] ?? []);
$versions = (array) ($health["versions"] ?? []);
$runtime = (array) ($health["runtime"] ?? []);
$checks = (array) ($health["checks"] ?? []);
$releaseChannel = (array) ($health["release_channel"] ?? []);
$frameworkUpdate = (array) ($health["framework_update"] ?? []);
$links = (array) ($health["links"] ?? []);
$rawJson = json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
$rawJson = is_string($rawJson) ? $rawJson : "{}";
$fnllaVersion = trim((string) ($versions["fnlla"] ?? "unknown"));
$integratedRuntimeVersion = trim((string) ($versions["fnlla_runtime"] ?? "unknown"));
$unifiedVersionModel = $fnllaVersion !== "" && $fnllaVersion === $integratedRuntimeVersion;
?>
<section class="section pt-1">
  <div class="container">
    <section class="feature-section" aria-label="API health browser summary">
      <div class="section-header mb-0">
        <p class="feature-kicker">Browser-friendly API health</p>
        <h1 class="section-title">This page makes <code>/api/health</code> easier to scan in a browser without changing the JSON contract for machines.</h1>
        <p class="section-text">Use the raw JSON button when you want the exact payload. Monitoring and automations should still send <code>Accept: application/json</code> or append <code>?format=json</code>.</p>
      </div>
      <div class="grid grid-3 gap-md api-health-summary-grid">
        <article class="feature-card">
          <p class="feature-kicker">Service</p>
          <h2 class="content-title mb-xs"><?= h((string) ($service["name"] ?? "Unknown service")) ?></h2>
          <p class="content-text mb-0"><?= h((string) ($service["status"] ?? "unknown")) ?> in <?= h((string) ($service["environment"] ?? "unknown")) ?></p>
        </article>
        <article class="feature-card">
          <p class="feature-kicker">Versions</p>
          <h2 class="content-title mb-xs"><?= h((string) ($versions["fnlla"] ?? "unknown")) ?></h2>
          <p class="content-text mb-0"><?= $unifiedVersionModel ? "Integrated UI surface synced to FNLLA" : ("Integrated UI surface " . h($integratedRuntimeVersion)) ?></p>
        </article>
        <article class="feature-card">
          <p class="feature-kicker">Release cache</p>
          <h2 class="content-title mb-xs"><?= h((string) ($releaseChannel["latest_cached_tag"] ?? "Not cached")) ?></h2>
          <p class="content-text mb-0">Channel <?= h((string) ($releaseChannel["status"] ?? "unknown")) ?></p>
        </article>
      </div>
    </section>
  </div>
</section>

<section class="section">
  <div class="container">
    <section class="feature-section" aria-label="API health endpoint modes">
      <div class="grid grid-2 gap-md api-health-mode-grid">
        <article class="feature-card">
          <p class="feature-kicker">Browser mode</p>
          <h2 class="content-title">Readable first</h2>
          <p class="content-text">Open <code>/api/health</code> directly in the browser when you want a quick visual check of service status, versions, source detection and update posture.</p>
          <div class="d-flex flex-wrap gap-md">
            <a class="btn btn-primary" href="<?= h((string) ($links["api_health_json"] ?? (route("api.health") . "?format=json"))) ?>">Open raw JSON</a>
            <a class="btn btn-outline" href="<?= h((string) ($links["maintenance"] ?? route("maintenance.home"))) ?>">Back to maintenance</a>
          </div>
        </article>
        <article class="feature-card">
          <p class="feature-kicker">Machine mode</p>
          <h2 class="content-title">Exact payload</h2>
          <ul class="starter-note-list">
            <li>Send <code>Accept: application/json</code> to receive JSON directly.</li>
            <li>Append <code>?format=json</code> when you want raw output in a browser tab.</li>
            <li>Use the same endpoint for dashboards, smoke checks and deployment scripts.</li>
          </ul>
        </article>
      </div>
    </section>
  </div>
</section>

<section class="section">
  <div class="container">
    <section class="feature-section" aria-label="API health details">
      <div class="grid grid-2 gap-md">
        <article class="feature-card">
          <h2 class="content-title">Runtime and checks</h2>
          <p class="content-text mb-1"><strong>PHP:</strong> <?= h((string) ($runtime["php_version"] ?? "unknown")) ?></p>
          <p class="content-text mb-1"><strong>SAPI:</strong> <?= h((string) ($runtime["sapi"] ?? "unknown")) ?></p>
          <p class="content-text mb-1"><strong>Secure request:</strong> <?= !empty($runtime["secure_request"]) ? "Yes" : "No" ?></p>
          <p class="content-text mb-0"><strong>Framework source:</strong> <?= h((string) ($frameworkUpdate["source_origin"] ?? "manual input required")) ?></p>
        </article>
        <article class="feature-card">
          <h2 class="content-title">Current checks</h2>
          <ul class="contact-list">
            <?php foreach ($checks as $label => $status): ?>
            <li><strong><?= h((string) str_replace("_", " ", $label)) ?>:</strong> <?= h((string) $status) ?></li>
            <?php endforeach; ?>
          </ul>
        </article>
      </div>
    </section>
  </div>
</section>

<section class="section">
  <div class="container">
    <section class="feature-section" aria-label="Raw health payload">
      <div class="section-header mb-0">
        <p class="feature-kicker">Raw payload preview</p>
        <h2 class="section-title">The same structure stays available for machine consumers.</h2>
      </div>
      <article class="feature-card">
        <pre class="api-health-code-block"><code><?= h($rawJson) ?></code></pre>
      </article>
    </section>
  </div>
</section>
