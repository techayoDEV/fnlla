<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA TEST CASE
File: tests\BootstrapAutoloadTest.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Validates maintained framework behavior inside the repository-local test harness.
*/

namespace Fnlla\Php\Tests;

use PHPUnit\Framework\TestCase;

final class BootstrapAutoloadTest extends TestCase
{
    public function testFallbackAutoloaderResolvesComposerPsr4NamespacesWithoutVendorAutoload(): void
    {
        self::assertFalse(is_file(base_path("vendor/autoload.php")));
        self::assertTrue(class_exists("Database\\Seeders\\DatabaseSeeder"));
        self::assertTrue(class_exists("Database\\Factories\\UserFactory"));
    }
}
