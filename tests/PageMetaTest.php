<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP TEST CASE
File: tests\PageMetaTest.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
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
            "Contact | FNLLA PHP",
            PageMeta::composeDocumentTitle([
                "site" => "FNLLA PHP",
                "page" => "Contact",
            ])
        );

        self::assertSame(
            "About | Framework | FNLLA PHP",
            PageMeta::composeDocumentTitle([
                "site" => "FNLLA PHP",
                "page" => "About",
                "section" => "Framework",
            ])
        );
    }

    public function testComposeDocumentTitleCollapsesHomePagesToTheSiteName(): void
    {
        self::assertSame(
            "FNLLA PHP",
            PageMeta::composeDocumentTitle([
                "site" => "FNLLA PHP",
                "page" => "Overview",
                "home" => true,
            ])
        );
    }

    public function testResolveDeduplicatesRepeatedLabels(): void
    {
        $meta = PageMeta::resolve([
            "site" => "FNLLA PHP",
            "page" => "FNLLA PHP",
            "section" => "Framework",
            "suffix" => "Framework",
        ], "FNLLA PHP");

        self::assertSame("FNLLA PHP | Framework", $meta["title"]);
        self::assertSame("FNLLA PHP", $meta["site"]);
    }
}
