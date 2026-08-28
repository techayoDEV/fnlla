<?php

declare(strict_types=1);

/*
===============================================================================
PROJECT TEST CASE
File: tests\BootstrapAutoloadTest.php
Purpose:
- Confirms the exported project can autoload the PSR-4 namespaces it actually ships.
===============================================================================
*/

namespace Fnlla\Php\Tests;

use PHPUnit\Framework\TestCase;

final class BootstrapAutoloadTest extends TestCase
{
    public function testFallbackAutoloaderResolvesExportedProjectNamespacesWithoutVendorAutoload(): void
    {
        self::assertFalse(is_file(base_path("vendor/autoload.php")));
        self::assertTrue(class_exists("Database\\Seeders\\DatabaseSeeder"));
        self::assertFalse(class_exists("Database\\Factories\\UserFactory"));
    }
}
