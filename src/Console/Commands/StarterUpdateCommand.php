<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\StarterUpdateCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Preserves the legacy `starter:update` command name as a hidden alias so older
  workflows can continue while the public surface moves to `framework:update`.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;

final class StarterUpdateCommand extends Command
{
    public function name(): string
    {
        return "starter:update";
    }

    public function description(): string
    {
        return "Legacy alias for framework:update.";
    }

    public function hidden(): bool
    {
        return true;
    }

    public function handle(array $arguments): int
    {
        return $this->container->make(FrameworkUpdateCommand::class)->handle($arguments);
    }
}
