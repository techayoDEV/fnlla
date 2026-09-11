<?php

declare(strict_types=1);

namespace Fnlla\Php\Observability;

use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Support\LockedJsonStore;

final class DebugToolbar
{
    public function available(): bool
    {
        return (bool) config("app.debug", false)
            && in_array((string) config("app.environment", "production"), ["local", "development", "testing"], true);
    }

    public function enabled(): bool
    {
        if (!$this->available()) {
            return false;
        }
        try {
            return (bool) ($this->store()->read()["enabled"] ?? config("debug.toolbar", false));
        } catch (\Throwable) {
            return false;
        }
    }

    public function setEnabled(bool $enabled, string $actor): void
    {
        if ($enabled && !$this->available()) {
            throw new \RuntimeException("Debug toolbar is unavailable in this environment.");
        }
        $this->store()->update(static fn (array $state): array => [
            "enabled" => $enabled, "updated_by" => $actor, "updated_at" => gmdate(DATE_ATOM),
        ]);
    }

    public function authorized(): bool
    {
        if (!$this->available() || !\Fnlla\Php\Support\ProjectProfile::hasPanel()) {
            return false;
        }
        $access = developer_access();
        return $access->enabled() && $access->isUnlocked() && $access->can("operations.view");
    }

    public function begin(): void
    {
        QueryTelemetry::reset($this->authorized() && $this->enabled());
    }

