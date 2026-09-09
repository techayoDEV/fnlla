<?php

declare(strict_types=1);

$developerPanelTitle = "Analytics";
$developerPanelLead = "First-party traffic, conversion, consent and performance intelligence without third-party analytics scripts.";
$report = is_array($analyticsReport ?? null) ? (array) $analyticsReport : [];
$summary = (array) ($report["summary"] ?? []);
$charts = (array) ($report["charts"] ?? []);
$privacy = (array) ($report["privacy"] ?? []);
$settings = (array) ($report["settings"] ?? []);
$goals = (array) ($report["goals"] ?? []);
$insights = (array) ($report["insights"] ?? []);
$dataQuality = (array) ($report["data_quality"] ?? []);
$lastRequest = (array) ($report["last_request"] ?? []);
$lastConsent = (array) ($report["last_consent_event"] ?? []);
$formatMetric = static fn (mixed $value, string $suffix = ""): string => is_numeric($value) ? rtrim(rtrim((string) round((float) $value, 2), "0"), ".") . $suffix : "0" . $suffix;
$renderBarList = static function (array $items, string $empty): void { ?>
          <?php if ($items === []): ?>
          <p class="content-text mb-0"><?= h($empty) ?></p>
          <?php else: ?>
          <div class="developer-analytics-bars">
            <?php foreach ($items as $item): ?>
            <?php
                $label = (string) ($item["label"] ?? "");
                $count = (string) ($item["count"] ?? 0);
                $percent = max(2, (int) ($item["percent"] ?? 0));
                $tooltip = trim($label . ": " . $count . " / " . $percent . "% of this chart");
            ?>
            <div class="developer-analytics-bar-row" data-fnlla-tooltip="<?= h($tooltip) ?>" data-fnlla-tooltip-position="top" aria-label="<?= h($tooltip) ?>" tabindex="0">
              <div>
                <strong><?= h($label) ?></strong>
                <span><?= h($count) ?></span>
              </div>
              <i aria-hidden="true"><b style="width: <?= h((string) $percent) ?>%"></b></i>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
<?php };
$renderTimeline = static function (array $items, string $empty): void { ?>
          <?php if ($items === []): ?>
          <p class="content-text mb-0"><?= h($empty) ?></p>
          <?php else: ?>
          <div class="developer-analytics-timeline">
            <?php foreach ($items as $item): ?>
            <?php
                $label = (string) ($item["label"] ?? "");
                $fullLabel = (string) ($item["full_label"] ?? $label);
                $count = (string) ($item["count"] ?? 0);
                $percent = max(4, (int) ($item["percent"] ?? 0));
                $tooltip = trim($fullLabel . ": " . $count . " events / " . $percent . "% of this chart");
            ?>
            <span data-fnlla-tooltip="<?= h($tooltip) ?>" data-fnlla-tooltip-position="top" aria-label="<?= h($tooltip) ?>" tabindex="0">
              <i style="height: <?= h((string) $percent) ?>%"></i>
              <small><?= h($label) ?></small>
            </span>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
<?php };
$metricCards = [
    [
        "label" => "Page views",
        "value" => (string) ($summary["page_views"] ?? 0),
        "detail" => (string) ($summary["total_requests"] ?? 0) . " total requests",
        "status" => ($report["enabled"] ?? false) ? "ON" : "OFF",
        "tone" => "blue",
    ],
    [
        "label" => "Response time",
        "value" => $formatMetric($summary["average_response_ms"] ?? 0, "ms"),
        "detail" => "Max " . $formatMetric($summary["max_response_ms"] ?? 0, "ms") . " recorded",
        "status" => "P95-ready",
        "tone" => "green",
    ],
    [
        "label" => "Errors",
        "value" => (string) ($summary["error_requests"] ?? 0),
        "detail" => $formatMetric($summary["error_rate"] ?? 0, "%") . " error rate",
        "status" => "4xx/5xx",
        "tone" => "red",
    ],
    [
        "label" => "Conversions",
        "value" => (string) ($summary["conversion_events"] ?? 0),
        "detail" => $formatMetric($summary["conversion_rate"] ?? 0, "%") . " page-view to form rate",
        "status" => "LOCAL",
        "tone" => "amber",
    ],
    [
        "label" => "Consent signal",
        "value" => $formatMetric($summary["analytics_consent_rate"] ?? 0, "%"),
        "detail" => (string) ($summary["consent_events"] ?? 0) . " consent events recorded",
        "status" => "1P",
        "tone" => "green",
    ],
];
$qualityRows = [
    "Storage" => (string) ($dataQuality["storage_path"] ?? "storage/framework/metrics.json"),
    "Updated" => (string) (($dataQuality["updated_at_utc"] ?? "") ?: "not recorded yet"),
    "Retention" => (string) ($settings["retention_days"] ?? 90) . " days",
    "Sample" => (string) ($settings["sample_rate"] ?? 100) . "%",
];
$journeyRows = [
    "Visitor source" => "Host-only referrer buckets, campaign-safe direct/search/social/referral groups.",
    "Route movement" => "Page views, methods, status codes and slow routes are tracked as aggregate counters.",
    "Conversion path" => "Successful form routes and configured goals are measured without personal identifiers.",
    "Consent posture" => (string) ($dataQuality["consent_rate_source"] ?? "waiting for consent events"),
];
require __DIR__ . "/panel-header.php";
?>

        <section class="developer-dashboard-section" aria-label="Analytics summary">
          <div class="developer-analytics-commandbar">
            <div>
              <p class="feature-kicker">Analytics command center</p>
              <h2 class="developer-dashboard-section-title">FNLLA analytics <span class="developer-info-tip" tabindex="0" aria-label="Local analytics stores aggregate counters inside the project.">i<span>This is FNLLA-owned aggregate analytics. No external analytics vendor is required for these charts.</span></span></h2>
              <p class="content-text mb-0">Aggregate traffic, route performance and conversion signals without raw IP addresses, raw user agents or browser fingerprinting.</p>
            </div>
            <div class="developer-analytics-commandbar-panel">
              <strong>Data contract</strong>
              <span>First-party aggregate data</span>
              <span><?= h((string) ($privacy["mode"] ?? "privacy-light")) ?> / <?= ($settings["track_query_strings"] ?? false) ? "query strings tracked" : "query strings off" ?></span>
            </div>
          </div>

          <section class="developer-analytics-blueprint" aria-label="Analytics blueprint">
            <div class="developer-analytics-blueprint-grid" aria-hidden="true">
              <span></span>
              <span></span>
              <span></span>
              <span></span>
            </div>
            <div class="developer-analytics-blueprint-copy">
              <p class="feature-kicker">Blueprint view</p>
              <h3>Traffic, timing and consent signals mapped as an operating plan.</h3>
              <p class="content-text mb-0">The charts below stay first-party and aggregate, with route movement, source shape and response time shown as readable project signals.</p>
            </div>
            <div class="developer-analytics-blueprint-diagram" aria-hidden="true">
              <span class="developer-analytics-blueprint-node is-source"></span>
              <span class="developer-analytics-blueprint-node is-route"></span>
              <span class="developer-analytics-blueprint-node is-performance"></span>
              <span class="developer-analytics-blueprint-line is-main"></span>
              <span class="developer-analytics-blueprint-line is-branch"></span>
            </div>
          </section>

          <div class="developer-analytics-metric-grid">
            <?php foreach ($metricCards as $card): ?>
            <article class="developer-analytics-metric-card is-<?= h((string) $card["tone"]) ?>">
              <div class="developer-dashboard-card-head">
                <strong><?= h((string) $card["label"]) ?></strong>
                <span class="developer-dashboard-ok"><?= h((string) $card["status"]) ?></span>
              </div>
              <h3><?= h((string) $card["value"]) ?></h3>
              <p><?= h((string) $card["detail"]) ?></p>
            </article>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="developer-dashboard-section" aria-label="Traffic timeline">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title">Traffic timeline</h2>
            <span class="developer-dashboard-refresh">First-party aggregate data</span>
          </div>
          <div class="developer-analytics-chart-grid">
            <article class="developer-dashboard-card developer-analytics-chart-card">
              <p class="feature-kicker">Last 14 days</p>
              <?php $renderTimeline((array) ($charts["daily_page_views"] ?? []), "No daily page-view history has been recorded yet."); ?>
            </article>
            <article class="developer-dashboard-card developer-analytics-chart-card">
              <p class="feature-kicker">Last 24 hours</p>
              <?php $renderTimeline((array) ($charts["hourly_page_views"] ?? []), "No hourly page-view history has been recorded yet."); ?>
            </article>
            <article class="developer-dashboard-card developer-analytics-chart-card">
              <p class="feature-kicker">Consent trend</p>
              <?php $renderTimeline((array) ($charts["daily_consent_events"] ?? []), "No consent trend has been recorded yet."); ?>
            </article>
          </div>
        </section>

        <section class="developer-dashboard-section" aria-label="Behaviour and performance analytics">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title">Acquisition and performance</h2>
            <span class="developer-dashboard-refresh">No raw IP or fingerprinting</span>
          </div>
          <div class="developer-analytics-workbench">
            <article class="developer-dashboard-card developer-dashboard-card-wide">
              <p class="feature-kicker">Top routes</p>
              <?php $renderBarList((array) ($charts["top_routes"] ?? []), "No routes have been recorded yet."); ?>
            </article>
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Traffic source</p>
              <?php $renderBarList((array) ($charts["source_counts"] ?? []), "No traffic sources have been recorded yet."); ?>
            </article>
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Response time by route</p>
              <?php $renderBarList((array) ($charts["route_response_times"] ?? []), "No route timing averages have been recorded yet."); ?>
            </article>
            <article class="developer-dashboard-card">
              <p class="feature-kicker">HTTP status</p>
              <?php $renderBarList((array) ($charts["status_counts"] ?? []), "No status counts have been recorded yet."); ?>
            </article>
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Referrers</p>
              <?php $renderBarList((array) ($charts["referrers"] ?? []), "No referrers have been recorded yet."); ?>
            </article>
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Devices</p>
              <?php $renderBarList((array) ($charts["device_counts"] ?? []), "Device aggregation is empty or disabled."); ?>
            </article>
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Slow routes</p>
              <?php $renderBarList((array) ($charts["slow_routes"] ?? []), "No slow routes have crossed the configured threshold."); ?>
            </article>
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Form routes</p>
              <?php $renderBarList((array) ($charts["form_routes"] ?? []), "No successful form submissions have been recorded yet."); ?>
            </article>
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Methods</p>
              <?php $renderBarList((array) ($charts["method_counts"] ?? []), "No HTTP method counts have been recorded yet."); ?>
            </article>
          </div>
        </section>

        <section class="developer-dashboard-section" aria-label="Analytics replacement signals">
          <div class="developer-dashboard-section-head">
            <h2 class="developer-dashboard-section-title">Replacement signals</h2>
            <span class="developer-dashboard-refresh">Traffic, product and privacy in one local dataset</span>
          </div>
          <div class="developer-dashboard-overview-grid">
            <article class="developer-dashboard-card developer-dashboard-card-wide">
              <p class="feature-kicker">Measurement model</p>
              <div class="developer-dashboard-glance-table">
                <?php foreach ($journeyRows as $label => $value): ?>
                <div class="developer-dashboard-glance-row">
                  <strong><?= h((string) $label) ?></strong>
                  <span><?= h((string) $value) ?></span>
                </div>
                <?php endforeach; ?>
              </div>
            </article>
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Consent counts</p>
              <?php $renderBarList((array) ($charts["consent_counts"] ?? []), "No consent choices have been recorded yet."); ?>
            </article>
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Last consent event</p>
              <div class="developer-dashboard-glance-table">
                <?php foreach (["state", "analytics", "marketing", "recorded_at_utc"] as $key): ?>
                <div class="developer-dashboard-glance-row">
                  <strong><?= h(str_replace("_", " ", ucfirst($key))) ?></strong>
                  <span><?= h((string) ($lastConsent[$key] ?? "n/a")) ?></span>
                </div>
                <?php endforeach; ?>
              </div>
            </article>
          </div>
        </section>

        <section class="developer-dashboard-section" aria-label="Analytics goals and insights">
          <div class="developer-dashboard-overview-grid">
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Goals</p>
              <?php if ($goals === []): ?>
              <p class="content-text mb-0">No internal analytics goals are configured yet.</p>
              <?php else: ?>
              <div class="developer-analytics-goals">
                <?php foreach ($goals as $goal): ?>
                <div>
                  <span><?= h((string) ($goal["label"] ?? "Goal")) ?></span>
                  <strong><?= h((string) ($goal["value"] ?? 0)) ?></strong>
                </div>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </article>
            <article class="developer-dashboard-card developer-dashboard-card-wide">
              <p class="feature-kicker">Insights</p>
              <ul class="developer-analytics-insights">
                <?php foreach ($insights as $insight): ?>
                <li><?= h((string) $insight) ?></li>
                <?php endforeach; ?>
              </ul>
            </article>
          </div>
        </section>

        <section class="developer-dashboard-section" aria-label="Analytics settings">
          <div class="developer-dashboard-section-head">
              <h2 class="developer-dashboard-section-title">Internal analytics settings <span class="developer-info-tip" tabindex="0" aria-label="These settings affect local FNLLA metrics only.">i<span>These switches configure first-party storage, sampling, retention and bot filtering for this project.</span></span></h2>
            <span class="developer-dashboard-refresh">Saved to project .env</span>
          </div>
          <form class="form developer-analytics-settings-form" action="<?= h(route("developer.panel.analytics.settings")) ?>" method="post">
            <?= csrf_field() ?>
            <label class="developer-analytics-toggle">
              <input type="hidden" name="observability_metrics_enabled" value="0">
              <input type="checkbox" name="observability_metrics_enabled" value="1" <?= ($settings["metrics_enabled"] ?? true) ? "checked" : "" ?>>
              <span><strong>Request metrics</strong><small>Collect aggregate request counters and response timing.</small></span>
            </label>
            <label class="developer-analytics-toggle">
              <input type="hidden" name="observability_analytics_enabled" value="0">
              <input type="checkbox" name="observability_analytics_enabled" value="1" <?= ($settings["analytics_enabled"] ?? true) ? "checked" : "" ?>>
              <span><strong>Analytics cockpit</strong><small>Record aggregate page-view, source, form and route analytics.</small></span>
            </label>
            <label class="developer-analytics-toggle">
              <input type="hidden" name="observability_analytics_bot_filtering" value="0">
              <input type="checkbox" name="observability_analytics_bot_filtering" value="1" <?= ($settings["bot_filtering"] ?? true) ? "checked" : "" ?>>
              <span><strong>Bot filtering</strong><small>Exclude obvious bot user agents from page-view analytics.</small></span>
            </label>
            <label class="developer-analytics-toggle">
              <input type="hidden" name="observability_analytics_device_detection" value="0">
              <input type="checkbox" name="observability_analytics_device_detection" value="1" <?= ($settings["device_detection"] ?? true) ? "checked" : "" ?>>
              <span><strong>Device aggregation</strong><small>Store desktop, mobile, tablet or unknown buckets only.</small></span>
            </label>
            <label class="developer-analytics-toggle">
              <input type="hidden" name="observability_analytics_track_query_strings" value="0">
              <input type="checkbox" name="observability_analytics_track_query_strings" value="1" <?= ($settings["track_query_strings"] ?? false) ? "checked" : "" ?>>
              <span><strong>Query strings</strong><small>Keep disabled unless the project explicitly needs route-level query analytics.</small></span>
            </label>
            <div class="form-group">
              <label class="label" for="observability-analytics-retention">Retention days</label>
              <input class="input" id="observability-analytics-retention" name="observability_analytics_retention_days" type="number" min="1" max="730" value="<?= h((string) ($settings["retention_days"] ?? 90)) ?>">
            </div>
            <div class="form-group">
              <label class="label" for="observability-analytics-sample">Sample rate</label>
              <input class="input" id="observability-analytics-sample" name="observability_analytics_sample_rate" type="number" min="1" max="100" value="<?= h((string) ($settings["sample_rate"] ?? 100)) ?>">
            </div>
            <div class="form-group">
              <label class="label" for="observability-slow-threshold">Slow route threshold ms</label>
              <input class="input" id="observability-slow-threshold" name="observability_slow_route_threshold_ms" type="number" min="50" max="30000" value="<?= h((string) ($settings["slow_route_threshold_ms"] ?? 750)) ?>">
            </div>
            <button class="btn btn-primary" type="submit">Save analytics settings</button>
          </form>
        </section>

        <section class="developer-dashboard-section" aria-label="Analytics details">
          <div class="developer-analytics-detail-grid">
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Last measured request</p>
              <div class="developer-dashboard-glance-table">
                <?php foreach (["method", "path", "route", "status", "duration_ms", "recorded_at_utc"] as $key): ?>
                <div class="developer-dashboard-glance-row">
                  <strong><?= h(str_replace("_", " ", ucfirst($key))) ?></strong>
                  <span><?= h((string) ($lastRequest[$key] ?? "n/a")) ?></span>
                </div>
                <?php endforeach; ?>
              </div>
            </article>
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Data quality</p>
              <div class="developer-dashboard-glance-table">
                <?php foreach ($qualityRows as $label => $value): ?>
                <div class="developer-dashboard-glance-row">
                  <strong><?= h((string) $label) ?></strong>
                  <span><?= h((string) $value) ?></span>
                </div>
                <?php endforeach; ?>
              </div>
            </article>
          </div>
        </section>

        <section class="developer-dashboard-section" aria-label="Analytics privacy and data quality">
          <div class="developer-dashboard-overview-grid">
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Privacy model</p>
              <p class="content-text mb-0"><?= h((string) ($privacy["mode"] ?? "privacy-light")) ?>, host-only referrers, no raw IP, no raw user agent and no browser fingerprinting.</p>
              <p class="developer-dashboard-status is-active">Internal analytics only</p>
            </article>
            <article class="developer-dashboard-card">
              <p class="feature-kicker">Consent event</p>
              <p><code><?= h((string) ($privacy["analytics_consent_event"] ?? "fnlla:analytics-consent-granted")) ?></code></p>
              <p class="content-text mb-0">Projects can listen to this event before enabling first-party client-side measurements.</p>
            </article>
          </div>
        </section>

<?php require __DIR__ . "/panel-footer.php"; ?>
