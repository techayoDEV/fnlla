<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\OptimizeWarmCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

Purpose:
- Warms production-oriented caches and generated runtime hints.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Support\AssetManifestBuilder;
use Fnlla\Php\Support\PreloadBuilder;

final class OptimizeWarmCommand extends Command
{
    public function name(): string
    {
        return "optimize:warm";
    }

    public function description(): string
    {
        return "Build bootstrap caches, asset manifest and optional preload file.";
    }

    public function handle(array $arguments): int
    {
        $optimizeExit = (new OptimizeCommand($this->container))->handle([]);
        $assetManifest = $this->container->make(AssetManifestBuilder::class)->build(public_path(), framework_asset_manifest_path());
        $preload = $this->container->make(PreloadBuilder::class)->build(framework_preload_path());

        $this->line("Asset manifest warmed: " . $assetManifest["path"] . " (" . (string) $assetManifest["assets"] . " assets)");
        $this->line("OPcache preload file generated: " . $preload["path"] . " (" . (string) $preload["files"] . " files)");

        return $optimizeExit === 0 ? 0 : 1;
    }
}