    public function decorate(Request $request, Response $response, float $durationMs): Response
    {
        $data = QueryTelemetry::snapshot();
        QueryTelemetry::reset();
        if (!$this->authorized() || !$this->enabled() || !$this->isPublicDebugSurface($request) || $request->method() === "HEAD"
            || $response->status() < 200 || in_array($response->status(), [204, 205, 304], true)
            || strtolower((string) $request->header("X-Requested-With", "")) === "xmlhttprequest") {
            return $response;
        }
        $headers = array_change_key_case($response->headers(), CASE_LOWER);
        if (!str_starts_with(strtolower((string) ($headers["content-type"] ?? "")), "text/html")
            || isset($headers["content-disposition"]) || isset($headers["content-encoding"])) {
            return $response;
        }
        $position = strripos($response->body(), "</body>");
        if ($position === false) {
            return $response;
        }
        $route = (string) ($_SERVER["FNLLA_ROUTE_NAME"] ?? "unnamed");
        $path = "/" . trim($request->path(), "/");
        $path = $path === "/" ? "/" : $path;
        $memoryMiB = memory_get_peak_usage(true) / 1048576;
        $databaseMs = (float) ($data["duration_ms"] ?? 0.0);
        $queryCount = (int) ($data["count"] ?? 0);
        $liveUrl = route("developer.panel.debug.live");
        $debugUrl = route("developer.panel.debug");
        $summary = sprintf("FNLLA Debug | %s %d | %.1f ms | %.1f MiB | %d queries", $request->method(), $response->status(), $durationMs, $memoryMiB, $queryCount);
        $html = '<link rel="stylesheet" href="' . h(asset("assets/debug-toolbar.css")) . '">'
            . '<details id="fnlla-debug-toolbar"><summary aria-label="' . h($summary) . '">'
            . '<span class="fnlla-debug-brand">FNLLA Debug</span>'
            . '<span class="fnlla-debug-pill ' . ($response->status() >= 500 ? "is-danger" : ($response->status() >= 400 ? "is-warning" : "is-success")) . '">' . h($request->method()) . ' ' . h((string) $response->status()) . '</span>'
            . '<span>' . h(number_format($durationMs, 1)) . ' ms</span>'
            . '<span>' . h((string) $queryCount) . ' queries</span>'
            . '<span>' . h(number_format($memoryMiB, 1)) . ' MiB</span>'
            . '<span class="fnlla-debug-pill is-success" data-fnlla-debug-live-status>Live ready</span>'
            . '<span class="fnlla-debug-pill is-success" data-fnlla-debug-live-errors>0% errors</span>'
            . '<span class="fnlla-debug-pill is-success" data-fnlla-debug-live-issues>0 issues</span>'
            . '</summary><div class="fnlla-debug-content">'
            . '<div class="fnlla-debug-grid">'
            . '<section><h2>Request</h2><dl><dt>Route</dt><dd>' . h($route) . '</dd><dt>Path</dt><dd>' . h($path) . '</dd><dt>Request ID</dt><dd>' . h($request->requestId()) . '</dd></dl></section>'
            . '<section><h2>Runtime</h2><dl><dt>PHP</dt><dd>' . h(PHP_VERSION) . '</dd><dt>Environment</dt><dd>' . h(app_environment()) . '</dd><dt>Peak memory</dt><dd>' . h(number_format($memoryMiB, 1)) . ' MiB</dd></dl></section>'
            . '<section><h2>Database</h2><dl><dt>Queries</dt><dd>' . h((string) $queryCount) . '</dd><dt>Execution</dt><dd>' . h(number_format($databaseMs, 2)) . ' ms</dd><dt>Values</dt><dd>Excluded</dd></dl></section>'
            . '<section><h2>Live</h2><dl><dt>Total</dt><dd data-fnlla-debug-live-total>Waiting</dd><dt>Issues</dt><dd data-fnlla-debug-live-open>Waiting</dd><dt>Panel</dt><dd><a href="' . h($debugUrl) . '">Open Debug</a></dd></dl></section>'
            . '</div>'
            . '<table><caption>Query execution (first 100, values excluded)</caption><thead><tr><th>Operation</th><th>Duration</th><th>Result</th></tr></thead><tbody>';
        foreach ($data["queries"] as $query) {
            $html .= '<tr><td>' . h($query["operation"]) . '</td><td>' . h((string) $query["duration_ms"]) . ' ms</td><td>' . ($query["ok"] ? "OK" : "Failed") . '</td></tr>';
        }
        if ($data["queries"] === []) {
            $html .= '<tr><td colspan="3">No database queries were captured for this request.</td></tr>';
        }
        $html .= '</tbody></table></div></details>'
            . '<script nonce="' . h(csp_nonce()) . '">'
            . '(function(){var endpoint=' . json_encode($liveUrl, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . ';'
            . 'var text=function(s,v){var el=document.querySelector(s);if(el)el.textContent=String(v);};'
            . 'var tone=function(s,c){var el=document.querySelector(s);if(el){el.classList.remove("is-success","is-warning","is-danger");el.classList.add(c);}};'
            . 'var fmt=function(v){var n=Number(v);return Number.isFinite(n)?String(Math.round(n*100)/100):"0";};'
            . 'var refresh=function(){var status=document.querySelector("[data-fnlla-debug-live-status]");if(status)status.textContent="Refreshing";'
            . 'fetch(endpoint,{credentials:"same-origin",headers:{"Accept":"application/json","X-Requested-With":"XMLHttpRequest"}}).then(function(r){if(!r.ok)throw new Error("debug live failed");return r.json();}).then(function(p){var report=p.report||{};var metrics=report.metrics||{};var issues=report.runtime_issues||{};'
            . 'var errorRate=Number(metrics.error_rate||0);var openIssues=Number(issues.open||0);text("[data-fnlla-debug-live-status]","Live");tone("[data-fnlla-debug-live-status]","is-success");text("[data-fnlla-debug-live-errors]",fmt(errorRate)+"% errors");tone("[data-fnlla-debug-live-errors]",errorRate>0?"is-danger":"is-success");text("[data-fnlla-debug-live-issues]",openIssues+" issues");tone("[data-fnlla-debug-live-issues]",openIssues>0?"is-warning":"is-success");text("[data-fnlla-debug-live-total]",(metrics.total_requests||0)+" requests");text("[data-fnlla-debug-live-open]",openIssues+" open");'
            . '}).catch(function(){text("[data-fnlla-debug-live-status]","Live paused");tone("[data-fnlla-debug-live-status]","is-warning");});};'
            . 'window.setInterval(function(){if(!document.hidden)refresh();},5000);refresh();})();'
            . '</script>';
        $body = substr_replace($response->body(), $html, $position, 0);
        return $response->withBody($body)->withHeader("Cache-Control", "private, no-store")
            ->withoutHeader("ETag")->withoutHeader("Last-Modified")->withoutHeader("Content-Length");
    }

    private function store(): LockedJsonStore
    {
        return new LockedJsonStore((string) config("debug.state_path", storage_path("framework/developer/debug-toolbar.json")));
    }

    private function isPublicDebugSurface(Request $request): bool
    {
        $route = (string) ($_SERVER["FNLLA_ROUTE_NAME"] ?? "");

        if ($route !== "" && (
            str_starts_with($route, "developer.")
            || str_starts_with($route, "customer.")
            || str_starts_with($route, "maintenance.")
            || str_starts_with($route, "api.")
            || in_array($route, ["developer.setup", "health"], true)
        )) {
            return false;
        }

        $path = "/" . trim($request->path(), "/");
        $path = $path === "/" ? "/" : $path;
        foreach (["/developer", "/developer-panel-setup", "/customer", "/maintenance", "/api", "/health", "/fnlla/consent"] as $privatePrefix) {
            if ($path === $privatePrefix || str_starts_with($path, rtrim($privatePrefix, "/") . "/")) {
                return false;
            }
        }

        return true;
    }
}
