<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\MakeCommandCommand.php
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

final class MakeCommandCommand extends Command
{
    public function name(): string
    {
        return "make:command";
    }

    public function description(): string
    {
        return "Create a new CLI command class.";
    }

    public function handle(array $arguments): int
    {
        $name = trim((string) ($arguments[0] ?? ""));

        if ($name === "") {
            $this->error("Usage: make:command <NameCommand>");

            return 1;
        }

        $className = str_ends_with($name, "Command") ? $name : $name . "Command";
        $commandName = strtolower(str_replace("_", ":", preg_replace('/(?<!^)[A-Z]/', '_$0', preg_replace('/Command$/', "", $className))));
        $path = base_path("src/Console/Commands/" . $className . ".php");

        if (is_file($path)) {
            $this->error("Command already exists: " . $path);

            return 1;
        }

        $template = <<<PHP
<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\MakeCommandCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements the maintained CLI surface and scheduler-oriented console behavior.
*/

namespace Fnlla\\Php\\Console\\Commands;

use Fnlla\\Php\\Console\\Command;

final class {$className} extends Command
{
    public function name(): string
    {
        return "{$commandName}";
    }

    public function description(): string
    {
        return "Describe the command.";
    }

    public function handle(array \$arguments): int
    {
        \$this->line("{$commandName}");

        return 0;
    }
}
PHP;

        file_put_contents($path, $template);
        $this->line("Created command: " . $path);

        return 0;
    }
}
