<?php

declare(strict_types=1);

namespace Fnlla\Php\Providers;

use Fnlla\Php\Support\ServiceProvider;

final class DeveloperServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach ([
            \Fnlla\Php\Maintenance\MaintenanceAccessManager::class,
            \Fnlla\Php\Maintenance\DeveloperAccessManager::class,
            \Fnlla\Php\Maintenance\CustomerAccessManager::class,
            \Fnlla\Php\Maintenance\DeveloperActivityLog::class,
            \Fnlla\Php\Maintenance\DeveloperControlManager::class,
            \Fnlla\Php\Support\DeveloperAnalyticsReport::class,
            \Fnlla\Php\Support\DeveloperHeatmapReport::class,
            \Fnlla\Php\Support\DeveloperNotificationCenter::class,
            \Fnlla\Php\Support\DeveloperOperationsReport::class,
            \Fnlla\Php\Support\DeveloperWorkspaceBoard::class,
            \Fnlla\Php\Support\TechAyoRemoteControlPlugin::class,
        ] as $service) {
            $this->container->singleton($service);
        }
    }
}
