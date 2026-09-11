<?php

declare(strict_types=1);

$developerPanelTitle = "Error Monitor";
$developerPanelLead = "FNLLA-owned runtime issue monitor with local fingerprints, request history and triage into debt or Kanban.";
$report = is_array($debugReport ?? null) ? $debugReport : [];
$environment = (array) ($report["environment"] ?? []);
$metrics = (array) ($report["metrics"] ?? []);
$historySummary = (array) ($report["history"] ?? []);
$issueSummary = (array) ($report["runtime_issues"] ?? []);
$issueByRoute = (array) ($issueSummary["by_route"] ?? []);
$issueByException = (array) ($issueSummary["by_exception"] ?? []);
$issueBySeverity = (array) ($issueSummary["by_severity"] ?? []);
$logs = (array) ($report["logs"] ?? []);
$storage = (array) ($report["storage"] ?? []);
$historyEntries = array_values((array) ($historyEntries ?? []));
$runtimeIssues = array_values((array) ($runtimeIssues ?? []));
$debtRevision = (int) (($debtState["revision"] ?? 0));
$formatMetric = static fn (mixed $value, string $suffix = ""): string => is_numeric($value) ? rtrim(rtrim((string) round((float) $value, 2), "0"), ".") . $suffix : "n/a";
$formatBool = static fn (mixed $value): string => (bool) $value ? "On" : "Off";
$formatTime = static function (mixed $value): string {
    $timestamp = is_numeric($value) ? (int) $value : strtotime((string) $value);

    return $timestamp > 0 ? gmdate("H:i:s", $timestamp) : "n/a";
};
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
require VIEW_ROOT . "/developer/panel-header.php";
?>

