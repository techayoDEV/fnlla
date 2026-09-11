<?php

declare(strict_types=1);

use Fnlla\Php\Support\DeveloperPanelLabels;

$developerPanelTitle = "Operations";
$developerPanelLead = "Operational priorities for access, review decisions, release readiness, observability and project-owned adapters.";
$report = is_array($operationsReport ?? null) ? $operationsReport : [];
$analytics = (array) ($report["analytics"] ?? []);
$performance = (array) ($report["performance"] ?? []);
$forms = (array) ($report["forms"] ?? []);
$auditLog = (array) ($report["audit_log"]["items"] ?? []);
$release = (array) ($report["release_readiness"] ?? []);
$integrations = (array) ($report["integrations"] ?? []);
$heatmaps = (array) ($report["heatmaps"] ?? []);
$privacy = (array) ($report["privacy"] ?? []);
$currentCapabilities = is_array($developerAccess["current_capabilities"] ?? null) ? (array) $developerAccess["current_capabilities"] : [];
$analyticsConsentEvent = (string) ($analytics["consent"]["analytics_event"] ?? "fnlla:analytics-consent-granted");
$formatBool = static fn (bool $value): string => $value ? "Yes" : "No";
$formatMetric = static fn (mixed $value, string $suffix = ""): string => is_numeric($value) ? rtrim(rtrim((string) round((float) $value, 2), "0"), ".") . $suffix : "n/a";
$renderMiniList = static function (array $items, string $empty): void { ?>
              <?php if ($items === []): ?>
              <p class="content-text mb-0"><?= h($empty) ?></p>
              <?php else: ?>
              <ul class="developer-dashboard-check-list is-metric-list">
                <?php foreach ($items as $item): ?>
                <li><span class="developer-dashboard-check-count"><?= h((string) ($item["count"] ?? "0")) ?></span><span class="developer-dashboard-check-label"><?= h((string) ($item["label"] ?? "")) ?></span></li>
                <?php endforeach; ?>
              </ul>
              <?php endif; ?>
<?php };
$reviewQueue = is_array($developerReviewQueue ?? null) ? (array) $developerReviewQueue : [];
$reviewItems = array_values((array) ($reviewQueue["items"] ?? []));
$reviewTotal = max(0, (int) ($reviewQueue["total_count"] ?? count($reviewItems)));
$reviewCritical = max(0, (int) ($reviewQueue["critical_count"] ?? 0));
$reviewWarnings = max(0, (int) ($reviewQueue["warning_count"] ?? 0));
$releaseStatus = ((bool) ($release["acceptance"]["ok"] ?? true) && (bool) ($release["backup_restore"]["ok"] ?? true)) ? "OK" : "Check";
$observabilityStatus = ((bool) ($analytics["enabled"] ?? false) || strtolower((string) ($heatmaps["status"] ?? "off")) !== "off") ? "ON" : "Review";
$operationLanes = [
    ["priority" => "P0", "title" => "Access & security", "text" => "Developer accounts, roles, TOTP, customer review access.", "href" => (string) ($developerLinks["access"] ?? route("developer.panel.access")), "status" => $reviewCritical > 0 ? "Critical" : "Review"],
    ["priority" => "P0", "title" => "Review queue", "text" => "Global decisions from setup, security and release checks.", "href" => (string) ($developerLinks["notifications"] ?? route("developer.panel.notifications")), "status" => (string) $reviewTotal],
    ["priority" => "P1", "title" => "Release & readiness", "text" => "Release gate, runtime health, backup evidence and framework updates.", "href" => (string) ($developerLinks["release_readiness"] ?? route("developer.panel.release_readiness")), "status" => $releaseStatus],
    ["priority" => "P2", "title" => "Observability", "text" => "Project logs, runtime errors, public-only analytics and behavior heatmap.", "href" => (string) ($developerLinks["operations"] ?? route("developer.panel.operations")) . "#developer-operations-observability", "status" => $observabilityStatus],
    ["priority" => "P3", "title" => "Adapters & AI", "text" => "Consent-aware outbound adapters, generic API hooks and optional AI providers.", "href" => (string) ($developerLinks["integrations"] ?? route("developer.panel.integrations")), "status" => (string) count($integrations)],
];
$operationLanes = array_values(array_filter($operationLanes, static function (array $lane) use ($currentCapabilities): bool {
    $capability = (string) ($lane["capability"] ?? "");

    return $capability === "" || in_array($capability, $currentCapabilities, true);
}));

