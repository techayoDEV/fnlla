<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests;

use Fnlla\Php\Support\DeveloperWorkspaceBoard;
use PHPUnit\Framework\TestCase;

final class WorkspaceConcurrencyTest extends TestCase
{
    public function testRejectedStatePreservesPreviousJsonAndRemovesStagingFiles(): void
    {
        $directory = sys_get_temp_dir() . "/fnlla-state-" . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $path = $directory . "/state.json";
        $store = new \Fnlla\Php\Support\LockedJsonStore($path);
        try {
            $store->update(static fn (array $state): array => ["value" => "original"]);
            $original = file_get_contents($path);
            try {
                $store->update(static fn (array $state): array => ["value" => str_repeat("x", 2097153)]);
                self::fail("Oversized state accepted.");
            } catch (\RuntimeException $error) {
                self::assertStringContainsString("size limit", $error->getMessage());
            }
            self::assertSame($original, file_get_contents($path));
            self::assertSame(["value" => "original"], $store->read());
            self::assertSame([], glob($directory . "/.state-*") ?: []);
        } finally {
            foreach ([$path, $path . ".lock"] as $file) { if (is_file($file)) { unlink($file); } }
            rmdir($directory);
        }
    }

    public function testParallelFileWritersPreserveAllTasksAndCorruptState(): void
    {
        $relative = "framework/workspace-concurrency-" . bin2hex(random_bytes(8)) . ".json";
        $path = storage_path($relative);
        $script = sys_get_temp_dir() . "/fnlla-workspace-worker-" . bin2hex(random_bytes(8)) . ".php";
        $saved = config("developer_workspace");
        $workers = [];
        try {
            file_put_contents($path, json_encode(["tasks" => []]));
            file_put_contents($script, '<?php define("FNLLA_RUNTIME_SKIP_AUTO_GUARD",true); require $argv[1];'
                . 'config_set("developer_workspace.driver","file"); config_set("developer_workspace.path",$argv[2]);'
                . '$board = new \\Fnlla\\Php\\Support\\DeveloperWorkspaceBoard(); for($i=0;$i<15;$i++) $board->create(["title"=>$argv[3]."-".$i]);');
            for ($i = 0; $i < 4; $i++) {
                $process = proc_open([PHP_BINARY, $script, base_path("bootstrap/common.php"), $relative, (string) $i],
                    [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes);
                fclose($pipes[0]);
                $workers[] = [$process, $pipes];
            }
            foreach ($workers as [$process, $pipes]) {
                $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                self::assertSame(0, proc_close($process), $output);
            }
            $state = json_decode((string) file_get_contents($path), true);
            self::assertSame(60, count($state["tasks"]));
            file_put_contents($path, "invalid json");
            config_set("developer_workspace.driver", "file");
            config_set("developer_workspace.path", $relative);
            try {
                (new DeveloperWorkspaceBoard())->create(["title" => "must not overwrite"]);
                self::fail("Corrupt workspace was replaced.");
            } catch (\JsonException) {
                self::assertSame("invalid json", file_get_contents($path));
            }
        } finally {
            config_set("developer_workspace", $saved);
            foreach ([$script, $path, $path . ".lock"] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}
