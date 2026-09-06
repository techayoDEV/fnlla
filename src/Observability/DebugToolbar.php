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
        if (!$this->authorized() || !$this->enabled() || $request->method() === "HEAD"
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
        $summary = sprintf("FNLLA Debug | %s %d | %.1f ms | %.1f MiB | %d queries", $request->method(), $response->status(), $durationMs, memory_get_peak_usage(true) / 1048576, $data["count"]);
        $html = '<link rel="stylesheet" href="' . h(asset("assets/debug-toolbar.css")) . '">'
            . '<details id="fnlla-debug-toolbar"><summary>' . h($summary) . '</summary><div class="fnlla-debug-content">'
            . '<dl><dt>Route</dt><dd>' . h($route) . '</dd><dt>Request ID</dt><dd>' . h($request->requestId()) . '</dd>'
            . '<dt>Database execution</dt><dd>' . h((string) $data["duration_ms"]) . ' ms</dd><dt>PHP</dt><dd>' . h(PHP_VERSION) . '</dd></dl>'
            . '<table><caption>Query execution (first 100, values excluded)</caption><thead><tr><th>Operation</th><th>Duration</th><th>Result</th></tr></thead><tbody>';
        foreach ($data["queries"] as $query) {
            $html .= '<tr><td>' . h($query["operation"]) . '</td><td>' . h((string) $query["duration_ms"]) . ' ms</td><td>' . ($query["ok"] ? "OK" : "Failed") . '</td></tr>';
        }
        $html .= '</tbody></table></div></details>';
        $body = substr_replace($response->body(), $html, $position, 0);
        return $response->withBody($body)->withHeader("Cache-Control", "private, no-store")
            ->withoutHeader("ETag")->withoutHeader("Last-Modified")->withoutHeader("Content-Length");
    }

    private function store(): LockedJsonStore
    {
        return new LockedJsonStore((string) config("debug.state_path", storage_path("framework/developer/debug-toolbar.json")));
    }
}
