<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP MAINTAINER SCRIPT
File: scripts\validate-version-manifest.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Supports framework maintenance, validation, release hygiene or repository hardening.
*/

define("FNLLA_UI_SKIP_AUTO_GUARD", true);

require dirname(__DIR__) . DIRECTORY_SEPARATOR . "bootstrap" . DIRECTORY_SEPARATOR . "common.php";

use Fnlla\Php\Support\VersionManifest;

$errors = VersionManifest::validateRepositoryManifest();

if ($errors !== []) {
    fwrite(STDOUT, "FNLLA PHP version manifest validation failed." . PHP_EOL);

    foreach ($errors as $error) {
        fwrite(STDOUT, "- " . $error . PHP_EOL);
    }

    exit(1);
}

fwrite(STDOUT, "FNLLA PHP version manifest passed." . PHP_EOL);
