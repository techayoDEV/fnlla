<?php

declare(strict_types=1);

$developerPanelTitle = "Release Readiness";
$developerPanelLead = "Production gate for security, backup, framework drift, cache, acceptance and runtime health.";
$report = is_array($operationsReport ?? null) ? (array) $operationsReport : [];
$release = (array) ($report["release_readiness"] ?? []);
$health = is_array($health ?? null) ? $health : [];
$service = (array) ($health["service"] ?? []);
$runtime = (array) ($health["runtime"] ?? []);
$versions = (array) ($health["versions"] ?? []);
$dependencies = (array) ($health["dependencies"] ?? []);
$readinessFailures = (int) ($release["security_audit"]["failures"] ?? 0)
    + (int) ($release["backup_restore"]["failures"] ?? 0)
    + (int) ($release["acceptance"]["failures"] ?? 0);
$readinessWarnings = (int) ($release["security_audit"]["warnings"] ?? 0)
    + (int) ($release["acceptance"]["warnings"] ?? 0);
$gateCommands = [
    ["command" => "php fnlla security:audit --strict", "area" => "Security", "purpose" => "Fail the release on unsafe runtime, access or policy posture."],
    ["command" => "php fnlla ops:backup-plan --verify", "area" => "Recovery", "purpose" => "Confirm restore and storage runbook before production changes."],
    ["command" => "php fnlla project:acceptance --json", "area" => "Acceptance", "purpose" => "Export machine-readable acceptance checks for the project surface."],
    ["command" => "php fnlla perf:budget", "area" => "Performance", "purpose" => "Check page and route budgets before launch."],
    ["command" => "php scripts/test.php", "area" => "Tests", "purpose" => "Run the application test suite."],
    ["command" => "php scripts/lint.php", "area" => "Lint", "purpose" => "Run PHP syntax and project lint checks."],
];
require __DIR__ . "/panel-header.php";
?>

        <section class="developer-dashboard-section" aria-label="Release readiness">
          <div class="developer-panel-intro">
            <div class="developer-panel-intro-copy">
              <p class="feature-kicker">Release command center</p>
              <h2 class="developer-dashboard-section-title">Use this page as the production gate before sharing, staging or deploying.</h2>
              <p class="content-text mb-0">Security, backup restore, framework drift, cache, acceptance and runtime health are shown together so release blockers do not hide on separate pages.</p>
            </div>
            <div class="developer-panel-intro-actions">
              <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["framework_updates"] ?? route("developer.panel.framework_updates"))) ?>">Framework updates</a>
              <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["operations"] ?? route("developer.panel.operations"))) ?>">Operations</a>
            </div>
          </div>

          <div class="developer-dashboard-status-grid">
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Security audit</strong></div>
              <h3><?= h((string) ($release["security_audit"]["failures"] ?? 0)) ?> failures</h3>
              <p><?= h((string) ($release["security_audit"]["warnings"] ?? 0)) ?> warnings, <?= h((string) ($release["security_audit"]["passed"] ?? 0)) ?> passed.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Backup restore</strong><span class="developer-dashboard-ok"><?= ($release["backup_restore"]["ok"] ?? false) ? "OK" : "CHECK" ?></span></div>
              <h3><?= h((string) ($release["backup_restore"]["failures"] ?? 0)) ?> blockers</h3>
              <p>Runbook and storage recovery must be verified before production release.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Framework drift</strong></div>
              <h3>FNLLA <?= h((string) ($release["framework_drift"]["framework_version"] ?? "unknown")) ?></h3>
              <p>Lock <?= ($release["framework_drift"]["lock_present"] ?? false) ? "present" : "missing" ?>.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Acceptance</strong><span class="developer-dashboard-ok"><?= ($release["acceptance"]["ok"] ?? false) ? "OK" : "CHECK" ?></span></div>
              <h3><?= h((string) ($release["acceptance"]["failures"] ?? 0)) ?> failures</h3>
              <p><?= h((string) ($release["acceptance"]["warnings"] ?? 0)) ?> warnings.</p>
            </article>
          </div>
          <div class="developer-panel-status-note">
            <strong>Gate summary</strong>
            <span><?= h((string) $readinessFailures) ?> failures and <?= h((string) $readinessWarnings) ?> warnings are currently visible in the release gate snapshot.</span>
          </div>
        </section>

        <section class="developer-dashboard-section" aria-label="Runtime health">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title">Runtime health</h2>
            <span class="developer-dashboard-refresh"><?= h((string) ($service["timestamp"] ?? "Live snapshot")) ?></span>
          </div>
          <div class="developer-dashboard-status-grid">
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Service</strong><span class="developer-dashboard-ok"><?= h(strtoupper((string) ($service["status"] ?? "OK"))) ?></span></div>
              <h3><?= h((string) ($service["name"] ?? config("app.name", "FNLLA Project"))) ?></h3>
              <p>Environment <?= h((string) ($service["environment"] ?? app_environment())) ?>.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Runtime</strong><span class="developer-dashboard-ok">OK</span></div>
              <h3>PHP <?= h((string) ($runtime["php_version"] ?? PHP_VERSION)) ?></h3>
              <p><?= h((string) ($runtime["sapi"] ?? PHP_SAPI)) ?> · <?= h((string) ($runtime["timezone"] ?? date_default_timezone_get())) ?></p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Versions</strong><span class="developer-dashboard-ok">OK</span></div>
              <h3>FNLLA <?= h((string) ($versions["fnlla"] ?? "unknown")) ?></h3>
              <p>Runtime <?= h((string) ($versions["fnlla_runtime"] ?? "unknown")) ?>.</p>
            </article>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head"><strong>Dependencies</strong><span class="developer-dashboard-ok"><?= $dependencies === [] ? "OK" : h((string) count($dependencies)) ?></span></div>
              <ul class="developer-dashboard-check-list">
                <?php foreach (array_slice($dependencies, 0, 4) as $dependency): ?>
                <?php if (!is_array($dependency)) { continue; } ?>
                <li><?= h((string) ($dependency["label"] ?? "Dependency")) ?>: <?= strtolower((string) ($dependency["status"] ?? "ok")) === "ok" ? "OK" : "Needs attention" ?></li>
                <?php endforeach; ?>
                <?php if ($dependencies === []): ?>
                <li>No dependency blockers reported</li>
                <?php endif; ?>
              </ul>
            </article>
          </div>
        </section>

        <section class="developer-dashboard-section" aria-label="Release checklist">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title">Production gate commands</h2>
            <span class="developer-dashboard-refresh">Run locally before release</span>
          </div>
          <div class="developer-dashboard-glance-table">
            <?php foreach ($gateCommands as $command): ?>
            <div class="developer-dashboard-glance-row">
              <strong><code><?= h((string) $command["command"]) ?></code></strong>
              <span><?= h((string) $command["purpose"]) ?></span>
              <span><?= h((string) $command["area"]) ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </section>

<?php require __DIR__ . "/panel-footer.php"; ?>