<section class="developer-dashboard-section developer-debug-live" aria-labelledby="debug-title" data-debug-live data-debug-live-url="<?= h(route("developer.panel.debug.live")) ?>">
  <div class="developer-panel-intro">
    <div class="developer-panel-intro-copy">
      <p class="feature-kicker">Operations / Observability / Error monitor</p>
      <h2 id="debug-title" class="developer-dashboard-section-title">FNLLA Error Monitor for local runtime issue triage.</h2>
      <p class="content-text mb-0">Error fingerprints, request timing and recent log entries stay local and bounded. Issue candidates are promoted to technical debt only after developer review.</p>
    </div>
    <div class="developer-panel-intro-actions">
      <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["analytics"] ?? route("developer.panel.analytics"))) ?>">Analytics</a>
      <a class="btn btn-outline btn-sm" href="<?= h((string) ($developerLinks["technical_debt"] ?? route("developer.panel.technical_debt"))) ?>">Technical debt</a>
      <span class="developer-dashboard-status is-active" data-debug-live-status>Live</span>
    </div>
  </div>

  <div class="developer-dashboard-status-grid">
    <article class="developer-dashboard-status-card">
      <div class="developer-dashboard-card-head">
        <strong>Runtime gates</strong>
        <span class="developer-dashboard-ok"><?= $debugAvailable ? "OPEN" : "BLOCKED" ?></span>
      </div>
      <h3><?= h((string) ($environment["app_env"] ?? app_environment())) ?></h3>
      <p>APP_DEBUG <?= h($formatBool($environment["app_debug"] ?? false)) ?>. Toolbar <?= h($formatBool($environment["toolbar_enabled"] ?? false)) ?>.</p>
      <p class="developer-dashboard-status <?= $debugAvailable ? "is-active" : "is-neutral" ?>"><?= $debugAvailable ? "Debug policy allows local tools" : "Environment policy blocks toolbar tools" ?></p>
    </article>

    <article class="developer-dashboard-status-card">
      <div class="developer-dashboard-card-head">
        <strong>Live requests</strong>
        <span class="developer-dashboard-ok" data-debug-total-requests><?= h((string) ($metrics["total_requests"] ?? 0)) ?></span>
      </div>
      <h3 data-debug-average-response><?= h($formatMetric($metrics["average_response_ms"] ?? null, "ms avg")) ?></h3>
      <p>Max <?= h($formatMetric($metrics["max_response_ms"] ?? null, "ms")) ?>. Errors <span data-debug-error-rate><?= h($formatMetric($metrics["error_rate"] ?? null, "%")) ?></span>.</p>
      <p class="developer-dashboard-status is-neutral">History entries: <span data-debug-history-count><?= h((string) ($historySummary["entries"] ?? count($historyEntries))) ?></span></p>
    </article>

    <article class="developer-dashboard-status-card">
      <div class="developer-dashboard-card-head">
        <strong>FNLLA Error Monitor</strong>
        <span class="developer-dashboard-ok"><?= ($issueSummary["enabled"] ?? false) ? "ON" : "OFF" ?></span>
      </div>
      <h3 data-debug-open-issues><?= h((string) ($issueSummary["open"] ?? count($runtimeIssues))) ?></h3>
      <p><span data-debug-issue-occurrences><?= h((string) ($issueSummary["occurrences"] ?? 0)) ?></span> unlinked recorded occurrences. <?= h((string) ($issueSummary["retention"] ?? "latest 100 fingerprints")) ?>.</p>
      <p class="developer-dashboard-status <?= ((int) ($issueSummary["open"] ?? 0) > 0) ? "is-neutral" : "is-active" ?>"><?= ((int) ($issueSummary["open"] ?? 0) > 0) ? "Review candidates" : "No open candidates" ?></p>
    </article>

    <article class="developer-dashboard-status-card">
      <div class="developer-dashboard-card-head">
        <strong>Diagnostic storage</strong>
        <span class="developer-dashboard-ok">LOCAL</span>
      </div>
      <p><code><?= h((string) ($storage["history_path"] ?? "storage/framework/developer/request-history.json")) ?></code></p>
      <p><code><?= h((string) ($storage["runtime_issues_path"] ?? "storage/framework/developer/runtime-issues.json")) ?></code></p>
    </article>
  </div>

  <?php if ($canManageDebug): ?>
  <form class="developer-debug-settings" method="post" action="<?= h(route("developer.panel.debug.save")) ?>">
    <?= csrf_field() ?>
    <label class="developer-workspace-check"><input type="checkbox" name="enabled" value="1" <?= $debugEnabled ? "checked" : "" ?> <?= !$debugAvailable ? "disabled" : "" ?>> <span><strong>Debug toolbar</strong><small>Profiler on public HTML surfaces.</small></span></label>
    <label class="developer-workspace-check"><input type="checkbox" name="history_enabled" value="1" <?= $historyEnabled ? "checked" : "" ?> <?= !$debugAvailable ? "disabled" : "" ?>> <span><strong>Request history</strong><small>Bounded developer-session request stream.</small></span></label>
    <label class="developer-workspace-check"><input type="checkbox" name="clear_history" value="1" <?= !$debugAvailable ? "disabled" : "" ?>> <span><strong>Clear history</strong><small>Erase stored request history on save.</small></span></label>
    <button class="btn btn-primary" type="submit">Save</button>
  </form>
  <?php endif; ?>
</section>

