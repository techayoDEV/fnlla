<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests;

use Fnlla\Php\Support\ProjectProfile;
use PHPUnit\Framework\TestCase;

final class ProjectProfileTest extends TestCase
{
    public function testProductProfilesResolveToRuntimeProfiles(): void
    {
        foreach (["fnlla" => "fnlla", "platform" => "fnlla", "full" => "fnlla", "core" => "core"] as $stored => $expected) {
            $root = sys_get_temp_dir() . "/fnlla-profile-" . bin2hex(random_bytes(6));
            mkdir($root . "/.fnlla", 0777, true);
            file_put_contents($root . "/.fnlla/project-profile", $stored . "\n");

            try {
                self::assertSame($expected, ProjectProfile::name($root));
                self::assertSame($expected === "fnlla", ProjectProfile::hasPanel($root));
            } finally {
                unlink($root . "/.fnlla/project-profile");
                rmdir($root . "/.fnlla");
                rmdir($root);
            }
        }
    }
}
