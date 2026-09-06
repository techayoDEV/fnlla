<?php

declare(strict_types=1);

define("FNLLA_RUNTIME_SKIP_AUTO_GUARD", true);
require dirname(__DIR__, 2) . "/bootstrap/common.php";
$settings = json_decode((string) getenv("FNLLA_SESSION_WORKER_SETTINGS"), true, 512, JSON_THROW_ON_ERROR);
session_set_save_handler(new \Fnlla\Php\Session\RedisSessionHandler($settings, 60), true);
ini_set("session.use_strict_mode", "1");
ini_set("session.use_cookies", "0");
ini_set("session.cache_limiter", "");
session_id($argv[1]);
if (!session_start()) { throw new RuntimeException("Worker session failed"); }
file_put_contents($argv[3], "ready");
usleep($argv[2] === "crash" ? 10000000 : 200000);
$_SESSION["counter"] = (int) ($_SESSION["counter"] ?? 0) + 1;
$result = ["id" => session_id(), "counter" => $_SESSION["counter"]];
if (!session_write_close()) { throw new RuntimeException("Worker session write failed"); }
echo json_encode($result, JSON_THROW_ON_ERROR);
