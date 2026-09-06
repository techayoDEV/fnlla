<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests\Interop;

use Fnlla\Deploy\ReleaseStore;
use PHPUnit\Framework\TestCase;

final class DeploymentGatewayTest extends TestCase
{
    public function testHttpSwitchRollbackVersionedAssetsAndPrivatePaths(): void
    {
        $root = sys_get_temp_dir() . "/fnlla-gateway-" . bin2hex(random_bytes(8));
        mkdir($root . "/artifact/public", 0700, true);
        mkdir($root . "/web", 0700);
        file_put_contents($root . "/artifact/public/index.php", "<?php echo 'one';");
        file_put_contents($root . "/artifact/public/style.css", "body{color:red}");
        file_put_contents($root . "/artifact/public/private.php", "<?php echo 'private';");
        $store = new ReleaseStore($root . "/deployment");
        $store->stage($root . "/artifact", "one");
        $store->activate("one", null, static fn (): bool => true);
        file_put_contents($root . "/deployment/shared/.env", "SECRET=hidden");
        file_put_contents($root . "/web/index.php", "<?php require " . var_export(base_path("packages/fnlla-deploy/src/ReleaseStore.php"), true)
            . "; require " . var_export(base_path("packages/fnlla-deploy/src/Gateway.php"), true)
            . "; \\Fnlla\\Deploy\\Gateway::run(" . var_export($root . "/deployment", true) . ");");
        $socket = stream_socket_server("tcp://127.0.0.1:0", $errno, $error);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $process = proc_open([PHP_BINARY, "-S", $address, "-t", $root . "/web", $root . "/web/index.php"],
            [0 => ["pipe", "r"], 1 => ["file", $root . "/server.log", "a"], 2 => ["file", $root . "/server.log", "a"]], $pipes);
        fclose($pipes[0]);
        $get = static function (string $path) use ($address): array {
            $http_response_header = [];
            $body = @file_get_contents("http://" . $address . $path, false, stream_context_create([
                "http" => ["ignore_errors" => true, "timeout" => 2]]));
            return [$body, implode("\n", $http_response_header)];
        };
        try {
            for ($i = 0; $i < 100; $i++) {
                [$body] = $get("/");
                if ($body !== false) { break; }
                usleep(20000);
            }
            self::assertSame("one", $body);
            file_put_contents($root . "/artifact/public/index.php", "<?php echo 'two';");
            file_put_contents($root . "/artifact/public/style.css", "body{color:blue}");
            $store->stage($root . "/artifact", "two");
            $store->activate("two", "one", static fn (): bool => true);
            self::assertSame("two", $get("/")[0]);
            self::assertSame("body{color:red}", $get("/_fnlla/releases/one/style.css")[0]);
            self::assertSame("body{color:blue}", $get("/_fnlla/releases/two/style.css")[0]);
            self::assertStringContainsString("immutable", $get("/_fnlla/releases/two/style.css")[1]);
            foreach (["/.env", "/private.php", "/_fnlla/releases/one/private.php", "/_fnlla/releases/one/%2e%2e/.env"] as $path) {
                self::assertStringContainsString("404", $get($path)[1]);
            }
            $store->rollback("two", static fn (): bool => true);
            self::assertSame("one", $get("/")[0]);
        } finally {
            proc_terminate($process); proc_close($process);
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root,
                \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
            rmdir($root);
        }
    }
}
