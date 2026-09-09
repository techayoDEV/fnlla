<?php

declare(strict_types=1);

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Support\FrameworkIdentity;

final class PublicApiLockCommand extends Command
{
    public function name(): string
    {
        return "api:lock";
    }

    public function description(): string
    {
        return "Write the small public API lock file used by release checks.";
    }

    public function handle(array $arguments): int
    {
        $path = base_path("docs/PUBLIC-API.lock.json");
        $payload = [
            "schema" => "fnlla.public_api_lock.v1",
            "helpers" => ["config", "env", "base_path", "public_path", "storage_path", "url", "asset", "route", "csrf_token", "csrf_field", "csp_nonce", "auth", "db", "cache", "queue", "runtime_ai", "project_leadership", "customer_access", "stream_request_body_to_file"],
            "commands" => ["doctor", "config:doctor", "security:audit", "ops:backup-plan", "project:acceptance", "developer:install-storage", "app:map", "upgrade:check", "perf:budget", "release:prepare", "release:manifest", "tech-debt:update", "ai:ask", "ai:triage", "ai:explain-log", "ai:brief", "ai:providers",
                "make:controller", "make:middleware", "make:command", "make:factory", "make:seeder", "make:migration",
                "migrate", "migrate:rollback", "migrate:status", "config:cache", "route:cache"],
            "schemas" => [
                "fnlla.technical_debt_report.v1",
                "fnlla.technical_debt_update.v1",
                "fnlla.debug_report.v1",
                "fnlla.debug_live.v1",
                "fnlla.runtime_issue_tracker.v1",
                "fnlla.developer_private_todo.v1",
            ],
            "data" => [
                "framework.identity",
                "database.transaction",
                "database.named_connections",
                "auth.identity.v1",
                "query_builder.offset",
                "query_builder.paginate",
                "developer.activity_export",
                "developer.activity_csv_export",
                "developer.activity",
                "developer.debug_live",
                "developer.runtime_issues",
                "developer.analytics",
                "developer.analytics_settings",
                "developer.cookie_consent_event",
                "developer.form_inbox_summary",
                "developer.integrations_settings",
                "developer.notifications",
                "developer.operations",
                "developer.policy_boundary",
                "developer.project_leadership",
                "developer.runtime_environment",
                "developer.security",
                "developer.storage_install",
                "developer.workspace",
                "developer.private_todo",
                "customer.access",
                "customer.workspace",
                "customer.analytics",
                "customer.heatmap",
                "remote_control_plugin",
                "techayo_remote_control",
                "techayo_remote_control_state",
                "runtime_ai.answer",
                "runtime_ai.providers",
                "runtime_ai.provider_status",
                "runtime_ai.provider.fionn",
            ],
            "framework_identity" => [
                "schema" => "fnlla.framework_identity.v1",
                "name" => FrameworkIdentity::PRODUCT_NAME,
                "lead_name" => FrameworkIdentity::LEAD_NAME,
                "lead_role" => FrameworkIdentity::LEAD_ROLE,
                "official_url" => FrameworkIdentity::OFFICIAL_URL,
                "support_email" => FrameworkIdentity::SUPPORT_EMAIL,
                "repository" => FrameworkIdentity::REPOSITORY,
            ],
        ];

        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL, LOCK_EX);
        $this->line("Public API lock written: " . $path);

        return 0;
    }
}
