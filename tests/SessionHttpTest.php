<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests;

use PHPUnit\Framework\TestCase;

final class SessionHttpTest extends TestCase
{
    public function testPersistentIdentityRotationExpiryAndDomainIsolation(): void
    {
        $this->verifyPersistentIdentity("file");
    }

    public function verifyPersistentIdentity(string $driver, array $redisSettings = []): void
    {
        $root = sys_get_temp_dir() . "/fnlla-session-http-" . bin2hex(random_bytes(8));
        mkdir($root, 0700);
        copy(__DIR__ . "/fixtures/session-http.php", $root . "/index.php");
        $socket = stream_socket_server("tcp://127.0.0.1:0", $errno, $error);
        self::assertTrue(is_resource($socket));
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $environment = getenv();
        $environment["FNLLA_SESSION_TEST_BOOTSTRAP"] = base_path("bootstrap/common.php");
        $environment["FNLLA_SESSION_TEST_DRIVER"] = $driver;
        $environment["FNLLA_SESSION_TEST_REDIS"] = json_encode($redisSettings, JSON_THROW_ON_ERROR);
        $extensionOptions = $driver === "redis" && getenv("FNLLA_TEST_REDIS_EXTENSION")
            ? ["-d", "extension=" . getenv("FNLLA_TEST_REDIS_EXTENSION")] : [];
        $process = proc_open([PHP_BINARY, ...$extensionOptions, "-d", "output_buffering=0", "-S", $address, "-t", $root, $root . "/index.php"],
            [0 => ["pipe", "r"], 1 => ["file", $root . "/server.log", "a"], 2 => ["file", $root . "/server.log", "a"]], $pipes, $root, $environment);
        self::assertTrue(is_resource($process));
        fclose($pipes[0]);
        $send = static function (string $action = "state", string $id = "") use ($address, $root): array {
            $connection = @stream_socket_client("tcp://" . $address, $errno, $error, 1);
            if ($connection === false) { return ["raw" => "", "body" => []]; }
            try {
                stream_set_timeout($connection, 5);
                $cookie = $id === "" ? "" : "Cookie: fnlla_test_session=" . $id . "\r\n";
                fwrite($connection, "GET /?action=" . $action . " HTTP/1.1\r\nHost: localhost\r\nConnection: close\r\n" . $cookie . "\r\n");
                $raw = (string) stream_get_contents($connection);
                $parts = explode("\r\n\r\n", $raw, 2);
                if ($action !== "late-start") {
                    self::assertTrue(is_array(json_decode($parts[1] ?? "", true)), $action . ": " . $raw . "\n" . file_get_contents($root . "/server.log"));
                }
                return ["raw" => $raw, "body" => json_decode($parts[1] ?? "", true) ?? []];
            } finally { fclose($connection); }
        };
        try {
            for ($i = 0; $i < 100; $i++) {
                $response = $send();
                if ($response["raw"] !== "") { break; }
                usleep(20000);
            }
            self::assertStringContainsString("200 OK", $response["raw"], (string) file_get_contents($root . "/server.log"));
            foreach (["secure", "httponly", "samesite=lax"] as $attribute) {
                self::assertStringContainsString($attribute, strtolower($response["raw"]));
            }
            self::assertFalse($response["body"]["application"]);
            if ($driver === "redis") {
                self::assertStringContainsString("200 OK", $send("storage-independent")["raw"]);
            }
            $anonymous = $response["body"]["id"];
            $login = $send("login", $anonymous)["body"];
            self::assertNotSame($anonymous, $login["id"]);
            self::assertTrue($login["application"]);
            self::assertFalse($login["developer"]);
            self::assertFalse($login["customer"]);
            self::assertFalse($send("state", $anonymous)["body"]["application"]);
            $id = $login["id"];
            $developer = $send("developer", $id)["body"];
            self::assertNotSame($id, $developer["id"]);
            self::assertTrue($developer["application"]);
            self::assertTrue($developer["developer"]);
            self::assertFalse($developer["customer"]);
            $developer = $send("refresh-developer", $developer["id"])["body"];
            self::assertSame("dev@example.com", $developer["developer_email"]);
            $customer = $send("customer", $developer["id"])["body"];
            self::assertTrue($customer["customer"]);
            self::assertNotSame($developer["id"], $customer["id"]);
            $logout = $send("logout", $customer["id"])["body"];
            self::assertFalse($logout["application"]);
            self::assertTrue($logout["developer"]);
            self::assertTrue($logout["customer"]);
            self::assertSame([42], $logout["cart"]);
            self::assertNotSame($customer["id"], $logout["id"]);
            self::assertFalse($send("state", $customer["id"])["body"]["developer"]);
            $revoked = $send("revoke-developer", $logout["id"])["body"];
            self::assertFalse($revoked["developer"]);
            self::assertTrue($revoked["customer"]);
            $invalidated = $send("invalidate", $revoked["id"])["body"];
            self::assertFalse($invalidated["customer"]);
            self::assertSame(null, $invalidated["cart"]);
            $state = $send("developer")["body"];
            $removed = $send("remove-developer", $state["id"])["body"];
            self::assertFalse($removed["developer"]);
            self::assertSame(null, $removed["developer_email"]);
            foreach (["idle", "absolute", "corrupt", "future", "missing-meta"] as $action) {
                $state = $send("login")["body"];
                $state = $send("developer", $state["id"])["body"];
                $state = $send("customer", $state["id"])["body"];
                $send($action, $state["id"]);
                $expired = $send("state", $state["id"])["body"];
                foreach (["application", "developer", "customer"] as $domain) {
                    self::assertFalse($expired[$domain], $action . ": " . $domain);
                }
                self::assertSame(null, $expired["cart"], $action);
                self::assertNotSame($state["id"], $expired["id"], $action);
            }
            $state = $send("login")["body"];
            $send("legacy", $state["id"]);
            self::assertTrue($send("state", $state["id"])["body"]["application"]);
            $send("rotate", $state["id"]);
            $rotated = $send("state", $state["id"])["body"];
            self::assertTrue($rotated["application"]);
            self::assertNotSame($state["id"], $rotated["id"]);
            self::assertSame($state["started_at"], $rotated["started_at"]);
            self::assertFalse($send("state", $state["id"])["body"]["application"]);
            $fixed = str_repeat("a", 32);
            self::assertNotSame($fixed, $send("state", $fixed)["body"]["id"]);
            $late = $send("late-start");
            self::assertStringContainsString("session-rejected", $late["raw"]);
            self::assertStringNotContainsString("unsafe-success", $late["raw"]);
        } finally {
            proc_terminate($process);
            proc_close($process);
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
            rmdir($root);
        }
    }
}
