<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA AI SOURCE
File: src\Ai\RuntimeAiProviderRegistry.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Reports configured runtime AI provider readiness without coupling FNLLA to
  external provider implementations.
*/

namespace Fnlla\Php\Ai;

use Fnlla\Php\Container\Container;
use Throwable;

final class RuntimeAiProviderRegistry
{
    public function __construct(private ?Container $container = null)
    {
    }

    public function report(): array
    {
        $providers = $this->providers();

        return [
            "schema" => "fnlla.runtime_ai.providers.v1",
            "selected_driver" => $this->selectedDriver(),
            "external_calls" => $this->hasExternalCalls($providers),
            "providers" => $providers,
        ];
    }

    public function providers(): array
    {
        $providers = (array) config("ai.runtime.providers", []);
        ksort($providers);

        $statuses = [];

        foreach ($providers as $driver => $providerConfig) {
            $statuses[(string) $driver] = $this->providerStatus((string) $driver, (array) $providerConfig);
        }

        return $statuses;
    }

    public function status(string $driver): array
    {
        $driver = trim($driver);
        $providers = (array) config("ai.runtime.providers", []);

        if ($driver === "" || !is_array($providers[$driver] ?? null)) {
            return [
                "schema" => "fnlla.runtime_ai.provider_status.v1",
                "driver" => $driver,
                "configured_driver" => $driver,
                "selected" => $driver === $this->selectedDriver(),
                "provider_ready" => false,
                "external_calls" => false,
                "integration_state" => "missing",
                "reason" => "Provider is not configured.",
            ];
        }

        return $this->providerStatus($driver, (array) $providers[$driver]);
    }

    public function selectedDriver(): string
    {
        $driver = trim((string) config("ai.runtime.driver", "local"));

        return $driver !== "" ? $driver : "local";
    }

    private function providerStatus(string $driver, array $providerConfig): array
    {
        $class = (string) ($providerConfig["class"] ?? "");
        $base = [
            "schema" => "fnlla.runtime_ai.provider_status.v1",
            "driver" => $driver,
            "configured_driver" => $driver,
            "configured_class" => $class,
            "selected" => $driver === $this->selectedDriver(),
            "configured_external_calls" => ($providerConfig["external_calls"] ?? false) === true,
            "provider_ready" => false,
            "external_calls" => false,
            "integration_state" => (string) ($providerConfig["integration_state"] ?? "configured"),
        ];

        if ($class === "" || !class_exists($class)) {
            return array_merge($base, [
                "integration_state" => "invalid",
                "reason" => "Provider class is missing.",
            ]);
        }

        if (!is_subclass_of($class, RuntimeAiProviderInterface::class)) {
            return array_merge($base, [
                "integration_state" => "invalid",
                "reason" => "Provider class must implement RuntimeAiProviderInterface.",
            ]);
        }

        try {
            $provider = $this->container instanceof Container
                ? $this->container->make($class)
                : new $class();

            $status = $provider->status();
        } catch (Throwable $exception) {
            return array_merge($base, [
                "integration_state" => "error",
                "reason" => $exception->getMessage(),
            ]);
        }

        return array_merge($base, $status, [
            "configured_driver" => $driver,
            "configured_class" => $class,
            "configured_external_calls" => ($providerConfig["external_calls"] ?? false) === true,
            "selected" => $driver === $this->selectedDriver(),
        ]);
    }

    private function hasExternalCalls(array $providers): bool
    {
        foreach ($providers as $provider) {
            if (($provider["external_calls"] ?? false) === true || ($provider["configured_external_calls"] ?? false) === true) {
                return true;
            }
        }

        return false;
    }
}
