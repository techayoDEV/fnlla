<?php

declare(strict_types=1);

define("FNLLA_RUNTIME_SKIP_AUTO_GUARD", true);
require dirname(__DIR__) . "/bootstrap.php";
config_set("queue.visibility_timeout_seconds", 60);
$queue = new \Fnlla\Php\Queue\FileQueueStore($argv[1]);
while (($job = $queue->pop()) !== null) {
    usleep(2000);
    echo $job["id"] . "\n";
    $queue->complete($job);
}