<section class="developer-dashboard-section" aria-labelledby="debug-history-title">
  <div class="developer-dashboard-section-head">
    <h2 id="debug-history-title" class="developer-dashboard-section-title">Live request stream</h2>
    <span class="developer-dashboard-refresh">Auto refresh: 4s</span>
  </div>
  <div class="developer-history-table">
    <table>
      <thead><tr><th scope="col">Time (UTC)</th><th scope="col">Method</th><th scope="col">Status</th><th scope="col">Time (ms)</th><th scope="col">Memory (MiB)</th></tr></thead>
      <tbody data-debug-history-body>
      <?php foreach ($historyEntries as $entry): ?>
        <tr><td><?= h($formatTime($entry["at"] ?? 0)) ?></td><td><?= h((string) ($entry["method"] ?? "")) ?></td><td><?= h((string) ($entry["status"] ?? "")) ?></td><td><?= h((string) ($entry["duration_ms"] ?? "")) ?></td><td><?= h(number_format(((float) ($entry["memory_bytes"] ?? 0)) / 1048576, 1)) ?></td></tr>
      <?php endforeach; ?>
      <?php if ($historyEntries === []): ?><tr data-debug-history-empty><td colspan="5">No requests</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="developer-dashboard-section" id="runtime-issues" aria-labelledby="runtime-issues-title">
  <div class="developer-dashboard-section-head">
    <h2 id="runtime-issues-title" class="developer-dashboard-section-title">Error monitor triage</h2>
    <span class="developer-dashboard-refresh"><?= h((string) count($runtimeIssues)) ?> open candidates</span>
  </div>

  <?php if ($runtimeIssues === []): ?>
  <article class="developer-project-log-empty">
    <p class="feature-kicker">No error monitor candidates</p>
    <h3>Unexpected 500-level exceptions will appear here after they are observed.</h3>
    <p class="content-text mb-0">HTTP 404, CSRF, validation and throttling responses are kept out of this list.</p>
  </article>
  <?php else: ?>
  <div class="developer-debug-issue-list">
    <?php foreach ($runtimeIssues as $issue): ?>
    <article class="developer-debug-issue">
      <div>
        <div class="developer-dashboard-card-head">
          <strong><?= h((string) ($issue["title"] ?? "Runtime issue")) ?></strong>
          <span class="developer-dashboard-ok"><?= h(strtoupper((string) ($issue["severity"] ?? "high"))) ?></span>
        </div>
        <p class="content-text">Seen <?= h((string) ($issue["occurrences"] ?? 1)) ?> times. Last request <code><?= h((string) ($issue["last_request_id"] ?? "")) ?></code>.</p>
        <div class="developer-project-log-facts">
          <span><b>Route</b> <?= h((string) ($issue["route"] ?? "unmatched")) ?></span>
          <span><b>Status</b> <?= h((string) ($issue["http_status"] ?? 500)) ?></span>
          <span><b>Class</b> <?= h((string) ($issue["exception_type"] ?? "Throwable")) ?></span>
          <span><b>Location</b> <?= h((string) ($issue["file"] ?? "")) ?>:<?= h((string) ($issue["line"] ?? 0)) ?></span>
          <span><b>Last seen</b> <?= h((string) ($issue["last_seen_at"] ?? "")) ?></span>
        </div>
      </div>
      <?php if ($canManageDebt): ?>
      <form class="developer-debug-promote-form" method="post" action="<?= h(route("developer.panel.debug.runtime_issues.promote")) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="revision" value="<?= h((string) $debtRevision) ?>">
        <input type="hidden" name="runtime_issue_id" value="<?= h((string) ($issue["id"] ?? "")) ?>">
        <label class="developer-workspace-check">
          <input type="checkbox" name="runtime_issue_create_workspace_task" value="1">
          <span>Create Kanban card</span>
        </label>
        <button class="btn btn-outline btn-sm" type="submit">Promote</button>
      </form>
      <?php endif; ?>
    </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<section class="developer-dashboard-section" aria-label="Debug aggregates">
  <div class="developer-dashboard-overview-grid developer-debug-aggregate-grid">
    <article class="developer-dashboard-card">
      <p class="feature-kicker">Status counts</p>
      <?php $renderMiniList((array) ($metrics["status_counts"] ?? []), "No status counts have been recorded yet."); ?>
    </article>

    <article class="developer-dashboard-card">
      <p class="feature-kicker">Methods</p>
      <?php $renderMiniList((array) ($metrics["method_counts"] ?? []), "No method counts have been recorded yet."); ?>
    </article>

    <article class="developer-dashboard-card">
      <p class="feature-kicker">Slow routes</p>
      <?php $renderMiniList((array) ($metrics["slow_routes"] ?? []), "No slow route candidates have been recorded yet."); ?>
    </article>

    <article class="developer-dashboard-card">
      <p class="feature-kicker">Issue routes</p>
      <?php $renderMiniList($issueByRoute, "No issue routes have been recorded yet."); ?>
    </article>

    <article class="developer-dashboard-card">
      <p class="feature-kicker">Issue classes</p>
      <?php $renderMiniList($issueByException, "No issue classes have been recorded yet."); ?>
    </article>

    <article class="developer-dashboard-card">
      <p class="feature-kicker">Severity mix</p>
      <?php $renderMiniList($issueBySeverity, "No severity buckets have been recorded yet."); ?>
    </article>
  </div>

  <div class="developer-dashboard-section-head">
    <h2 class="developer-dashboard-section-title">Recent error log</h2>
    <span class="developer-dashboard-refresh"><?= h((string) ($logs["path"] ?? "storage/logs/app.log")) ?></span>
  </div>
  <div class="developer-history-table">
    <table>
      <thead><tr><th scope="col">Time</th><th scope="col">Level</th><th scope="col">Message</th><th scope="col">Request</th></tr></thead>
      <tbody>
      <?php foreach ((array) ($logs["recent_errors"] ?? []) as $entry): ?>
        <tr><td><?= h((string) ($entry["time"] ?? "")) ?></td><td><?= h((string) ($entry["level"] ?? "ERROR")) ?></td><td><?= h((string) ($entry["message"] ?? "Application error")) ?></td><td><?= h((string) ($entry["request_id"] ?? "")) ?></td></tr>
      <?php endforeach; ?>
      <?php if ((array) ($logs["recent_errors"] ?? []) === []): ?><tr><td colspan="4">No recent error log entries.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<script nonce="<?= h(csp_nonce()) ?>">
