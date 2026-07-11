<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA TEST CASE
File: tests\PageMetaTest.php
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

use Fnlla\Php\Support\PageMeta;
use PHPUnit\Framework\TestCase;

final class PageMetaTest extends TestCase
{
    public function testComposeDocumentTitleBuildsProfessionalOrdering(): void
    {
        self::assertSame(
            "Contact | FNLLA",
            PageMeta::composeDocumentTitle([
                "site" => "FNLLA",
                "page" => "Contact",
            ])
        );

        self::assertSame(
            "About | Framework | FNLLA",
            PageMeta::composeDocumentTitle([
                "site" => "FNLLA",
                "page" => "About",
                "section" => "Framework",
            ])
        );
    }

    public function testComposeDocumentTitleCollapsesHomePagesToTheSiteName(): void
    {
        self::assertSame(
            "FNLLA",
            PageMeta::composeDocumentTitle([
                "site" => "FNLLA",
                "page" => "Overview",
                "home" => true,
            ])
        );
    }

    public function testResolveDeduplicatesRepeatedLabels(): void
    {
        $meta = PageMeta::resolve([
            "site" => "FNLLA",
            "page" => "FNLLA",
            "section" => "Framework",
            "suffix" => "Framework",
        ], "FNLLA");

        self::assertSame("FNLLA | Framework", $meta["title"]);
        self::assertSame("FNLLA", $meta["site"]);
    }
}
