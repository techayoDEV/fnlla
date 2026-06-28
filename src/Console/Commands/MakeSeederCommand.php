<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP CONSOLE SOURCE
File: src\Console\Commands\MakeSeederCommand.php
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

final class MakeSeederCommand extends Command
{
    public function name(): string
    {
        return "make:seeder";
    }

    public function description(): string
    {
        return "Create a new database seeder class.";
    }

    public function handle(array $arguments): int
    {
        $name = trim((string) ($arguments[0] ?? ""));

        if ($name === "") {
            $this->error("Usage: make:seeder <NameSeeder>");

            return 1;
        }

        $className = str_ends_with($name, "Seeder") ? $name : $name . "Seeder";
        $directory = base_path("database/seeders");

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path = $directory . DIRECTORY_SEPARATOR . $className . ".php";

        if (is_file($path)) {
            $this->error("Seeder already exists: " . $path);

            return 1;
        }

        $template = <<<PHP
<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP CONSOLE SOURCE
File: src\Console\Commands\MakeSeederCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements the maintained CLI surface and scheduler-oriented console behavior.
*/

namespace Database\\Seeders;

use Fnlla\\Php\\Database\\Seeders\\Seeder;

final class {$className} extends Seeder
{
    public function run(): void
    {
        //
    }
}
PHP;

        file_put_contents($path, $template);
        $this->line("Created seeder: " . $path);

        return 0;
    }
}
