<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA SUPPORT SOURCE
File: src\Support\SecurityAuditReport.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Builds configuration-focused security posture checks for FNLLA deployments.
*/

namespace Fnlla\Php\Support;

final class SecurityAuditReport
{
    public function build(): array
    {
        $isProduction = app_environment() === "production";
        $allowedOrigins = (array) config("cors.allowed_origins", []);
        $supportsCredentials = (bool) config("cors.supports_credentials", false);
        $appUrl = trim((string) config("app.base_url", ""));
        $securityHeaders = (array) config("http.security_headers", []);
        $csp = trim((string) ($securityHeaders["Content-Security-Policy"] ?? ""));
        $hsts = trim((string) ($securityHeaders["Strict-Transport-Security"] ?? ""));
        $trustedHosts = (array) config("security.trusted_hosts", []);
        $mailDriver = (string) config("mail.default", "log");
        $nativeMailEnabled = (bool) config("mail.native_enabled", false);
        $mailHttpEndpoint = trim((string) config("mail.http.endpoint", ""));
        $mailHttpRequiresHttps = (bool) config("mail.http.require_https", true);
        $mailHttpAllowedHosts = (array) config("mail.http.allowed_hosts", []);
        $runtimeAiEnabled = (bool) config("ai.runtime.enabled", false);
        $runtimeAiDriver = (string) config("ai.runtime.driver", "local");

        $checks = [
            $this->check("debug_disabled", !$isProduction || !app_debug(), "fail", "APP_DEBUG must be false in production."),
            $this->check("https_app_url", !$isProduction || str_starts_with(strtolower($appUrl), "https://"), "warning", "Production APP_URL should use HTTPS."),
            $this->check("trusted_hosts", !$isProduction || $trustedHosts !== [], "warning", "Production deployments should configure TRUSTED_HOSTS."),
            $this->check("secure_session_cookie", !$isProduction || (bool) config("session.secure", false), "warning", "Production session cookies should be Secure."),
            $this->check("http_only_session_cookie", (bool) config("session.http_only", true), "fail", "Session cookies should be HttpOnly."),
            $this->check("strict_session_mode", (bool) config("session.strict_mode", true), "warning", "Strict session mode helps prevent session fixation."),
            $this->check("same_site_session_cookie", in_array((string) config("session.same_site", "Lax"), ["Lax", "Strict", "None"], true), "fail", "Session SameSite value must be explicit and valid."),
            $this->check("csrf_rotation", (int) config("security.csrf.rotate_after_minutes", 120) > 0, "fail", "CSRF tokens should rotate on a bounded schedule."),
            $this->check("request_body_limit", (int) config("security.request.max_body_bytes", 0) > 0, "fail", "Request body size must be bounded."),
            $this->check("upload_limit", (int) config("security.uploads.max_file_bytes", 0) > 0, "fail", "Upload size must be bounded."),
            $this->check("upload_mime_allowlist", (array) config("security.uploads.allowed_mime_types", []) !== [], "fail", "Uploads should use an explicit MIME allow-list."),
            $this->check("credentialed_cors_explicit", !($supportsCredentials && in_array("*", $allowedOrigins, true)), "fail", "Credentialed CORS must not use wildcard origins."),
            $this->check("csp_present", $csp !== "", "warning", "A Content-Security-Policy header should be configured."),
            $this->check("hsts_for_https", !$isProduction || !str_starts_with(strtolower($appUrl), "https://") || $hsts !== "", "warning", "Production HTTPS deployments should configure Strict-Transport-Security."),
            $this->check("browser_security_headers", $this->hasBrowserSecurityHeaders($securityHeaders), "warning", "Browser security headers should include content type, referrer, frame and permissions policies."),
            $this->check("php_display_errors_disabled", !$isProduction || !filter_var(ini_get("display_errors"), FILTER_VALIDATE_BOOL), "warning", "Production PHP display_errors should be disabled."),
            $this->check("json_cache_serializer", (string) config("cache.serializer", "json") === "json", "warning", "JSON cache serialization is the preferred forward-safe cache format."),
            $this->check("trusted_proxies_explicit", !$isProduction || (array) config("http.trusted_proxies", []) !== [], "warning", "Production proxy deployments should configure TRUSTED_PROXIES explicitly."),
            $this->check("mail_transport", !$isProduction || $mailDriver !== "log", "warning", "Production form notifications should not use the log mail transport."),
            $this->check("native_mail_explicit", $mailDriver !== "native" || $nativeMailEnabled, "fail", "Native PHP mail must be explicitly enabled after the server transport is configured."),
            $this->check("http_mail_transport", $mailDriver !== "http" || ($mailHttpEndpoint !== "" && $mailHttpRequiresHttps), "fail", "HTTP mail transport must have an endpoint and require HTTPS outside localhost."),
            $this->check("http_mail_host_allowlist", !$isProduction || $mailDriver !== "http" || $mailHttpAllowedHosts !== [], "warning", "Production HTTP mail transport should pin allowed provider or relay hosts."),
            $this->check("runtime_ai_bundle", !$runtimeAiEnabled || $this->runtimeAiBundleIsPresent(), "fail", "Runtime AI requires the integrated private runtime intelligence bundle."),
            $this->check("runtime_ai_local_driver", !$runtimeAiEnabled || $runtimeAiDriver === "local", "fail", "Runtime AI must use the local driver unless a project explicitly adds and audits another provider."),
            $this->check("runtime_ai_learning_path", !$runtimeAiEnabled || $this->runtimeAiLearningPathIsSafe(), "fail", "Runtime AI learning data must stay inside storage."),
        ];

        return [
            "schema" => "fnlla.security_audit.v1",
            "generated_at_utc" => gmdate(DATE_ATOM),
            "environment" => app_environment(),
            "checks" => $checks,
            "summary" => [
                "passed" => count(array_filter($checks, static fn (array $check): bool => $check["status"] === "pass")),
                "warnings" => count(array_filter($checks, static fn (array $check): bool => $check["status"] === "warning")),
                "failures" => count(array_filter($checks, static fn (array $check): bool => $check["status"] === "fail")),
            ],
        ];
    }

