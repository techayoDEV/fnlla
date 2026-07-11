<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA MAINTAINER SCRIPT
File: scripts\sync-version-manifest.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Supports framework maintenance, validation, release hygiene or repository hardening.
*/

define("FNLLA_RUNTIME_SKIP_AUTO_GUARD", true);

require dirname(__DIR__) . DIRECTORY_SEPARATOR . "bootstrap" . DIRECTORY_SEPARATOR . "common.php";

use Fnlla\Php\Support\VersionManifest;

$manifest = VersionManifest::syncRepositoryManifest();

fwrite(STDOUT, "FNLLA version manifest synchronized." . PHP_EOL);
fwrite(STDOUT, "Framework version: " . $manifest["product"]["version"] . PHP_EOL);
fwrite(STDOUT, "Vendored FNLLA Runtime version: " . $manifest["ui_runtime"]["vendored_version"] . PHP_EOL);
fwrite(STDOUT, "Manifest path: " . VersionManifest::repositoryManifestPath() . PHP_EOL);
