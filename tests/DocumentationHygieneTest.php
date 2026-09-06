<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests;

use PHPUnit\Framework\TestCase;

final class DocumentationHygieneTest extends TestCase
{
    public function testConsolidatedChecklistPreservesPublishedBookmark(): void
    {
        $guide = (string) file_get_contents(base_path("docs/RELEASE-AND-OPERATIONS.md"));
        $alias = (string) file_get_contents(base_path("docs/major-release-checklist.html"));
        self::assertStringContainsString("## Release Acceptance Checklist", $guide);
        self::assertStringContainsString("release-and-operations.html#release-acceptance-checklist", $alias);
        self::assertStringContainsString('rel="canonical"', $alias);
        self::assertFalse(is_file(base_path("docs/MAJOR-RELEASE-CHECKLIST.md")));
    }

    public function testPublicDocumentationHasNoKnownPrivateMarkersOrBrokenLinks(): void
    {
        require_once base_path("scripts/check-docs.php");
        self::assertSame([], fnlla_documentation_issues(base_path()));
    }

    public function testGuardReportsCategoriesWithoutEchoingSecretContents(): void
    {
        require_once base_path("scripts/check-docs.php");
        $root = sys_get_temp_dir() . "/fnlla-docs-" . bin2hex(random_bytes(6));
        mkdir($root, 0700);
        try {
            $credential = "ghp_" . str_repeat("a", 36);
            file_put_contents($root . "/README.md", "[Missing](" . $credential . ".md)\nC:/Users/example-user/project\n");
            $issues = fnlla_documentation_issues($root);
            self::assertSame(3, count($issues));
            self::assertStringNotContainsString($credential, implode("\n", $issues));
            file_put_contents($root . "/README.md", "[Guide](guide.md)\nSupport: support@example.com\n");
            file_put_contents($root . "/guide.md", "# Neutral guide\n");
            self::assertSame([], fnlla_documentation_issues($root));
        } finally {
            foreach (glob($root . "/*") ?: [] as $path) { unlink($path); }
            rmdir($root);
        }
    }

    public function testGuardIncludesNestedFrameworkAndRuntimeDocumentation(): void
    {
        require_once base_path("scripts/check-docs.php");
        $root = sys_get_temp_dir() . "/fnlla-docs-" . bin2hex(random_bytes(6));
        $directories = ["docs", "docs/framework", "public", "public/vendor",
            "public/vendor/fnlla-runtime", "vendor"];
        $files = ["docs/framework/README.md", "public/vendor/fnlla-runtime/README.md", "vendor/README.md"];
        mkdir($root, 0700);
        try {
            foreach ($directories as $directory) { mkdir($root . "/" . $directory, 0700); }
            foreach ($files as $file) { file_put_contents($root . "/" . $file, "C:/Users/example-user/project\n"); }
            self::assertSame([
                "docs/framework/README.md: workstation path",
                "public/vendor/fnlla-runtime/README.md: workstation path",
            ], fnlla_documentation_issues($root));
        } finally {
            foreach ($files as $file) { if (is_file($root . "/" . $file)) { unlink($root . "/" . $file); } }
            foreach (array_reverse($directories) as $directory) { if (is_dir($root . "/" . $directory)) { rmdir($root . "/" . $directory); } }
            rmdir($root);
        }
    }
}
