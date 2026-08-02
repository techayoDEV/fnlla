<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\CacheClearCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements the maintained CLI surface and scheduler-oriented console behaviour.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Cache\CacheStoreInterface;
use Fnlla\Php\Console\Command;
use Fnlla\Php\Observability\MetricsRecorder;

final class CacheClearCommand extends Command
{
    public function name(): string
    {
        return "cache:clear";
    }

    public function description(): string
    {
        return "Clear the configured application cache store.";
    }

    public function handle(array $arguments): int
    {
        $this->container->make(CacheStoreInterface::class)->clear();
        $this->container->make(MetricsRecorder::class)->clear();

        foreach ([framework_ai_context_path(), framework_ai_review_pack_path(), framework_ai_upgrade_brief_path(), framework_app_map_path(), framework_upgrade_plan_path()] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->line("Cache cleared.");

        return 0;
    }
}
