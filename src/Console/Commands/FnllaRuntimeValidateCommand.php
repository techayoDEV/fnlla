<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\FnllaRuntimeValidateCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements the maintained CLI surface and scheduler-oriented console behavior.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Support\FnllaRuntimeGuard;

final class FnllaRuntimeValidateCommand extends Command
{
    public function name(): string
    {
        return "fnlla-runtime:validate";
    }

    public function description(): string
    {
        return "Validate that views and assets stay within FNLLA's built-in runtime contract.";
    }

    public function handle(array $arguments): int
    {
        FnllaRuntimeGuard::validateOnly();
        $this->line("FNLLA built-in runtime contract passed.");

        return 0;
    }
}