    private function check(string $id, bool $passed, string $severityOnFail, string $detail): array
    {
        return [
            "id" => $id,
            "status" => $passed ? "pass" : $severityOnFail,
            "detail" => $detail,
        ];
    }

    private function hasBrowserSecurityHeaders(array $headers): bool
    {
        foreach (["X-Content-Type-Options", "Referrer-Policy", "X-Frame-Options", "Permissions-Policy"] as $header) {
            if (trim((string) ($headers[$header] ?? "")) === "") {
                return false;
            }
        }

        return true;
    }

    private function runtimeAiLearningPathIsSafe(): bool
    {
        $relativePath = trim((string) config("ai.runtime.learning_path", "framework/ai/runtime-knowledge.json"));
        $relativePath = ltrim(str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $relativePath), DIRECTORY_SEPARATOR);

        if (in_array("..", explode(DIRECTORY_SEPARATOR, $relativePath), true)) {
            return false;
        }

        $path = storage_path($relativePath);
        $storageRoot = realpath(storage_path()) ?: storage_path();
        $targetDirectory = realpath(dirname($path)) ?: dirname($path);

        return str_starts_with($targetDirectory, $storageRoot);
    }

    private function runtimeAiBundleIsPresent(): bool
    {
        $relativePath = trim((string) config("ai.runtime.runtime_path", "resources/fnlla-ai-runtime"));
        $relativePath = ltrim(str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $relativePath), DIRECTORY_SEPARATOR);

        if ($relativePath === "" || in_array("..", explode(DIRECTORY_SEPARATOR, $relativePath), true)) {
            return false;
        }

        foreach (["VERSION", "MANIFEST.json", "profile.json"] as $file) {
            if (!is_file(base_path($relativePath . DIRECTORY_SEPARATOR . $file))) {
                return false;
            }
        }

        return true;
    }
}
