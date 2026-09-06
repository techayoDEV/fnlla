<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests;

use PHPUnit\Framework\TestCase;

final class HttpTransportTest extends TestCase
{
    public function testRealHttpEnforcesProxyAndPayloadBoundaries(): void
    {
        $root = sys_get_temp_dir() . "/fnlla-http-boundary-" . bin2hex(random_bytes(8));
        mkdir($root, 0700);
        $fixture = '<?php define("FNLLA_RUNTIME_SKIP_AUTO_GUARD", true); define("FNLLA_CACHE_ROOT", __DIR__ . "/cache"); $_ENV["TRUSTED_PROXIES"] = "127.0.0.1,10.0.0.0/24"; require BOOTSTRAP;
            config_set("security.request.max_body_bytes", 64);
            header("Content-Type: application/json");
            try { $request = \\Fnlla\\Php\\Http\\Request::capture(); echo json_encode(["ip"=>$request->ip(),"bytes"=>strlen($request->rawBody())]); }
            catch (\\Fnlla\\Php\\Http\\HttpException $error) { http_response_code($error->statusCode()); echo json_encode(["status"=>$error->statusCode()]); }';
        file_put_contents($root . "/index.php", str_replace("BOOTSTRAP", var_export(base_path("bootstrap/common.php"), true), $fixture));
        $socket = stream_socket_server("tcp://127.0.0.1:0", $errno, $error);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $process = proc_open([PHP_BINARY, "-S", $address, "-t", $root, $root . "/index.php"],
            [0 => ["pipe", "r"], 1 => ["file", $root . "/server.log", "a"], 2 => ["file", $root . "/server.log", "a"]], $pipes);
        self::assertTrue(is_resource($process));
        fclose($pipes[0]);
        $send = static function (string $headers, string $body = "") use ($address): string {
            $connection = @stream_socket_client("tcp://" . $address, $errno, $error, 2);
            if ($connection === false) { return ""; }
            try {
                stream_set_timeout($connection, 3);
                fwrite($connection, "POST / HTTP/1.1\r\nHost: localhost\r\nConnection: close\r\n" . $headers . "\r\n\r\n" . $body);
                return (string) stream_get_contents($connection);
            } finally { fclose($connection); }
        };
        try {
            for ($attempt = 0; $attempt < 100; $attempt++) {
                $response = $send("Content-Length: 0");
                if ($response !== "") { break; }
                usleep(20000);
            }
            self::assertStringContainsString("200 OK", $response);
            $response = $send("Content-Length: 64\r\nX-Forwarded-For: 127.0.0.1, 198.51.100.7, 10.0.0.1", str_repeat("x", 64));
            self::assertStringContainsString('"ip":"198.51.100.7"', $response);
            self::assertStringContainsString('"bytes":64', $response);
            self::assertStringContainsString("413", $send("Content-Length: 65", str_repeat("x", 65)));
            $response = $send("Content-Length: 0\r\nX-Forwarded-For: 127.0.0.1\r\nX-Forwarded-For: 198.51.100.7");
            self::assertStringContainsString('"ip":"198.51.100.7"', $response);
            // The SAPI may close a malformed framing request before invoking PHP.
            $rejected = $send("Content-Length: invalid");
            self::assertTrue($rejected === "" || preg_match('/^HTTP\/1\.[01] 400 /', $rejected) === 1);
            self::assertStringContainsString("413", $send("Transfer-Encoding: chunked", "41\r\n" . str_repeat("x", 65) . "\r\n0\r\n\r\n"));
        } finally {
            proc_terminate($process);
            proc_close($process);
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
            rmdir($root);
        }
    }
}
