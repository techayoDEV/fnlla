<?php

declare(strict_types=1);

$service = (array) ($health["service"] ?? []);
$versions = (array) ($health["versions"] ?? []);
$runtime = (array) ($health["runtime"] ?? []);
$requestInfo = (array) ($health["request"] ?? []);
$readiness = (array) ($health["readiness"] ?? []);
$dependencies = (array) ($health["dependencies"] ?? []);
$releaseChannel = (array) ($health["release_channel"] ?? []);
$versionContract = (array) ($health["version_contract"] ?? []);
$storage = (array) ($health["storage"] ?? []);
$frameworkUpdate = (array) ($health["framework_update"] ?? []);
$operatorNotes = (array) ($health["operator_notes"] ?? []);
$links = (array) ($health["links"] ?? []);
$serviceStatus = (string) ($service["status"] ?? "unknown");
$releaseStatus = (string) ($releaseChannel["status"] ?? "unknown");
$fnllaVersion = trim((string) ($versions["fnlla"] ?? "unknown"));
$integratedRuntimeVersion = trim((string) ($versions["fnlla_runtime"] ?? "unknown"));
$unifiedVersionModel = $fnllaVersion !== "" && $fnllaVersion === $integratedRuntimeVersion;
?>
<section class="section pt-1">
  <div class="container">
    <section class="feature-section" aria-label="Health summary">
      <div class="section-header mb-0">
        <p class="feature-kicker">Current snapshot</p>
        <h2 class="section-title">The status page keeps the operational baseline explicit without breaking visual continuity.</h2>
      </div>
      <div class="grid gap-md maintenance-health-stack">
        <article class="feature-card">
          <h3 class="content-title">Service</h3>
          <p class="content-text mb-1"><strong>Name:</strong> <?= h((string) ($service["name"] ?? "Unknown service")) ?></p>
          <p class="content-text mb-1"><strong>Slug:</strong> <?= h((string) ($service["slug"] ?? "unknown")) ?></p>
          <p class="content-text mb-0"><strong>Timestamp:</strong> <?= h((string) ($service["timestamp"] ?? "unknown")) ?></p>
        </article>
        <article class="feature-card">
          <h3 class="content-title">Runtime</h3>
          <p class="content-text mb-1"><strong>PHP:</strong> <?= h((string) ($runtime["php_version"] ?? "unknown")) ?></p>
          <p class="content-text mb-1"><strong>SAPI:</strong> <?= h((string) ($runtime["sapi"] ?? "unknown")) ?></p>
          <p class="content-text mb-0"><strong>Timezone:</strong> <?= h((string) ($runtime["timezone"] ?? "unknown")) ?></p>
        </article>
        <article class="feature-card">
          <h3 class="content-title">Request</h3>
          <p class="content-text mb-1"><strong>Method:</strong> <?= h((string) ($requestInfo["method"] ?? "unknown")) ?></p>
          <p class="content-text mb-1"><strong>Path:</strong> <?= h((string) ($requestInfo["path"] ?? "unknown")) ?></p>
          <p class="content-text mb-0"><strong>Request ID:</strong> <code><?= h((string) ($requestInfo["id"] ?? "unknown")) ?></code></p>
        </article>
      </div>
    </section>
  </div>
</section>

<section class="section">
  <div class="container">
    <section class="feature-section" aria-label="Dependency and release channel status">
      <div class="grid grid-2 gap-md">
        <article class="feature-card">
          <h2 class="content-title">Versions and release state</h2>
          <p class="content-text mb-1"><strong>FNLLA:</strong> <?= h((string) ($versions["fnlla"] ?? "unknown")) ?></p>
          <p class="content-text mb-1"><strong>Integrated UI surface:</strong> <?= $unifiedVersionModel ? "synced to FNLLA" : h($integratedRuntimeVersion) ?></p>
          <p class="content-text mb-1"><strong>Release channel status:</strong> <?= h($releaseStatus) ?></p>
          <p class="content-text mb-0"><strong>Latest cached tag:</strong> <?= h((string) ($releaseChannel["latest_cached_tag"] ?? "not cached")) ?></p>
        </article>
        <article class="feature-card">
          <h2 class="content-title">Dependency checks</h2>
          <ul class="contact-list">
            <?php foreach ($dependencies as $dependency): ?>
            <?php if (!is_array($dependency)) { continue; } ?>
            <li><strong><?= h((string) ($dependency["label"] ?? "Dependency")) ?>:</strong> <?= h((string) ($dependency["status"] ?? "unknown")) ?></li>
            <?php endforeach; ?>
          </ul>
        </article>
        <article class="feature-card">
          <h2 class="content-title">Framework update source</h2>
          <p class="content-text mb-1"><strong>Source origin:</strong> <?= h((string) ($frameworkUpdate["source_origin"] ?? "manual input required")) ?></p>
          <p class="content-text mb-1"><strong>Framework storage writable:</strong> <?= !empty($storage["framework_writable"]) ? "Yes" : "No" ?></p>
          <p class="content-text mb-1"><strong>Update cache writable:</strong> <?= !empty($storage["updates_writable"]) ? "Yes" : "No" ?></p>
          <p class="content-text mb-0"><strong>Source path:</strong> <?= h((string) ($frameworkUpdate["source_path"] ?? "not configured")) ?></p>
        </article>
        <article class="feature-card">
          <h2 class="content-title">Raw endpoint contract</h2>
          <p class="content-text">This maintenance page is the human-facing operator layer. The same payload stays available from <code>/api/health</code> with <code>Accept: application/json</code> or <code>?format=json</code>.</p>
          <?php if (!empty($versionContract["errors"])): ?>
          <ul class="contact-list">
            <?php foreach ((array) $versionContract["errors"] as $error): ?>
            <li><?= h((string) $error) ?></li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
          <div class="d-flex flex-wrap gap-md">
            <a class="btn btn-primary" href="<?= h((string) ($links["api_health"] ?? route("api.health"))) ?>">Open browser API health</a>
            <a class="btn btn-outline" href="<?= h((string) ($links["api_health_json"] ?? (route("api.health") . "?format=json"))) ?>">Open raw JSON</a>
            <a class="btn btn-outline" href="<?= h((string) ($links["maintenance"] ?? route("maintenance.home"))) ?>">Back to maintenance</a>
          </div>
        </article>
      </div>
    </section>
  </div>
</section>

<?php if ($operatorNotes !== []): ?>
<section class="section">
  <div class="container">
    <section class="feature-section" aria-label="Operator notes">
      <div class="section-header mb-0">
        <p class="feature-kicker">Operator notes</p>
        <h2 class="section-title">Status <?= h(strtoupper($serviceStatus)) ?> in <?= h((string) ($service["environment"] ?? "unknown")) ?> with release channel <?= h($releaseStatus) ?>.</h2>
      </div>
      <div class="feature-card">
        <ul class="starter-note-list">
          <?php foreach ($operatorNotes as $note): ?>
          <li><?= h((string) $note) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </section>
  </div>
</section>
<?php endif; ?>
