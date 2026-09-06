<?php

declare(strict_types=1);

use Fnlla\Php\Container\Container;

function maintenance_access(): \Fnlla\Php\Maintenance\MaintenanceAccessManager
{
    return app(\Fnlla\Php\Maintenance\MaintenanceAccessManager::class);
}

function developer_access(): \Fnlla\Php\Maintenance\DeveloperAccessManager
{
    return app(\Fnlla\Php\Maintenance\DeveloperAccessManager::class);
}

function customer_access(): \Fnlla\Php\Maintenance\CustomerAccessManager
{
    return app(\Fnlla\Php\Maintenance\CustomerAccessManager::class);
}

function developer_activity(): \Fnlla\Php\Maintenance\DeveloperActivityLog
{
    return app(\Fnlla\Php\Maintenance\DeveloperActivityLog::class);
}

function developer_control(): \Fnlla\Php\Maintenance\DeveloperControlManager
{
    return app(\Fnlla\Php\Maintenance\DeveloperControlManager::class);
}

function framework_runtime_ai_provider(?Container $container = null): \Fnlla\Php\Ai\RuntimeAiProviderInterface
{
    $driver = trim((string) config("ai.runtime.driver", "local")) ?: "local";
    $providers = (array) config("ai.runtime.providers", []);
    $provider = (array) ($providers[$driver] ?? []);
    $class = (string) ($provider["class"] ?? \Fnlla\Php\Ai\LocalRuntimeAssistant::class);

    if ($class === "" || !class_exists($class) || !is_subclass_of($class, \Fnlla\Php\Ai\RuntimeAiProviderInterface::class)) {
        throw new RuntimeException("Runtime AI provider is not configured for driver: " . $driver);
    }

    if ($container instanceof Container && $container->has($class)) {
        return $container->make($class);
    }

    return new $class();
}

function runtime_ai(): \Fnlla\Php\Ai\RuntimeAiProviderInterface
{
    $container = $GLOBALS["fnlla_container"] ?? $GLOBALS["fnlla_php_container"] ?? null;

    if ($container instanceof Container && $container->has(\Fnlla\Php\Ai\RuntimeAiProviderInterface::class)) {
        return $container->make(\Fnlla\Php\Ai\RuntimeAiProviderInterface::class);
    }

    return framework_runtime_ai_provider($container instanceof Container ? $container : null);
}

function project_leadership(string $context = "admin"): array
{
    return (new \Fnlla\Php\Support\ProjectLeadership())->state($context);
}

function framework_ai_context_path(): string
{
    return framework_cache_path("ai-context.json");
}

function framework_ai_review_pack_path(): string
{
    return framework_cache_path("ai-review-pack.json");
}

function framework_ai_upgrade_brief_path(): string
{
    return framework_cache_path("ai-upgrade-brief.md");
}

function framework_app_map_path(): string
{
    return framework_cache_path("app-map.json");
}

function framework_upgrade_plan_path(): string
{
    return framework_cache_path("upgrade-plan.json");
}

function framework_technical_debt_report_path(): string
{
    return framework_cache_path("technical-debt-report.json");
}

