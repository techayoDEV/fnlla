<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\MakeMigrationCommand.php
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

final class MakeMigrationCommand extends Command
{
    public function name(): string
    {
        return "make:migration";
    }

    public function description(): string
    {
        return "Create a new migration file.";
    }

    public function handle(array $arguments): int
    {
        $name = $arguments[0] ?? null;

        if (!is_string($name) || trim($name) === "") {
            $this->error("Usage: make:migration <name>");

            return 1;
        }

        $timestamp = gmdate("YmdHis");
        $className = str_replace(" ", "", ucwords(str_replace(["-", "_"], " ", $name)));
        $fileName = $timestamp . "_" . strtolower(preg_replace('/[^a-zA-Z0-9]+/', "_", $name)) . ".php";
        $path = base_path("database/migrations/" . $fileName);
        $template = <<<PHP
<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONSOLE SOURCE
File: src\Console\Commands\MakeMigrationCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements the maintained CLI surface and scheduler-oriented console behavior.
*/

use Fnlla\Php\Database\Migrations\Migration;

return new class(app(\\Fnlla\\Php\\Database\\DatabaseManager::class)) extends Migration {
    public function up(): void
    {
        // \$this->statement("CREATE TABLE ...");
    }

    public function down(): void
    {
        // \$this->statement("DROP TABLE ...");
    }
};
PHP;

        file_put_contents($path, $template);
        $this->line("Created migration: " . $path);

        return 0;
    }
}
