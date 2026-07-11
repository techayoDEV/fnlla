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
- Registers maintained framework services and application-level boot behavior.
*/

namespace Fnlla\Php\Providers;

use Fnlla\Php\Auth\AuthManager;
use Fnlla\Php\Auth\Authorization\Gate;
use Fnlla\Php\Auth\DatabaseUserProvider;
use Fnlla\Php\Auth\UserProviderInterface;
use Fnlla\Php\Cache\CacheStoreInterface;
use Fnlla\Php\Cache\FileCacheStore;
use Fnlla\Php\Cache\RateLimiter;
use Fnlla\Php\Console\Application as ConsoleApplication;
use Fnlla\Php\Container\Container;
use Fnlla\Php\Database\DatabaseManager;
use Fnlla\Php\Database\Migrations\Migrator;
use Fnlla\Php\Events\Dispatcher;
use Fnlla\Php\Exceptions\ExceptionHandler;
use Fnlla\Php\Filesystem\StorageManager;
use Fnlla\Php\Hashing\Hasher;
use Fnlla\Php\Localization\Translator;
use Fnlla\Php\Mail\Mailer;
use Fnlla\Php\Queue\QueueManager;
use Fnlla\Php\Routing\Router;
use Fnlla\Php\Routing\UrlGenerator;
use Fnlla\Php\Session\SessionStore;
use Fnlla\Php\Support\ServiceProvider;

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

            return new FileCacheStore((string) ($storeConfig["path"] ?? storage_path("framework/cache")));
        });
        $this->container->singleton(RateLimiter::class);
        $this->container->singleton(StorageManager::class);
        $this->container->singleton(Hasher::class);
        $this->container->singleton(Dispatcher::class);
        $this->container->singleton(Translator::class);
        $this->container->singleton(Mailer::class);
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
