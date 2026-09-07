<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests;

use Fnlla\Php\Support\FrameworkReleaseChannel;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

final class FrameworkReleaseChannelTest extends TestCase
{
    public function testOnlyConfirmedPublicStableReleasesAreAccepted(): void
    {
        $release = ["id" => 123, "tag_name" => "v2.2.0", "draft" => false, "prerelease" => false,
            "published_at" => "2026-09-07T12:00:00Z",
            "html_url" => "https://github.com/techayoDEV/fnlla/releases/tag/v2.2.0"];
        $validate = new ReflectionMethod(FrameworkReleaseChannel::class, "publishedRelease");
        self::assertSame($release, $validate->invoke(null, $release));
        self::assertSame($release, $validate->invoke(null, $release, "v2.2.0"));
        foreach ([["draft" => true], ["draft" => null], ["prerelease" => true], ["prerelease" => null],
            ["published_at" => null], ["published_at" => ""], ["id" => 0], ["id" => "123"],
            ["tag_name" => "main"], ["tag_name" => "v2.2.0-beta.1"], ["tag_name" => "v02.2.0"],
            ["tag_name" => "v2.1.3"], ["html_url" => "https://example.test/releases/v2.2.0"]] as $change) {
            try {
                $validate->invoke(null, array_replace($release, $change), "v2.2.0");
                self::fail("Unpublished or mismatched release metadata was accepted.");
            } catch (RuntimeException $error) {
                self::assertStringContainsString("No tag fallback", $error->getMessage());
            }
        }
    }

    public function testCachedSourceMustMatchThePublishedTag(): void
    {
        $validate = new ReflectionMethod(FrameworkReleaseChannel::class, "assertReleaseSourceIntegrity");
        $validate->invoke(null, base_path(), FrameworkReleaseChannel::OFFICIAL_REPOSITORY, "v2.2.0");
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("published tag do not match");
        $validate->invoke(null, base_path(), FrameworkReleaseChannel::OFFICIAL_REPOSITORY, "v2.1.3");
    }

    public function testApiFailuresCannotFallBackToGitTags(): void
    {
        $source = (string) file_get_contents(base_path("src/Support/FrameworkReleaseChannel.php"));
        self::assertStringNotContainsString("latestTagFromGit", $source);
        foreach (["fetchLatestRelease", "fetchReleaseByTag"] as $method) {
            $reflection = new ReflectionMethod(FrameworkReleaseChannel::class, $method);
            $lines = file((string) $reflection->getFileName());
            $body = implode("", array_slice($lines, $reflection->getStartLine() - 1,
                $reflection->getEndLine() - $reflection->getStartLine() + 1));
            self::assertStringContainsString("self::publishedRelease(self::requestJson(", $body);
            self::assertStringNotContainsString("catch (", $body);
        }
    }
}
