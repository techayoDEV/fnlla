<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP CONSOLE SOURCE
File: src\Console\Commands\MakeControllerCommand.php
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

final class MakeControllerCommand extends Command
{
    public function name(): string
    {
        return "make:controller";
    }

    public function description(): string
    {
        return "Create a new controller class.";
    }

    public function handle(array $arguments): int
    {
        $name = trim((string) ($arguments[0] ?? ""));

        if ($name === "") {
            $this->error("Usage: make:controller <NameController>");

            return 1;
        }

        $className = str_ends_with($name, "Controller") ? $name : $name . "Controller";
        $path = base_path("src/Controllers/" . $className . ".php");

        if (is_file($path)) {
            $this->error("Controller already exists: " . $path);

            return 1;
        }

        $template = <<<PHP
<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP CONSOLE SOURCE
File: src\Console\Commands\MakeControllerCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements the maintained CLI surface and scheduler-oriented console behavior.
*/

namespace Fnlla\\Php\\Controllers;

use Fnlla\\Php\\Http\\Request;
use Fnlla\\Php\\Http\\Response;

final class {$className} extends Controller
{
    public function __invoke(Request \$request): Response
    {
        return Response::html("");
    }
}
PHP;

        file_put_contents($path, $template);
        $this->line("Created controller: " . $path);

        return 0;
    }
}
