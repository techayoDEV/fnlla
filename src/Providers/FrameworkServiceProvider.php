<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA SERVICE PROVIDER SOURCE
File: src\Providers\FrameworkServiceProvider.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Registers maintained framework services and application-level boot behaviour.
*/

namespace Fnlla\Php\Providers;

use Fnlla\Php\Auth\AuthManager;
use Fnlla\Php\Auth\Authorization\Gate;
use Fnlla\Php\Auth\DatabaseUserProvider;
use Fnlla\Php\Auth\UserProviderInterface;
use Fnlla\Php\Ai\FionnRuntimeBridge;
use Fnlla\Php\Ai\LocalRuntimeAssistant;
use Fnlla\Php\Ai\RuntimeAiProviderInterface;
use Fnlla\Php\Ai\RuntimeAiProviderRegistry;
use Fnlla\Php\Cache\CacheStoreInterface;
use Fnlla\Php\Cache\FileCacheStore;
use Fnlla\Php\Cache\JsonCacheSerializer;
use Fnlla\Php\Cache\PhpCacheSerializer;
use Fnlla\Php\Cache\RateLimiter;
use Fnlla\Php\Cache\RedisCacheStore;
use Fnlla\Php\Console\Application as ConsoleApplication;
use Fnlla\Php\Container\Container;
use Fnlla\Php\Database\DatabaseManager;
use Fnlla\Php\Database\Migrations\Migrator;
use Fnlla\Php\Events\Dispatcher;
use Fnlla\Php\Exceptions\ExceptionHandler;
use Fnlla\Php\Filesystem\StorageManager;
use Fnlla\Php\Hashing\Hasher;
use Fnlla\Php\Localization\Translator;
use Fnlla\Php\Maintenance\CustomerAccessManager;
use Fnlla\Php\Maintenance\DeveloperActivityLog;
use Fnlla\Php\Maintenance\DeveloperAccessManager;
use Fnlla\Php\Maintenance\DeveloperControlManager;
use Fnlla\Php\Maintenance\MaintenanceAccessManager;
use Fnlla\Php\Mail\Mailer;
use Fnlla\Php\Observability\MetricsRecorder;
use Fnlla\Php\Observability\RequestObserver;
use Fnlla\Php\Queue\FileQueueStore;
use Fnlla\Php\Queue\QueueManager;
use Fnlla\Php\Queue\QueueStoreInterface;
use Fnlla\Php\Queue\RedisQueueStore;
use Fnlla\Php\Routing\Router;
use Fnlla\Php\Routing\UrlGenerator;
use Fnlla\Php\Session\SessionStore;
use Fnlla\Php\Support\ServiceProvider;
use Fnlla\Php\Support\DeveloperAnalyticsReport;
use Fnlla\Php\Support\DeveloperHeatmapReport;
use Fnlla\Php\Support\DeveloperNotificationCenter;
use Fnlla\Php\Support\DeveloperOperationsReport;
use Fnlla\Php\Support\DeveloperWorkspaceBoard;
use Fnlla\Php\Support\EnvironmentFileManager;
use Fnlla\Php\Support\TechAyoRemoteControlPlugin;
use RuntimeException;

final class FrameworkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->instance(Container::class, $this->container);
        $this->container->singleton(ExceptionHandler::class);
        $this->container->singleton(DatabaseManager::class);
        $this->container->singleton(SessionStore::class);
        $this->container->singleton(CacheStoreInterface::class, static function (): CacheStoreInterface {
            $defaultStore = (string) config("cache.default", "file");
            $storeConfig = config("cache.stores." . $defaultStore, []);
            $serializer = (string) config("cache.serializer", "json");

            if (!in_array($serializer, ["json", "php"], true)) {
                throw new RuntimeException("Unsupported cache serializer: " . $serializer);
            }

            if ($defaultStore === "redis") {
                return new RedisCacheStore((array) config("cache.stores.redis", []));
            }

            return new FileCacheStore(
                (string) ($storeConfig["path"] ?? storage_path("framework/cache")),
                $serializer === "php" ? new PhpCacheSerializer() : new JsonCacheSerializer(),
                new PhpCacheSerializer()
            );
        });
        $this->container->singleton(RateLimiter::class);
        $this->container->singleton(StorageManager::class);
        $this->container->singleton(Hasher::class);
        $this->container->singleton(Dispatcher::class);
        $this->container->singleton(Translator::class);
        $this->container->singleton(MaintenanceAccessManager::class);
        $this->container->singleton(DeveloperAccessManager::class);
        $this->container->singleton(CustomerAccessManager::class);
        $this->container->singleton(DeveloperActivityLog::class);
        $this->container->singleton(DeveloperControlManager::class);
        $this->container->singleton(DeveloperAnalyticsReport::class);
        $this->container->singleton(DeveloperHeatmapReport::class);
        $this->container->singleton(DeveloperNotificationCenter::class);
        $this->container->singleton(DeveloperOperationsReport::class);
        $this->container->singleton(DeveloperWorkspaceBoard::class);
        $this->container->singleton(TechAyoRemoteControlPlugin::class);
        $this->container->singleton(EnvironmentFileManager::class);
        $this->container->singleton(LocalRuntimeAssistant::class);
        $this->container->singleton(FionnRuntimeBridge::class);
        $this->container->bind(RuntimeAiProviderInterface::class, static function (Container $container): RuntimeAiProviderInterface {
            return \framework_runtime_ai_provider($container);
        });
        $this->container->singleton(RuntimeAiProviderRegistry::class);
        $this->container->singleton(Mailer::class);
        $this->container->singleton(MetricsRecorder::class);
        $this->container->singleton(RequestObserver::class);
        $this->container->singleton(QueueStoreInterface::class, static function (): QueueStoreInterface {
            $default = (string) config("queue.default", "file");

            if ($default === "redis") {
                return new RedisQueueStore((array) config("queue.connections.redis", []));
            }

            return new FileQueueStore(storage_path((string) config("queue.connections.file.path", "framework/queue")));
        });
        $this->container->singleton(QueueManager::class);
        $this->container->singleton(UserProviderInterface::class, static fn (Container $container): DatabaseUserProvider => new DatabaseUserProvider(
            $container->make(DatabaseManager::class)
        ));
        $this->container->singleton(AuthManager::class);
        $this->container->singleton(Gate::class);
        $this->container->singleton(Router::class, static fn (Container $container): Router => new Router($container));
        $this->container->singleton(UrlGenerator::class, static fn (Container $container): UrlGenerator => new UrlGenerator(
            $container->make(Router::class)
        ));
        $this->container->singleton(Migrator::class);
        $this->container->singleton(ConsoleApplication::class);
    }
}
