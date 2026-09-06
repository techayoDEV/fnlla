<?php

declare(strict_types=1);

namespace Fnlla\Php\Observability;

use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Support\LockedJsonStore;

final class RequestHistory
{
    public function enabled(): bool
    {
        if (!(new DebugToolbar())->authorized()) {
            return false;
        }
        try {
            return (bool) ($this->store()->read()["enabled"] ?? config("debug.history.enabled", false));
        } catch (\Throwable) {
            return false;
        }
    }

    public function configure(bool $enabled, bool $clear = false): void
    {
        if (!(new DebugToolbar())->authorized() || !developer_access()->can("panel.settings.write")) {
            throw new \RuntimeException("Request history management is forbidden.");
        }
        $this->store()->update(fn (array $state): array => [
            "enabled" => $enabled,
            "entries" => !$enabled || $clear ? [] : $this->retained($state),
        ]);
    }

    public function record(Request $request, Response $response, float $durationMs): void
    {
        if (!$this->enabled()) {
            return;
        }
        // Whitelist only aggregate values: URLs, IDs, headers, SQL and bodies can contain secrets.
        $method = $request->method();
        $entry = [
            "at" => time(),
            "method" => in_array($method, ["GET", "HEAD", "POST", "PUT", "PATCH", "DELETE", "OPTIONS"], true) ? $method : "OTHER",
            "status" => $response->status(),
            "duration_ms" => is_finite($durationMs) ? round(max(0, $durationMs), 2) : 0,
            "memory_bytes" => memory_get_peak_usage(true),
        ];
        try {
            $this->store()->update(function (array $state) use ($entry): array {
                // A concurrent disable must not be undone by an in-flight request.
                if (!(bool) ($state["enabled"] ?? config("debug.history.enabled", false))) {
                    return $state;
                }
                $state["entries"] = $this->retained($state);
                $state["entries"][] = $entry;
                $state["entries"] = array_slice($state["entries"], -$this->limit());
                return $state;
            });
        } catch (\Throwable) {
            // Optional telemetry must never change the application response on storage failure.
        }
    }

    public function entries(): array
    {
        if (!$this->enabled()) {
            return [];
        }
        $state = $this->store()->update(function (array $state): array {
            $state["entries"] = $this->retained($state);
            return $state;
        });
        return array_reverse($state["entries"]);
    }

    private function retained(array $state): array
    {
        $cutoff = time() - max(1, min(86400, (int) config("debug.history.retention_seconds", 3600)));
        $entries = array_filter((array) ($state["entries"] ?? []), static fn (mixed $entry): bool =>
            is_array($entry) && (int) ($entry["at"] ?? 0) > $cutoff && (int) ($entry["at"] ?? 0) <= time()
        );
        return array_slice(array_values($entries), -$this->limit());
    }

    private function limit(): int
    {
        return max(1, min(1000, (int) config("debug.history.max_entries", 200)));
    }

    private function store(): LockedJsonStore
    {
        return new LockedJsonStore((string) config("debug.history.path", storage_path("framework/developer/request-history.json")));
    }
}
