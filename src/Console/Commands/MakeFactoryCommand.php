<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP CONSOLE SOURCE
File: src\Console\Commands\MakeFactoryCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements the maintained CLI surface and scheduler-oriented console behavior.
*/

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;

final class MakeFactoryCommand extends Command
{
    public function name(): string
    {
        return "make:factory";
    }

    public function description(): string
    {
        return "Create a new data factory class.";
    }

    public function handle(array $arguments): int
    {
        $name = trim((string) ($arguments[0] ?? ""));

        if ($name === "") {
            $this->error("Usage: make:factory <NameFactory>");

            return 1;
        }

        $className = str_ends_with($name, "Factory") ? $name : $name . "Factory";
        $table = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', preg_replace('/Factory$/', "", $className))) . "s";
        $directory = base_path("database/factories");

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path = $directory . DIRECTORY_SEPARATOR . $className . ".php";

        if (is_file($path)) {
            $this->error("Factory already exists: " . $path);

            return 1;
        }

        $template = <<<PHP
<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP CONSOLE SOURCE
File: src\Console\Commands\MakeFactoryCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements the maintained CLI surface and scheduler-oriented console behavior.
*/

namespace Database\\Factories;

use Fnlla\\Php\\Database\\Factories\\Factory;

final class {$className} extends Factory
{
    protected function table(): string
    {
        return "{$table}";
    }

    protected function definition(): array
    {
        return [];
    }
}
PHP;

        file_put_contents($path, $template);
        $this->line("Created factory: " . $path);

        return 0;
    }
}