(() => {
  const root = document.querySelector('[data-debug-live]');
  if (!root || !root.dataset.debugLiveUrl) return;
  const setText = (selector, value) => {
    const target = document.querySelector(selector);
    if (target) target.textContent = String(value);
  };
  const formatNumber = value => {
    const number = Number(value);
    return Number.isFinite(number) ? String(Math.round(number * 100) / 100) : '0';
  };
  const renderHistory = entries => {
    const body = document.querySelector('[data-debug-history-body]');
    if (!body) return;
    body.textContent = '';
    if (!Array.isArray(entries) || entries.length === 0) {
      const row = document.createElement('tr');
      const cell = document.createElement('td');
      cell.colSpan = 5;
      cell.textContent = 'No requests';
      row.appendChild(cell);
      body.appendChild(row);
      return;
    }
    for (const entry of entries.slice(0, 20)) {
      const row = document.createElement('tr');
      const time = Number(entry.at || 0) > 0 ? new Date(Number(entry.at) * 1000).toISOString().slice(11, 19) : 'n/a';
      const memory = formatNumber(Number(entry.memory_bytes || 0) / 1048576);
      for (const value of [time, entry.method || '', entry.status || '', entry.duration_ms || '', memory]) {
        const cell = document.createElement('td');
        cell.textContent = String(value);
        row.appendChild(cell);
      }
      body.appendChild(row);
    }
  };
  const refresh = async () => {
    const status = document.querySelector('[data-debug-live-status]');
    try {
      if (status) status.textContent = 'Refreshing';
      const response = await fetch(root.dataset.debugLiveUrl, {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      });
      if (!response.ok) throw new Error('Debug live request failed');
      const payload = await response.json();
      const report = payload.report || {};
      const metrics = report.metrics || {};
      const issues = report.runtime_issues || {};
      setText('[data-debug-total-requests]', metrics.total_requests || 0);
      setText('[data-debug-average-response]', `${formatNumber(metrics.average_response_ms)}ms avg`);
      setText('[data-debug-error-rate]', `${formatNumber(metrics.error_rate)}%`);
      setText('[data-debug-history-count]', (report.history || {}).entries || 0);
      setText('[data-debug-open-issues]', issues.open || 0);
      setText('[data-debug-issue-occurrences]', issues.occurrences || 0);
      renderHistory(payload.history || []);
      if (status) status.textContent = 'Live';
    } catch (error) {
      if (status) status.textContent = 'Paused';
    }
  };
  window.setInterval(() => {
    if (!document.hidden) refresh();
  }, 4000);
  refresh();
})();
</script>
<?php require VIEW_ROOT . "/developer/panel-footer.php"; ?>
