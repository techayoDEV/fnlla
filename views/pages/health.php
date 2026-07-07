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
$readinessOkCount = count(array_filter($readiness, static fn (mixed $value): bool => in_array((string) $value, ["ready", "ok"], true)));
$readinessTotal = count($readiness);
?>
<section class="section pt-1">
  <div class="container site-page-stack">
    <section class="hero hero-compact" aria-label="Operations health overview">
      <div class="grid gap-md hero-copy">
        <div class="d-flex flex-wrap items-center gap-md">
          <span class="tag">Maintenance</span>
          <span class="badge">Health view</span>
          <span class="badge">Operator-only surface</span>
          <span class="badge">API-backed</span>
        </div>
        <h1 class="hero-title">Operational health should read like part of the same application shell, not like a detached diagnostics screen.</h1>
        <p class="hero-text">This maintenance view summarizes the same state exposed by <code>/api/health</code>, but keeps the layout close to the starter shell so teams can review readiness, dependencies and operator context without leaving the application language of the project.</p>
        <div class="d-flex flex-wrap items-center gap-md">
          <span class="badge"><?= h(strtoupper($serviceStatus)) ?></span>
          <span class="badge"><?= h((string) ($service["environment"] ?? "unknown")) ?></span>
          <span class="badge">FNLLA PHP <?= h((string) ($versions["fnlla_php"] ?? "unknown")) ?></span>
          <span class="badge">FNLLA Web <?= h((string) ($versions["fnlla_web"] ?? "unknown")) ?></span>
          <span class="badge">Readiness <?= h((string) $readinessOkCount) ?>/<?= h((string) $readinessTotal) ?></span>
        </div>
        <div class="hero-actions">
          <a class="btn btn-primary btn-xl" href="<?= h((string) ($links["maintenance"] ?? route("maintenance.home"))) ?>">Open maintenance hub</a>
          <a class="btn btn-outline" href="<?= h((string) ($links["framework_updates"] ?? route("maintenance.framework_update"))) ?>">Open framework updates</a>
          <a class="btn btn-ghost" href="<?= h((string) ($links["api_health"] ?? route("api.health"))) ?>">Open raw JSON</a>
        </div>
      </div>
    </section>
  </div>
</section>

<section class="section">
  <div class="container">
    <section class="feature-section" aria-label="Health summary">
      <div class="section-header mb-0">
        <p class="feature-kicker">Current snapshot</p>
        <h2 class="section-title">The status page keeps the operational baseline explicit without breaking visual continuity.</h2>
      </div>
      <div class="grid grid-2 gap-md">
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
    <section class="process-section" aria-label="Readiness checks">
      <div class="section-header mb-0">
        <p class="process-kicker">Readiness</p>
        <h2 class="section-title">The core downstream signals are grouped as operational checkpoints.</h2>
      </div>
      <div class="process-grid">
        <?php foreach ($checkItems as $checkItem): ?>
        <?php $status = (string) ($checkItem["status"] ?? "unknown"); ?>
        <article class="process-step">
          <span class="process-step-number"><?= h(strtoupper($status)) ?></span>
          <h3 class="process-step-title"><?= h((string) ($checkItem["label"] ?? "Check")) ?></h3>
          <p class="process-step-text"><?= h((string) ($checkItem["text"] ?? "")) ?></p>
        </article>
        <?php endforeach; ?>
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
          <p class="content-text mb-1"><strong>FNLLA PHP:</strong> <?= h((string) ($versions["fnlla_php"] ?? "unknown")) ?></p>
          <p class="content-text mb-1"><strong>FNLLA Web:</strong> <?= h((string) ($versions["fnlla_web"] ?? "unknown")) ?></p>
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
          <p class="content-text">Automations should still consume the JSON endpoint directly. This page is the human-facing operator layer over the same state.</p>
          <?php if (!empty($versionContract["errors"])): ?>
          <ul class="contact-list">
            <?php foreach ((array) $versionContract["errors"] as $error): ?>
            <li><?= h((string) $error) ?></li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
          <div class="d-flex flex-wrap gap-md">
            <a class="btn btn-primary" href="<?= h((string) ($links["api_health"] ?? route("api.health"))) ?>">Open /api/health</a>
            <a class="btn btn-outline" href="<?= h((string) ($links["maintenance"] ?? route("maintenance.home"))) ?>">Back to maintenance</a>
          </div>
        </article>
      </div>
    </section>
  </div>
</section>
