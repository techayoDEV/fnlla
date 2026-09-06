<?php

declare(strict_types=1);

define("FNLLA_RUNTIME_SKIP_AUTO_GUARD", true);
require dirname(__DIR__) . "/bootstrap.php";
$pdo = new PDO((string) getenv("FNLLA_TEST_MYSQL_DSN"), getenv("FNLLA_TEST_MYSQL_USER") ?: "root", getenv("FNLLA_TEST_MYSQL_PASSWORD") ?: "");
app()->instance(\Fnlla\Php\Database\DatabaseManager::class, \Fnlla\Php\Database\DatabaseManager::using($pdo));
config_set("developer_workspace.driver", "database");
config_set("developer_workspace.table", $argv[1]);
config_set("developer_workspace.notifications_table", $argv[2]);
$board = new \Fnlla\Php\Support\DeveloperWorkspaceBoard();
$notifications = new \Fnlla\Php\Support\DeveloperNotificationCenter();
for ($i = 0; $i < 15; $i++) {
    $key = "parallel-" . $argv[3] . "-" . $i;
    $board->create(["title" => $key]);
    $notifications->archive($key);
}
