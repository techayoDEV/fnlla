<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP CONSOLE SOURCE
File: src\Console\Commands\MakeMiddlewareCommand.php
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

final class MakeMiddlewareCommand extends Command
{
    public function name(): string
    {
        return "make:middleware";
    }

    public function description(): string
    {
        return "Create a new middleware class.";
    }

    public function handle(array $arguments): int
    {
        $name = trim((string) ($arguments[0] ?? ""));

        if ($name === "") {
            $this->error("Usage: make:middleware <NameMiddleware>");

            return 1;
        }

        $className = str_ends_with($name, "Middleware") ? $name : $name . "Middleware";
        $path = base_path("src/Middleware/" . $className . ".php");

        if (is_file($path)) {
            $this->error("Middleware already exists: " . $path);

            return 1;
        }

        $template = <<<PHP
<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP CONSOLE SOURCE
File: src\Console\Commands\MakeMiddlewareCommand.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements the maintained CLI surface and scheduler-oriented console behavior.
*/

namespace Fnlla\\Php\\Middleware;

use Fnlla\\Php\\Http\\Request;

final class {$className} implements MiddlewareInterface
{
    public function handle(Request \$request, callable \$next): mixed
    {
        return \$next(\$request);
    }
}
PHP;

        file_put_contents($path, $template);
        $this->line("Created middleware: " . $path);

        return 0;
    }
}