require __DIR__ . "/panel-header.php";
?>

        <section class="developer-dashboard-section" aria-label="Operations overview">
          <div class="developer-panel-intro">
            <div class="developer-panel-intro-copy">
              <p class="feature-kicker">Operations overview</p>
              <h2 class="developer-dashboard-section-title">Daily operating view for access, decisions, releases and runtime signals.</h2>
              <p class="content-text mb-0">Use this as the first Operations stop: clear security and review decisions, then inspect release posture, observability and outbound adapters.</p>
            </div>
            <div class="developer-panel-intro-actions">
              <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["access"] ?? route("developer.panel.access"))) ?>">Access & security</a>
              <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["notifications"] ?? route("developer.panel.notifications"))) ?>">Review queue</a>
              <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["release_readiness"] ?? route("developer.panel.release_readiness"))) ?>">Release & readiness</a>
              <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["operations"] ?? route("developer.panel.operations")) . "#developer-operations-observability") ?>">Observability</a>
              <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["integrations"] ?? route("developer.panel.integrations"))) ?>">Adapters & AI</a>
            </div>
          </div>
        </section>

        <section class="developer-dashboard-section" aria-label="Operations priority map">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title">Operations priority map</h2>
            <span class="developer-dashboard-refresh">P0 first, telemetry after risk</span>
          </div>
          <div class="developer-operations-priority-grid">
            <?php foreach ($operationLanes as $lane): ?>
            <a class="developer-operations-lane" href="<?= h((string) $lane["href"]) ?>">
              <span><?= h((string) $lane["priority"]) ?></span>
              <strong><?= h((string) $lane["title"]) ?></strong>
              <p><?= h((string) $lane["text"]) ?></p>
              <em><?= h((string) $lane["status"]) ?></em>
            </a>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="developer-dashboard-section" aria-label="Operations review queue">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title">Review queue</h2>
            <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["notifications"] ?? route("developer.panel.notifications"))) ?>">Open full queue</a>
          </div>
          <div class="developer-review-queue-list developer-review-queue-list-compact">
            <?php foreach (array_slice($reviewItems, 0, 4) as $item): ?>
            <?php $severity = strtolower((string) ($item["severity"] ?? "info")); ?>
            <article class="developer-review-queue-row is-<?= h($severity) ?>">
              <span class="developer-review-queue-marker" aria-hidden="true"></span>
              <div>
                <small><?= h((string) ($item["source"] ?? "Developer Panel")) ?> / <?= h(strtoupper($severity)) ?> / Owner: <?= h((string) ($item["owner"] ?? "Lead developer")) ?></small>
                <strong><?= h((string) ($item["title"] ?? "Review item")) ?></strong>
                <p><?= h((string) ($item["text"] ?? "Review this item before continuing.")) ?></p>
              </div>
              <a class="btn btn-outline btn-sm" href="<?= h((string) ($item["href"] ?? ($developerLinks["notifications"] ?? route("developer.panel.notifications")))) ?>"><?= h((string) (($item["action"] ?? "") !== "" ? $item["action"] : "Review")) ?></a>
            </article>
            <?php endforeach; ?>
            <?php if ($reviewItems === []): ?>
            <article class="developer-review-queue-row is-success">
              <span class="developer-review-queue-marker" aria-hidden="true"></span>
              <div>
                <small>Developer Panel / CLEAR</small>
                <strong>No review items</strong>
                <p>Operations has no unresolved decisions from setup, access or release checks.</p>
              </div>
            </article>
            <?php endif; ?>
          </div>
        </section>

        <section class="developer-dashboard-section" id="developer-operations-observability" aria-label="Observability">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title">Observability</h2>
            <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["analytics"] ?? route("developer.panel.analytics"))) ?>">Open traffic analytics</a>
          </div>
          <div class="developer-dashboard-status-grid">
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head">
                <strong>Page views</strong>
                <span class="developer-dashboard-ok"><?= ($analytics["enabled"] ?? false) ? "ON" : "OFF" ?></span>
              </div>
              <h3><?= h((string) ($analytics["page_views"] ?? 0)) ?></h3>
              <p><?= h((string) ($analytics["total_requests"] ?? 0)) ?> total measured requests</p>
              <p class="developer-dashboard-status is-active"><?= h($formatMetric($analytics["average_response_ms"] ?? null, "ms avg")) ?></p>
            </article>

            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head">
                <strong>Top routes</strong>
              </div>
              <?php $renderMiniList((array) ($analytics["top_routes"] ?? []), "No page-view routes have been recorded yet."); ?>
            </article>

            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head">
                <strong>Referrers</strong>
              </div>
              <?php $renderMiniList((array) ($analytics["referrers"] ?? []), "No referrers have been recorded yet."); ?>
            </article>

            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head">
                <strong>Consent</strong>
                <span class="developer-dashboard-ok">READY</span>
              </div>
              <p>Consent storage is configured for first-party measurements.</p>
              <p>Consent signal: <?= h(DeveloperPanelLabels::event($analyticsConsentEvent)) ?></p>
            </article>
          </div>
          <div class="developer-dashboard-management-list">
            <article class="developer-dashboard-management-row">
              <div>
                <strong>Privacy posture</strong>
                <p>Raw IP: <?= h($formatBool((bool) ($privacy["raw_ip_addresses"] ?? false))) ?>. Raw user agents: <?= h($formatBool((bool) ($privacy["raw_user_agents"] ?? false))) ?>. Referrers: <?= h((string) ($privacy["referrers"] ?? "host-only")) ?>. Queries: <?= ($privacy["query_strings_tracked"] ?? false) ? "tracked" : "excluded" ?>.</p>
                <p>Retention: <?= h((string) ($privacy["retention_days"] ?? 90)) ?> days. Excluded paths: <?= h(implode(", ", (array) ($privacy["excluded_paths"] ?? []))) ?>.</p>
              </div>
              <span class="developer-dashboard-status is-active"><?= h((string) ($privacy["mode"] ?? "privacy-light")) ?></span>
            </article>
          </div>
        </section>

        <section class="developer-dashboard-section" aria-label="Performance probes">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title">Performance probes</h2>
            <span class="developer-dashboard-refresh">Slow route threshold: <?= h((string) ($performance["threshold_ms"] ?? 750)) ?>ms</span>
          </div>
          <div class="developer-dashboard-status-grid">
            <?php foreach ((array) ($performance["probes"] ?? []) as $name => $probe): ?>
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head">
                <strong><?= h((string) $name) ?></strong>
                <span class="developer-dashboard-ok"><?= ($probe["ok"] ?? false) ? "OK" : "CHECK" ?></span>
              </div>
              <?php if (isset($probe["error"])): ?>
              <p><?= h((string) $probe["error"]) ?></p>
              <?php else: ?>
              <h3><?= h($formatMetric($probe["p95_ms"] ?? null, "ms p95")) ?></h3>
              <p>Average <?= h($formatMetric($probe["avg_ms"] ?? null, "ms")) ?>, max <?= h($formatMetric($probe["max_ms"] ?? null, "ms")) ?></p>
              <p class="developer-dashboard-status <?= ($probe["slow"] ?? false) ? "is-neutral" : "is-active" ?>"><?= ($probe["slow"] ?? false) ? "Slow route candidate" : "Within budget" ?></p>
              <?php endif; ?>
            </article>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="developer-dashboard-section" aria-label="Form inbox and audit log">
          <div class="developer-dashboard-overview-grid">
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Form inbox</p>
              <h3><?= h((string) ($forms["recent_count"] ?? 0)) ?> recent submissions</h3>
              <p class="content-text">Validation issues: <?= h((string) ($forms["validation_issues"] ?? 0)) ?>. Failed mail delivery: <?= h((string) ($forms["failed_mail_delivery"] ?? 0)) ?>.</p>
              <p class="developer-dashboard-status is-neutral">Mailer: <?= h((string) ($forms["mail_driver"] ?? "log")) ?></p>
            </article>

            <article class="developer-dashboard-card">
              <p class="feature-kicker">Project logs</p>
              <h3><?= h((string) count($auditLog)) ?> recent events</h3>
              <p class="content-text">Developer actions, project changes, preview access and workspace updates are tracked in one readable activity trail.</p>
              <div class="developer-inline-actions">
                <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["project_logs"] ?? route("developer.panel.project_logs"))) ?>">Open project logs</a>
                <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["project_settings"] ?? route("developer.panel.project_settings"))) ?>">Open preview</a>
                <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["audit_export"] ?? route("developer.panel.audit_export"))) ?>">Export audit log</a>
                <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["audit_export_csv"] ?? route("developer.panel.audit_export_csv"))) ?>">Export CSV</a>
              </div>
            </article>

            <article class="developer-dashboard-card">
              <p class="feature-kicker">FNLLA Heatmap</p>
              <h3><?= h(ucfirst((string) ($heatmaps["status"] ?? "disabled"))) ?></h3>
              <p class="content-text"><?= h((string) ($heatmaps["notes"] ?? "Heatmaps stay outside the framework core.")) ?></p>
              <p class="developer-dashboard-status is-active"><?= h((string) ($heatmaps["mode"] ?? "first-party aggregate heatmap")) ?></p>
            </article>
          </div>
          <?php if ($auditLog !== []): ?>
          <div class="developer-dashboard-activity-list">
            <?php foreach ($auditLog as $event): ?>
            <article class="developer-dashboard-activity-row">
              <div>
                <strong><?= h((string) ($event["title"] ?? "Developer change")) ?></strong>
                <p><?= h((string) ($event["text"] ?? "")) ?></p>
              </div>
              <span><?= h((string) (($event["developer"]["email"] ?? "") ?: ($event["developer"]["name"] ?? "Developer"))) ?></span>
            </article>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </section>

        <section class="developer-dashboard-section" aria-label="Readiness and health">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title">Readiness & health</h2>
            <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["release_readiness"] ?? route("developer.panel.release_readiness"))) ?>">Open readiness & health</a>
          </div>
          <div class="developer-dashboard-status-grid">
            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head">
                <strong>Security audit</strong>
              </div>
              <h3><?= h((string) ($release["security_audit"]["failures"] ?? 0)) ?> failures</h3>
              <p><?= h((string) ($release["security_audit"]["warnings"] ?? 0)) ?> warnings, <?= h((string) ($release["security_audit"]["passed"] ?? 0)) ?> passed</p>
            </article>

            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head">
                <strong>Backup status</strong>
                <span class="developer-dashboard-ok"><?= ($release["backup_restore"]["ok"] ?? false) ? "OK" : "CHECK" ?></span>
              </div>
              <h3><?= h((string) ($release["backup_restore"]["failures"] ?? 0)) ?> blockers</h3>
              <p>Backup and restore runbook verification is part of production readiness.</p>
            </article>

            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head">
                <strong>Framework drift</strong>
              </div>
              <h3>FNLLA <?= h((string) ($release["framework_drift"]["framework_version"] ?? "unknown")) ?></h3>
              <p>Lock <?= ($release["framework_drift"]["lock_present"] ?? false) ? "present" : "missing" ?><?= ($release["framework_drift"]["lock_version"] ?? "") !== "" ? ": " . h((string) $release["framework_drift"]["lock_version"]) : "" ?></p>
            </article>

            <article class="developer-dashboard-status-card">
              <div class="developer-dashboard-card-head">
                <strong>Cache status</strong>
              </div>
              <ul class="developer-dashboard-check-list">
                <li>Cache: <?= ($release["cache"]["cache_writable"] ?? false) ? "OK" : "Needs attention" ?></li>
                <li>Sessions: <?= ($release["cache"]["sessions_writable"] ?? false) ? "OK" : "Needs attention" ?></li>
                <li>Queue: <?= ($release["cache"]["queue_writable"] ?? false) ? "OK" : "Needs attention" ?></li>
              </ul>
            </article>
          </div>
        </section>

        <section class="developer-dashboard-section" aria-label="Consent-aware integrations">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title">Adapter registry</h2>
            <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["integrations"] ?? route("developer.panel.integrations"))) ?>">Open integrations</a>
          </div>
          <div class="developer-dashboard-glance-table">
            <?php foreach ($integrations as $integration): ?>
            <div class="developer-dashboard-glance-row">
              <strong><?= h((string) ($integration["name"] ?? "Integration")) ?></strong>
              <span><?= h((string) ($integration["status"] ?? "disabled")) ?> · <?= h((string) ($integration["adapter"] ?? "optional adapter")) ?> · <?= h((string) ($integration["consent_event"] ?? "manual")) ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </section>

<?php require __DIR__ . "/panel-footer.php"; ?>
