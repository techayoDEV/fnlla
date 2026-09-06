<?php

declare(strict_types=1);

namespace Fnlla\Php\Providers;

use Fnlla\Php\Ai\FionnRuntimeBridge;
use Fnlla\Php\Ai\LocalRuntimeAssistant;
use Fnlla\Php\Ai\RuntimeAiProviderInterface;
use Fnlla\Php\Ai\RuntimeAiProviderRegistry;
use Fnlla\Php\Container\Container;
use Fnlla\Php\Observability\MetricsRecorder;
use Fnlla\Php\Observability\RequestObserver;
use Fnlla\Php\Http\RequestLifecycleObserver;
use Fnlla\Php\Observability\RuntimeRequestObserver;
use Fnlla\Php\Support\EnvironmentFileManager;
use Fnlla\Php\Support\ProjectProfile;

final class FrameworkServiceProvider extends CoreServiceProvider
{
    public function boot(): void
    {
        $this->container->make(\Fnlla\Php\Events\Dispatcher::class)->listen("cache.cleared", function (): void {
            $this->container->make(MetricsRecorder::class)->clear();
            foreach ([framework_ai_context_path(), framework_ai_review_pack_path(), framework_ai_upgrade_brief_path(),
                framework_app_map_path(), framework_upgrade_plan_path()] as $path) {
                if (is_file($path) && !unlink($path)) {
                    throw new \RuntimeException("Cannot remove generated cache artifact: " . $path);
                }
            }
        });
    }

    public function register(): void
    {
        parent::register();
        if (ProjectProfile::hasPanel()) {
            (new DeveloperServiceProvider($this->container))->register();
        }
        $this->container->singleton(EnvironmentFileManager::class);
        $this->container->singleton(LocalRuntimeAssistant::class);
        $this->container->singleton(FionnRuntimeBridge::class);
        $this->container->bind(RuntimeAiProviderInterface::class,
            static fn (Container $container): RuntimeAiProviderInterface => framework_runtime_ai_provider($container));
        $this->container->singleton(RuntimeAiProviderRegistry::class);
        $this->container->singleton(MetricsRecorder::class);
        $this->container->singleton(RequestObserver::class);
        $this->container->singleton(RequestLifecycleObserver::class, RuntimeRequestObserver::class);
    }
}
